<?php

namespace App\Services\Signalling;

use App\Models\Line;
use App\Support\Lgv;
use Illuminate\Support\Facades\DB;

/**
 * Ce que VFF sait de la date de mise en service de la signalisation (cantonnement) d'une ligne classique en
 * service aujourd'hui : fiche de ligne, `GET /api/v1/lines/{line}` et `/signalling-history` (clé `dating`), GraphQL
 * `Line.signalling_dating`, outil MCP `get_line` et `signalling_history`.
 *
 * Trois états, calculés (jamais écrits à la main) :
 *  - `dated` : au moins une mise en service de cantonnement (BAL, BAPR, BM, CT, ou signalisation en cabine TVM et ETCS)
 *    datée et sûre est exposée par `LineSignallingHistory` ;
 *  - `undated` : ligne classique en service (hors ligne à grande vitesse, hors train touristique seul) sans aucun
 *    fait de ce genre : aucun document libre consulté ne date cette mise en service, et la fiche le dit ;
 *  - `not_applicable` : ligne à grande vitesse, ligne fermée ou ligne touristique seule (la mention ne s'applique pas).
 *
 * `checked_at` : date du dernier passage sur les documents libres (database/data/signalling_dating_check.php).
 * Le KVB et la commande centralisée ne datent pas un cantonnement : ils ne comptent pas.
 */
final class SignallingDating
{
    public const DATED = 'dated';

    public const UNDATED = 'undated';

    public const NOT_APPLICABLE = 'not_applicable';

    /** Rôles qui datent une mise en service de cantonnement (`block`, `cab` : la cabine est à la fois cantonnement et contrôle). */
    public const ROLES = ['block', 'cab'];

    /** Langues du texte (clés de `signalling_history.dating.undated`). */
    private const LANGS = ['fr', 'en', 'de'];

    /** Date du dernier passage sur les documents libres (AAAA-MM-JJ). */
    public static function checkedAt(): string
    {
        $file = (array) require database_path('data/signalling_dating_check.php');

        return (string) $file['checked_at'];
    }

    /**
     * Mention dans les trois langues du site, quelle que soit la langue de la requête (réponses mises en cache).
     *
     * @return array{fr: string, en: string, de: string}
     */
    public static function messages(): array
    {
        $out = [];
        foreach (self::LANGS as $lang) {
            $out[$lang] = (string) trans('signalling_history.dating.undated', [], $lang);
        }

        return $out;
    }

    /**
     * État de plusieurs lignes en une requête.
     *
     * @param  list<int>  $lineIds
     * @return array<int, array{status: string, checked_at: string, messages: array{fr: string, en: string, de: string}|null}>
     */
    public function forLines(array $lineIds): array
    {
        if ($lineIds === []) {
            return [];
        }
        $rows = DB::table('lines as l')->whereIn('l.id', $lineIds)->get([
            'l.id', 'l.name', 'l.is_lgv',
            DB::raw("exists (select 1 from line_segments s join eras e on e.id = s.era_id and e.is_current
                where s.line_id = l.id and s.status = 'in_service' and not s.is_heritage) as in_service"),
            DB::raw("exists (select 1 from line_signalling_events x where x.line_id = l.id
                and x.role in ('block', 'cab') and x.confidence in ('high', 'medium')) as dated"),
        ]);
        $checkedAt = self::checkedAt();
        $messages = self::messages();
        $out = [];
        foreach ($rows as $r) {
            $status = ((bool) $r->is_lgv || Lgv::nameIsLgv((string) $r->name) || ! (bool) $r->in_service) ? self::NOT_APPLICABLE
                : ((bool) $r->dated ? self::DATED : self::UNDATED);
            $out[(int) $r->id] = ['status' => $status, 'checked_at' => $checkedAt, 'messages' => $status === self::UNDATED ? $messages : null];
        }

        return $out;
    }

    /** @return array{status: string, checked_at: string, messages: array{fr: string, en: string, de: string}|null} */
    public function forLine(Line $line): array
    {
        return $this->forLines([$line->id])[$line->id];
    }
}
