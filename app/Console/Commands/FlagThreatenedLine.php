<?php

namespace App\Console\Commands;

use App\Support\Advocacy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Attache à une ligne un message de soutien de VFF, affiché en bandeau sur la
 * fiche, en encart d'accueil et en halo sur la carte. Deux types de campagne :
 *   - threatened : ligne MENACÉE de fermeture (bandeau et halo rouges) ;
 *   - reopening  : campagne pour la RÉOUVERTURE d'une ligne fermée (vert).
 *
 *   php artisan vff:flag-threatened 878000 --title="…" --message="…" --url="https://…"
 *   php artisan vff:flag-threatened 610000 --kind=reopening --title="…" --cta="Soutenir …" --url="https://…"
 *   php artisan vff:flag-threatened 405000 --title="…" --source="France 3 Normandie|2026-07-23|https://…"
 *   php artisan vff:flag-threatened 878000 --off          # retire le message
 *
 * Sources (--source, répétable) : « Libellé|AAAA-MM-JJ|https://… », listées sous le message de la fiche.
 *
 * Langues : le texte français (--title, --message, --cta) est la référence ; ses
 * traductions anglaise et allemande (--title-en, --message-de…) sont stockées à
 * côté (App\Support\Advocacy) et affichées selon la langue de la page. Sans
 * traduction, un texte par défaut est traduit (lang/{fr,en,de}/home.php) ; un texte
 * propre non traduit s'affiche en français, signalé comme tel.
 *
 * Les campagnes soutenues par VFF sont aussi rejouées par AdvocacySeeder.
 */
class FlagThreatenedLine extends Command
{
    protected $signature = 'vff:flag-threatened
        {code : code RFN de la ligne}
        {--kind=threatened : threatened (menacée) ou reopening (campagne de réouverture)}
        {--title= : titre du bandeau (français)}
        {--title-en= : titre en anglais}
        {--title-de= : titre en allemand}
        {--message= : message de soutien (français)}
        {--message-en= : message en anglais}
        {--message-de= : message en allemand}
        {--cta= : libellé du bouton d\'action (français ; défaut « Agir pour la ligne »)}
        {--cta-en= : libellé du bouton en anglais}
        {--cta-de= : libellé du bouton en allemand}
        {--url= : URL d\'action (pétition, association…)}
        {--source=* : source du message, « Libellé|AAAA-MM-JJ|https://… » (répétable)}
        {--off : retire le message de soutien}';

    protected $description = 'Affiche le soutien de VFF à une ligne menacée ou à une campagne de réouverture.';

    public function handle(): int
    {
        $code = (string) $this->argument('code');
        $line = DB::table('lines')->whereRaw("REGEXP_REPLACE(code, '\\s+', '', 'g') = ?", [preg_replace('/\s+/', '', $code)])->first();
        if (! $line) {
            $this->error("Ligne {$code} introuvable");

            return self::FAILURE;
        }

        if ($this->option('off')) {
            DB::table('lines')->where('id', $line->id)->update(['is_threatened' => false, 'advocacy' => null]);
            Cache::forget('home_stats');
            $this->info("✓ {$line->name} : message de soutien retiré.");

            return self::SUCCESS;
        }

        $kind = (string) $this->option('kind');
        if (! in_array($kind, Advocacy::KINDS, true)) {
            $this->error('--kind doit valoir '.implode(' ou ', Advocacy::KINDS));

            return self::FAILURE;
        }

        $texts = [];
        foreach (['title' => 'title', 'message' => 'message', 'cta_label' => 'cta'] as $field => $option) {
            $texts[$field] = ['fr' => $this->option($option), 'en' => $this->option($option.'-en'), 'de' => $this->option($option.'-de')];
        }
        try {
            $sources = array_map(Advocacy::parseSource(...), array_values((array) $this->option('source')));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $advocacy = Advocacy::build($kind, $texts, $this->option('url') ?: null, $sources);

        DB::table('lines')->where('id', $line->id)->update([
            // is_threatened reste réservé aux lignes menacées (halo rouge, filtres).
            'is_threatened' => $kind === 'threatened',
            'advocacy' => json_encode($advocacy, JSON_UNESCAPED_UNICODE),
        ]);
        Cache::forget('home_stats');

        $this->info("✓ {$line->name} ({$code}) : ".($kind === 'reopening' ? 'campagne de réouverture' : 'ligne menacée').'.');
        $this->line('  '.$advocacy['title']);
        if ($advocacy['cta_url']) {
            $this->line('  → '.$advocacy['cta_url']);
        }

        return self::SUCCESS;
    }
}
