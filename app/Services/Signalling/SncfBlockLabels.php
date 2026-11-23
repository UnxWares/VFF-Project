<?php

namespace App\Services\Signalling;

/**
 * Libellés du jeu SNCF Réseau `mode-de-cantonnement-des-lignes` ramenés aux codes de cantonnement
 * de VFF (`line_signalling.signalling` et `signalling_systems`).
 *
 * Une même tranche peut porter deux systèmes installés ensemble : SNCF Réseau les publie alors dans
 * un seul libellé séparé par une barre oblique (« European train control system 2/Transmission
 * voie-machine 300 » sur la LGV Bretagne - Pays de la Loire et la LGV SEA, « .../Transmission
 * voie-machine 430 » sur la LGV Est). Chaque partie est lue séparément et toutes sont gardées, dans
 * l'ordre du libellé : la première devient le code principal (`signalling`). Avant le 19/11/2026,
 * seul l'ETCS niveau 2 était gardé et la TVM disparaissait.
 *
 * Le contrôle de vitesse (KVB) n'apparaît jamais ici : c'est un autre jeu, et une autre notion.
 */
final class SncfBlockLabels
{
    /**
     * Code d'une section que SNCF Réseau déclare « Sans cantonnement » (ligne sans circulation régulière,
     * trains menés à vue ou par une autre règle) : une valeur à part entière, qui se distingue d'un trou des
     * données. Ce n'est ni un cantonnement ni une signalisation : aucune famille de la carte, aucun KVB à lui seul.
     */
    public const NONE = 'NONE';

    /** Libellé SNCF d'un seul système → code VFF ; null : libellé connu, sans cantonnement à garder. */
    private const MAPPING = [
        'Block automatique lumineux' => 'BAL',
        'Block automatique lumineux de voie banalisée' => 'BAL-VB',
        'Block automatique à permissivité restreinte de double voie' => 'BAPR',
        'Block automatique à permissivité restreinte de voie banalisée' => 'BAPR-VB',
        'Block automatique Tram 3 avec annonce des signaux' => 'BAL-Tram',
        'Block automatique Tram sans annonce des signaux' => 'BAL-Tram',
        'Block manuel de double voie de type Alsace-Moselle uniformisé' => 'BM',
        'Block manuel de double voie de type Lartigue uniformisé' => 'BM',
        'Block manuel de double voie de type Nantes-Bordeaux' => 'BM',
        'Block manuel de double voie de type SNCF' => 'BM',
        'Block manuel de double voie de type Sud-Est uniformisé S' => 'BM',
        'Block manuel à circuit de voie' => 'BM',
        'Block manuel de voie unique de type SNCF' => 'BMVU',
        'Block manuel de voie unique de type Sud-Est' => 'BMVU',
        'Cantonnement téléphonique assisté par informatique' => 'CAPI',
        'Cantonnement téléphonique de double voie' => 'CT',
        'Cantonnement téléphonique de voie unique' => 'CT-VU',
        'European train control system 1' => 'ETCS_L1',
        'European train control system 2' => 'ETCS_L2',
        'Transmission voie-machine 300' => 'TVM-300',
        'Transmission voie-machine 430' => 'TVM-430',
        'Marche Tramway (circulation à vue)' => null,
        'Sans cantonnement' => self::NONE,
        '_Autre' => null,
    ];

    /**
     * Codes VFF d'un libellé, dans son ordre, sans doublon. Liste vide pour un libellé sans
     * cantonnement à garder (« Marche Tramway », « _Autre » : le système n'est pas dit), null pour un libellé inconnu (ou
     * dont une partie est inconnue : rien n'est deviné).
     *
     * @return list<string>|null
     */
    public static function systems(string $label): ?array
    {
        $codes = [];
        foreach (explode('/', $label) as $part) {
            $part = trim($part);
            if (! array_key_exists($part, self::MAPPING)) {
                return null;
            }
            $code = self::MAPPING[$part];
            if ($code !== null && ! in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * Contrôle de vitesse assuré par les systèmes de la tranche : l'ETCS fait partie de l'ERTMS, la
     * TVM contrôle elle-même la vitesse. Le KVB des lignes classiques est ajouté à part.
     *
     * @param  list<string>  $systems
     * @return list<string>
     */
    public static function protection(array $systems): array
    {
        $out = [];
        foreach ($systems as $code) {
            $p = match (true) {
                $code === 'ETCS_L1' => 'ERTMS_L1',
                $code === 'ETCS_L2' => 'ERTMS_L2',
                str_starts_with($code, 'TVM') => $code,
                default => null,
            };
            if ($p !== null && ! in_array($p, $out, true)) {
                $out[] = $p;
            }
        }

        return $out;
    }

    /** Signalisation en cabine (TVM, ETCS) : le KVB n'y est jamais ajouté. */
    public static function isCab(string $code): bool
    {
        return str_starts_with($code, 'TVM') || str_starts_with($code, 'ETCS');
    }
}
