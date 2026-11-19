<?php

namespace App\Services;

use App\Console\Commands\AttachStations;
use App\Support\StationNames;

/**
 * Terminus nommés relus à la main (database/data/station_terminus_overrides.php), lus par
 * vff:attach-stations : la décision relue l'emporte sur la règle des terminus nommés
 * (AttachStations::attachNamedTermini) pour la même ligne et la même extrémité.
 *
 *   - « attach » : la gare (slug) est rattachée avec la méthode terminus_reviewed, même loin des
 *     bouts du tracé ou dans une ville à plusieurs gares, parce qu'une source la nomme ;
 *   - « none »   : rien n'est rattaché pour cette extrémité.
 *
 * Une décision mal formée n'est jamais appliquée (validate) : la commande la signale.
 */
final class StationTerminusOverrides
{
    public const DECISIONS = ['attach', 'none'];

    public const METHOD = 'terminus_reviewed';

    /** @var list<array<string, mixed>> */
    private array $entries;

    /** @param  list<array<string, mixed>>|null  $entries  défaut : database/data/station_terminus_overrides.php */
    public function __construct(?array $entries = null)
    {
        $this->entries = $entries ?? (is_file($f = database_path('data/station_terminus_overrides.php')) ? require $f : []);
    }

    /**
     * Erreurs de forme d'une décision (vide si valide). $lineName : nom de la ligne visée (null si
     * introuvable) ; l'extrémité doit être l'une de celles que donne ce nom.
     *
     * @param  array<string, mixed>  $e
     * @return list<string>
     */
    public static function validate(array $e, ?string $lineName = null): array
    {
        $errors = [];
        foreach (['line', 'terminus', 'reason'] as $k) {
            if (! is_string($e[$k] ?? null) || trim($e[$k]) === '') {
                $errors[] = "{$k} manquant";
            }
        }
        $decision = $e['decision'] ?? null;
        if (! in_array($decision, self::DECISIONS, true)) {
            $errors[] = 'decision : attach ou none';
        }
        $url = $e['source_url'] ?? null;
        if ($url !== null && (! is_string($url) || ! str_starts_with($url, 'https://'))) {
            $errors[] = 'source_url : lien https ou null';
        }
        if ($decision === 'attach' && (! is_string($e['station'] ?? null) || $e['station'] === '' || $url === null)) {
            $errors[] = 'attach : station (slug) et source_url';
        }
        if ($decision === 'none' && isset($e['station'])) {
            $errors[] = 'none : pas de station';
        }
        if (is_string($e['reason'] ?? null) && (str_contains($e['reason'], "\u{2014}") || str_contains($e['reason'], "\u{2013}") || str_contains($e['reason'], '--'))) {
            $errors[] = 'reason : tiret long';
        }
        if ($lineName !== null && is_string($e['terminus'] ?? null)
            && ! in_array(self::key($e['terminus']), AttachStations::terminiFromName($lineName), true)) {
            $errors[] = "terminus : « {$e['terminus']} » n'est pas une extrémité de « {$lineName} »";
        }

        return $errors;
    }

    /** Extrémité normalisée, comparable à AttachStations::terminiFromName. */
    public static function key(string $terminus): string
    {
        return (string) StationNames::normalize($terminus);
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->entries;
    }

    /**
     * Provenance gardée dans station_lines.source_ref (160 caractères au plus).
     *
     * @param  array<string, mixed>  $e
     */
    public static function sourceRef(array $e): string
    {
        return mb_substr('relu:'.$e['source_url'], 0, 160);
    }
}
