<?php

namespace App\Services\Lines;

use Illuminate\Support\Str;

/**
 * Slugs neutres des lignes créées par vff:link-traces depuis le site archeoferroviaire, dont le slug portait
 * le préfixe « archeo- » : le slug d'une fiche publique ne nomme pas une source. Calcul seul (aucune
 * écriture), pour vff:neutralize-line-slugs et vff:link-traces.
 *
 * Schéma : le slug de la ligne sans le préfixe (« archeo-<nom> » devient « <nom> », par exemple
 * « ligne-de-grenoble-a-veurey »), donc le nom normalisé que donnerait Str::slug, suffixes « -2 » déjà
 * présents gardés. En cas de collision avec un slug pris (une autre ligne, un ancien slug retiré, un
 * nouveau slug déjà attribué), suffixe « -2 », « -3 »… Le code de ligne n'entre pas dans le slug : il peut
 * être attribué ou corrigé plus tard, le slug d'une fiche ne change plus.
 */
final class NeutralLineSlugs
{
    /** Préfixe retiré. */
    public const PREFIX = 'archeo-';

    /** Base quand le nom ne donne aucun mot. */
    public const FALLBACK = 'ligne';

    /** Slug neutre d'un nom de ligne (création d'une ligne). */
    public static function fromName(string $name): string
    {
        return Str::slug($name) ?: self::FALLBACK;
    }

    /** Slug neutre d'un ancien slug préfixé, avant résolution des collisions. */
    public static function stripped(string $slug): string
    {
        $rest = str_starts_with($slug, self::PREFIX) ? substr($slug, strlen(self::PREFIX)) : $slug;

        return trim($rest, '-') !== '' ? $rest : self::FALLBACK;
    }

    /** Premier slug libre à partir d'une base : la base, puis base-2, base-3… */
    public static function free(string $base, callable $isTaken): string
    {
        $slug = $base;
        for ($i = 2; $isTaken($slug); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /**
     * Plan des renommages.
     *
     * @param  list<array{id: int, slug: string}>  $lines  lignes dont le slug porte encore le préfixe
     * @param  list<array{from: string, line_id: int, line_slug: string}>  $merged  anciens slugs préfixés de lignes fusionnées
     *                                                                              dans une autre (alt_names.merged), absents de la base
     * @param  list<string>  $taken  tous les slugs de ligne actuels
     * @param  array<string, string>  $known  correspondances déjà retenues (fichier versionné)
     * @return array{
     *   renames: list<array{line_id: int, from: string, to: string}>,
     *   merged: list<array{line_id: int, from: string, to: string}>,
     *   collisions: list<array{from: string, wanted: string, to: string}>,
     * }
     */
    public static function plan(array $lines, array $merged, array $taken, array $known): array
    {
        $current = array_fill_keys($taken, true);
        // Pris : les slugs actuels (anciens slugs compris : jamais redonnés), les anciens et nouveaux slugs retenus.
        $used = $current;
        foreach ($known as $from => $to) {
            $used[$from] = true;
            $used[$to] = true;
        }

        $renames = [];
        $collisions = [];
        $newSlug = [];
        foreach ($lines as $line) {
            $from = $line['slug'];
            $kept = $known[$from] ?? null;
            if ($kept !== null && ! isset($current[$kept])) {
                $to = $kept; // déjà retenu : même slug à chaque passage (idempotent)
            } else {
                $wanted = self::stripped($from);
                $to = self::free($wanted, fn (string $s): bool => isset($used[$s]));
                if ($to !== $wanted) {
                    $collisions[] = ['from' => $from, 'wanted' => $wanted, 'to' => $to];
                }
            }
            $used[$to] = true;
            $newSlug[$from] = $to;
            $renames[] = ['line_id' => $line['id'], 'from' => $from, 'to' => $to];
        }

        $redirects = [];
        foreach ($merged as $m) {
            if (isset($known[$m['from']]) || isset($current[$m['from']])) {
                continue;
            }
            $redirects[$m['from']] = ['line_id' => $m['line_id'], 'from' => $m['from'], 'to' => $newSlug[$m['line_slug']] ?? $m['line_slug']];
        }
        ksort($redirects);

        return ['renames' => $renames, 'merged' => array_values($redirects), 'collisions' => $collisions];
    }
}
