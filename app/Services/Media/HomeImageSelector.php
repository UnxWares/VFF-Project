<?php

namespace App\Services\Media;

use App\Models\Line;
use App\Models\Media;
use Illuminate\Support\Facades\DB;

/**
 * Réserve des images de l'accueil pour les périodes anciennes.
 *
 * `is_featured` (image principale Wikidata) ne donne que 13, 5 et 0 images avant 1971. Cette règle écrite et
 * rejouable ajoute le drapeau `provider_meta.home_pick` aux images Commons d'une ligne qui satisfont :
 * année représentée au plus `$maxYear`, largeur d'au moins 1 200 px, format paysage (rapport 1,3 ou plus),
 * légende, auteur, licence libre ou domaine public, sujet ferroviaire (légende ou catégories), ni plan, carte,
 * peinture, portrait ni affiche, et pas de doublon presque identique (même ligne et même titre normalisé,
 * ou même ligne, même année et même auteur, ou même fichier Commons déjà retenu pour une autre ligne) ; trois images au plus par ligne, les plus larges d'abord.
 */
class HomeImageSelector
{
    public const FLAG = 'home_pick';

    private const RAIL = 'gare|station|train|rail|locomotive|loco|chemin de fer|chemins de fer|voie ferr|viaduc|viaduct|tunnel|tramway|ligne|pont|halte|depot|dépôt|ferroviaire|railway|wagon|automotrice|autorail|tender';

    private const EXCLUDED = 'plan |carte de|map of|peinture|painting|tableau|dessin|affiche|poster|portrait|gravure|schéma|échelle|nageur|swimm|croquis|aquarelle|lithograph|illustration|exposition|tour de france|cycliste|football|match|horaire|chaix|tracé|carte du';

    private const EXCLUDED_CATEGORIES = 'maps? |maps$|timetable|chaix|schematic|diagram';

    public function __construct(private readonly int $perLine = 3) {}

    /** SQL des images qui passent la règle, avec leur rang de qualité. */
    private function eligibleSql(): string
    {
        return "SELECT id FROM (
            SELECT m.id,
                   ROW_NUMBER() OVER (PARTITION BY m.mediable_id, regexp_replace(lower(coalesce(m.title, '')), '[^a-zà-ÿ]+|\\.[a-z]+\$', '', 'g') ORDER BY m.width DESC, m.id) AS r_title,
                   ROW_NUMBER() OVER (PARTITION BY m.mediable_id, m.depicted_year, coalesce(m.author, '') ORDER BY m.width DESC, m.id) AS r_year,
                   ROW_NUMBER() OVER (PARTITION BY m.external_id ORDER BY m.width DESC, m.id) AS r_ext,
                   m.mediable_id, m.width
            FROM media m
            WHERE m.mediable_type = ? AND m.provider = ? AND NOT m.is_featured
              AND m.depicted_year IS NOT NULL AND m.depicted_year <= ?
              AND m.thumb_url IS NOT NULL AND m.source_url IS NOT NULL
              AND m.width >= 1200 AND m.width >= 1.3 * m.height
              AND m.caption IS NOT NULL AND length(m.caption) >= 12
              AND m.author IS NOT NULL AND m.author <> ''
              AND m.license IS NOT NULL AND m.rights_status IN ('public_domain', 'open_license')
              AND (coalesce(m.caption, '') || ' ' || coalesce(m.title, '')) ~* ?
              AND coalesce(m.provider_meta->>'categories', '') !~* ?
              AND (coalesce(m.caption, '') || ' ' || coalesce(m.title, '')) !~* ?
        ) q WHERE r_title = 1 AND r_year = 1 AND r_ext = 1";
    }

    /**
     * @return array{before: int, after: int}
     */
    public function apply(int $maxYear = 1970, bool $dryRun = false): array
    {
        $params = [Line::class, Media::PROVIDER_COMMONS, $maxYear, self::RAIL, self::EXCLUDED_CATEGORIES, self::EXCLUDED];
        $before = $this->count();

        $ids = collect(DB::select(
            "SELECT id FROM (
                SELECT e.id, ROW_NUMBER() OVER (PARTITION BY e.mediable_id ORDER BY e.width DESC, e.id) AS rn
                FROM media mm JOIN (SELECT x.id, m2.mediable_id, m2.width FROM ({$this->eligibleSql()}) x JOIN media m2 ON m2.id = x.id) e ON e.id = mm.id
            ) t WHERE rn <= ?",
            [...$params, $this->perLine],
        ))->pluck('id')->all();

        if ($dryRun) {
            return ['before' => $before, 'after' => count($ids)];
        }

        DB::transaction(function () use ($ids): void {
            // Rejouable : on repart de zéro, puis on repose le drapeau sur la sélection.
            DB::statement("UPDATE media SET provider_meta = provider_meta - '".self::FLAG."' WHERE jsonb_exists(provider_meta, '".self::FLAG."')");
            foreach (array_chunk($ids, 1000) as $chunk) {
                $in = implode(',', array_map('intval', $chunk));
                DB::statement("UPDATE media SET provider_meta = coalesce(provider_meta, '{}'::jsonb) || '{\"".self::FLAG."\": true}'::jsonb WHERE id IN ({$in})");
            }
        });

        return ['before' => $before, 'after' => $this->count()];
    }

    public function count(): int
    {
        return (int) DB::selectOne("SELECT count(*) AS n FROM media WHERE jsonb_exists(provider_meta, '".self::FLAG."')")->n;
    }
}
