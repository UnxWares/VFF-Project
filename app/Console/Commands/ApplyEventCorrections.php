<?php

namespace App\Console\Commands;

use App\Enums\LineEventType;
use App\Services\WikipediaLineParser;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Corrections SOURCÉES des événements de lignes (database/data/line_event_corrections*.php :
 * line_event_corrections.php, puis les fichiers thématiques, ex. line_event_corrections_fins.php) :
 * ajout d'un événement manquant (source « manual », libellé facultatif « label » pour un fait de
 * type other, ex. line_event_corrections_actualite.php), neutralisation d'une fausse fermeture
 * (desserte réduite, trains saisonniers maintenus) ou portion corrigée.
 *
 * Pourquoi un fichier rejoué plutôt qu'un UPDATE ponctuel : les imports (lignes-oubliees,
 * Wikipédia…) réécrivent leurs événements à chaque passage ; la correction doit être
 * réappliquée après eux. vff:rebuild-history l'appelle avant la résolution des portions.
 *
 * Idempotente : fingerprint « correction:… » pour les ajouts (un ajout retiré du fichier est
 * supprimé de la base), neutralisation et déplacement recherchent l'événement visé (source,
 * type, date) à chaque passage.
 *
 *   php artisan vff:apply-event-corrections --dry-run
 *   php artisan vff:apply-event-corrections --line=473000
 */
class ApplyEventCorrections extends Command
{
    protected $signature = 'vff:apply-event-corrections
        {--line= : code SNCF ou id d\'une ligne}
        {--dry-run : affiche les corrections sans rien écrire}';

    protected $description = 'Applique les corrections sourcées des événements de lignes (ajouts, fausses fermetures, portions).';

    public const SOURCE = 'manual';

    public const FINGERPRINT_PREFIX = 'correction:';

    private const ACTIONS = ['add', 'neutralize', 'relocate'];

    /** Valeurs admises (contraintes de line_events). */
    private const REUSES = ['greenway', 'road', 'path', 'urban', 'other'];

    private const HERITAGE_KINDS = ['tourist_train', 'velorail', 'museum'];

    /** Libellés absents de WikipediaLineParser::TYPE_LABELS. */
    private const EXTRA_LABELS = ['conversion' => 'Reconversion de l\'emprise', 'heritage_start' => 'Début de l\'exploitation patrimoniale',
        'heritage_end' => 'Fin de l\'exploitation patrimoniale'];

    /**
     * Fichiers de corrections : le fichier principal puis les fichiers thématiques
     * (line_event_corrections_*.php), dans l'ordre alphabétique.
     *
     * @return list<string>
     */
    public static function files(): array
    {
        $files = glob(database_path('data/line_event_corrections*.php')) ?: [];
        sort($files);

        return $files;
    }

    public function handle(): int
    {
        $corrections = [];
        foreach (self::files() as $file) {
            foreach (require $file as $n => $c) {
                $corrections[basename($file, '.php').' n° '.$n] = $c;
            }
        }
        $dry = (bool) $this->option('dry-run');
        $only = $this->option('line');
        $types = array_column(LineEventType::cases(), 'value');
        $rows = [];
        $fps = [];
        $errors = 0;

        foreach ($corrections as $i => $c) {
            $line = $this->findLine($c['line'] ?? null);
            $problem = match (true) {
                $line === null => 'ligne inconnue « '.($c['line'] ?? '?').' »',
                ! in_array($c['action'] ?? null, self::ACTIONS, true) => 'action inconnue « '.($c['action'] ?? '?').' »',
                ($c['action'] === 'add') && ! in_array($c['type'] ?? null, $types, true) => 'type inconnu « '.($c['type'] ?? '?').' »',
                ($c['action'] === 'add') && ! $this->validDate($c['date'] ?? null) => 'date invalide',
                ($c['action'] === 'add') && isset($c['reuse']) && ! in_array($c['reuse'], self::REUSES, true) => 'reuse inconnu « '.$c['reuse'].' »',
                ($c['action'] === 'add') && isset($c['heritage_kind']) && ! in_array($c['heritage_kind'], self::HERITAGE_KINDS, true) => 'heritage_kind inconnu « '.$c['heritage_kind'].' »',
                $c['action'] !== 'add' && (empty($c['match']['source']) || empty($c['match']['type']) || ! $this->validDate($c['match']['date'] ?? null)) => 'match incomplet (source, type, date)',
                empty($c['source_url']) || empty($c['reason']) => 'source_url et reason obligatoires',
                default => null,
            };
            if ($problem !== null) {
                $this->error("Correction {$i} : {$problem}");
                $errors++;

                continue;
            }
            if ($c['action'] === 'add') {
                // Toujours compté (même hors --line) : l'ajout reste dans la base.
                $fps[] = $this->fingerprint($line, $c);
            }
            if ($only && $only !== $line->code && $only !== (string) $line->id) {
                continue;
            }

            [$decision, $detail] = match ($c['action']) {
                'add' => $this->add($line, $c, $dry),
                'neutralize' => $this->neutralize($line, $c, $dry),
                'relocate' => $this->relocate($line, $c, $dry),
            };
            $rows[] = [$line->code ?? $line->id, $c['action'], $c['type'] ?? $c['match']['type'], $c['date'] ?? $c['match']['date'], $decision, mb_strimwidth($detail, 0, 70, '…')];
        }

        // Ajout retiré du fichier : l'événement disparaît (seulement les ajouts de ce fichier).
        $stale = DB::table('line_events')->where('source', self::SOURCE)->where('fingerprint', 'like', self::FINGERPRINT_PREFIX.'%')
            ->when($fps, fn ($q) => $q->whereNotIn('fingerprint', $fps));
        if (! $only && ($n = $stale->count()) > 0) {
            $rows[] = ['', 'add', '', '', $dry ? 'à supprimer' : 'supprimé', "{$n} ajout(s) retiré(s) du fichier"];
            if (! $dry) {
                $stale->delete();
            }
        }

        $this->table(['ligne', 'action', 'type', 'date', 'décision', 'détail'], $rows);
        $this->info(($dry ? '[dry-run] ' : '').count($rows).' correction(s) traitée(s)'.($errors ? ", {$errors} invalide(s)" : '').'.');

        return $errors ? self::FAILURE : self::SUCCESS;
    }

    /** @return array{0: string, 1: string} */
    private function add(object $line, array $c, bool $dry): array
    {
        $fp = $this->fingerprint($line, $c);
        $where = ($c['from'] ?? null) && ($c['to'] ?? null) ? (preg_match('/^[AEIOUYÉÈÊÂÎÔ]/u', $c['from']) ? " d'" : ' de ')."{$c['from']} à {$c['to']}" : '';
        $data = [
            'line_id' => $line->id,
            'type' => $c['type'],
            'event_date' => $c['date'],
            'date_precision' => $c['precision'] ?? 'day',
            // Libellé rédigé (« label ») pour un fait d'actualité de type other, traduit par
            // lang/{fr,en,de}/generated.php (label.*) ; sinon celui du type.
            'description' => $this->text(($c['label'] ?? WikipediaLineParser::TYPE_LABELS[$c['type']] ?? self::EXTRA_LABELS[$c['type']] ?? 'Événement').$where.' (correction sourcée : '.$c['reason'].')'),
            'source' => self::SOURCE,
            'source_url' => $c['source_url'],
            'source_quote' => $this->text(mb_strimwidth($c['quote'] ?? '', 0, 300, '…')),
            'confidence' => 'high',
            'section_from' => $c['from'] ?? null,
            'section_to' => $c['to'] ?? null,
            'section_pk_start_m' => $c['pk_start'] ?? null,
            'section_pk_end_m' => $c['pk_end'] ?? null,
            'reuse' => $c['reuse'] ?? null,
            'heritage_kind' => $c['heritage_kind'] ?? null,
            'updated_at' => now(),
        ];
        $cur = DB::table('line_events')->where('fingerprint', $fp)->first();
        if ($cur === null) {
            if (! $dry) {
                DB::table('line_events')->insert($data + ['fingerprint' => $fp, 'section_resolution' => 'pending', 'created_at' => now()]);
            }

            return [$dry ? 'à ajouter' : 'ajouté', $c['reason']];
        }
        $samePortion = true;
        foreach (['section_from', 'section_to', 'section_pk_start_m', 'section_pk_end_m'] as $col) {
            $samePortion = $samePortion && (string) $cur->{$col} === (string) $data[$col];
        }
        if (! $samePortion) {
            $data += ['section_resolution' => 'pending', 'section_geom' => null, 'section_length_m' => null];
        }
        if (! $dry) {
            DB::table('line_events')->where('id', $cur->id)->update($data);
        }

        return ['à jour', $c['reason']];
    }

    /** @return array{0: string, 1: string} */
    private function neutralize(object $line, array $c, bool $dry): array
    {
        $label = $c['label'] ?? WikipediaLineParser::SERVICE_REDUCTION_LABEL;
        $description = $this->text($label.' (correction sourcée : '.$c['reason'].')');
        $targets = $this->matching($line, $c['match'], [$c['match']['type'], 'other']);
        if ($targets->isEmpty()) {
            $this->warn("  {$line->code} : aucun événement {$c['match']['source']} {$c['match']['type']} du {$c['match']['date']} (source modifiée ?)");

            return ['introuvable', $c['reason']];
        }
        $todo = $targets->filter(fn ($e) => $e->type !== 'other' || $e->description !== $description);
        if (! $dry && $todo->isNotEmpty()) {
            DB::table('line_events')->whereIn('id', $todo->pluck('id'))->update(['type' => 'other', 'description' => $description, 'updated_at' => now()]);
        }

        return [$todo->isEmpty() ? 'déjà appliqué' : ($dry ? 'à neutraliser' : 'neutralisé').' ('.$todo->count().')', $c['reason']];
    }

    /** @return array{0: string, 1: string} */
    private function relocate(object $line, array $c, bool $dry): array
    {
        // Déjà déplacé lors d'un passage précédent : le départ est celui de la correction.
        $targets = $this->matching($line, $c['match'], [$c['match']['type']], $c['from'] ?? null);
        if ($targets->isEmpty()) {
            $this->warn("  {$line->code} : aucun événement {$c['match']['source']} {$c['match']['type']} du {$c['match']['date']} (source modifiée ?)");

            return ['introuvable', $c['reason']];
        }
        // Réimport de la source après déplacement (vff:import-wikipedia-chronology) : la portion reste
        // celle de la correction, mais la note « Portion non précisée » remise par l'import interdit
        // toute résolution (SectionResolver) : l'événement est à déplacer de nouveau (800000, 2022).
        $todo = $targets->filter(fn ($e) => $e->section_from !== ($c['from'] ?? null) || $e->section_to !== ($c['to'] ?? null)
            || $e->section_pk_start_m != ($c['pk_start'] ?? null) || $e->section_pk_end_m != ($c['pk_end'] ?? null)
            || str_starts_with((string) $e->section_resolution_note, 'Portion non précisée'));
        if (! $dry && $todo->isNotEmpty()) {
            DB::table('line_events')->whereIn('id', $todo->pluck('id'))->update([
                'section_from' => $c['from'] ?? null, 'section_to' => $c['to'] ?? null,
                'section_pk_start_m' => $c['pk_start'] ?? null, 'section_pk_end_m' => $c['pk_end'] ?? null,
                'section_resolution' => 'pending', 'section_geom' => null, 'section_length_m' => null,
                'section_resolution_note' => mb_strimwidth($this->text('Portion corrigée : '.$c['reason']), 0, 500, '…'),
                'updated_at' => now(),
            ]);
        }

        return [$todo->isEmpty() ? 'déjà appliqué' : ($dry ? 'à déplacer' : 'déplacé').' ('.$todo->count().')', $c['reason']];
    }

    /**
     * Événements visés : ligne, source, date, type (ou déjà neutralisé), départ facultatif.
     *
     * @param  array<string, mixed>  $m
     * @param  list<string>  $types
     * @return Collection<int, \stdClass>
     */
    private function matching(object $line, array $m, array $types, ?string $alsoFrom = null): Collection
    {
        return DB::table('line_events')->where('line_id', $line->id)->where('source', $m['source'])
            ->where('event_date', $m['date'])->whereIn('type', $types)
            ->when(isset($m['from']), fn ($q) => $q->whereIn('section_from', array_filter([$m['from'], $alsoFrom])))
            ->get(['id', 'type', 'description', 'section_from', 'section_to', 'section_pk_start_m', 'section_pk_end_m', 'section_resolution_note']);
    }

    private function fingerprint(object $line, array $c): string
    {
        return self::FINGERPRINT_PREFIX.sha1(implode('|', [$line->code ?? $line->id, $c['type'], $c['date'],
            $c['from'] ?? '', $c['to'] ?? '', $c['pk_start'] ?? '', $c['pk_end'] ?? '']));
    }

    private function findLine(?string $ref): ?object
    {
        if ($ref === null || $ref === '') {
            return null;
        }

        return DB::table('lines')->where('code', $ref)
            ->orWhere('id', ctype_digit($ref) && strlen($ref) < 6 ? (int) $ref : 0)->first(['id', 'code', 'name']);
    }

    private function validDate(?string $d): bool
    {
        return is_string($d) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /** Pas de tiret long dans les textes affichés. */
    private function text(string $s): string
    {
        return str_replace(['—', '--'], '-', $s);
    }
}
