<?php

namespace App\Services\Accidents;

use App\Enums\AccidentLocationPrecision;
use App\Services\PlaceNames;
use Illuminate\Support\Facades\DB;

/**
 * Position, commune et département d'un accident, du plus sûr au plus vague :
 *
 *   1. coordonnées de la source (Wikidata, infobox Wikipédia) : commune dont le contour les
 *      contient (contours IGN ADMIN EXPRESS / Etalab), à défaut la plus proche à moins de 15 km
 *      (accident en tunnel sous la Manche, point posé en mer) ;
 *   2. passage à niveau cité (« PN n° 25 ») : point du jeu SNCF Réseau, dans la commune citée
 *      ou à moins de 3 km de son contour ;
 *   3. gare citée (« en gare de… ») : gare VFF de ce nom dans la commune ou à moins de 3 km ;
 *   4. commune seule : son point de référence (table communes).
 *
 * La commune citée par un texte est cherchée par son nom normalisé, dans le département quand il
 * est connu ; un nom ambigu (plusieurs communes, aucun département) n'est jamais tranché au
 * hasard : la position reste inconnue et le cas est signalé.
 */
class AccidentLocator
{
    /** Distance au-delà de laquelle une coordonnée hors des contours n'est pas en France. */
    private const OFFSHORE_MAX_M = 15000;

    public function locate(AccidentCandidate $c): void
    {
        if ($c->lat !== null && $c->lng !== null) {
            // Position déjà qualifiée par la source (lieu cité qui a ses coordonnées) ou exacte.
            $this->fromCoordinates($c, keepSource: $c->locationPrecision !== null);

            return;
        }
        $commune = $this->resolveCommune($c);
        if ($commune === null) {
            // Lieu-dit, gare ou triage qui n'est pas une commune (« Laroche-Migennes », « Sibelin »).
            $names = array_filter([[$c->stationHint, $c->departmentCode], [$c->communeName, $c->departmentCode], ...$c->placeHints], fn ($n) => $n[0] !== null);
            foreach ($names as [$name, $department]) {
                $station = $this->stationByName($name, $department ?? $c->departmentCode);
                if ($station !== null) {
                    $this->setPoint($c, (float) $station->lat, (float) $station->lng, AccidentLocationPrecision::Station, 'station');
                    $this->fromCoordinates($c, keepSource: true);

                    return;
                }
            }
            if ($c->communeName !== null || $c->departmentCode !== null) {
                $c->note('commune_not_found', trim(($c->communeName ?? '?').' ('.($c->departmentCode ?? '?').')'));
            }

            return;
        }
        $c->communeInsee = $commune->code_insee;
        $c->communeName = $commune->nom;
        $c->departmentCode = $commune->departement;

        if ($c->levelCrossing !== null && ($pn = $this->levelCrossing($c->levelCrossing, $commune->code_insee)) !== null) {
            $this->setPoint($c, (float) $pn->lat, (float) $pn->lon, AccidentLocationPrecision::LevelCrossing, 'sncf-pn');
            $c->lineCodes[] = (string) $pn->code_ligne;
            $c->levelCrossingPk = $pn->pk_m !== null ? (int) $pn->pk_m : null;

            return;
        }
        if ($c->levelCrossing !== null) {
            $c->note('level_crossing_not_found', "PN {$c->levelCrossing} ({$commune->nom})");
        }
        if ($c->stationHint !== null && ($st = $this->station($c->stationHint, $commune->code_insee)) !== null) {
            $this->setPoint($c, (float) $st->lat, (float) $st->lng, AccidentLocationPrecision::Station, 'station');

            return;
        }
        $this->setPoint($c, (float) $commune->lat, (float) $commune->lng, AccidentLocationPrecision::Commune, 'commune');
        $c->note('location_commune_only', $commune->nom);
    }

    /** Commune et département d'un point ; $keepSource : point déjà qualifié (gare retrouvée). */
    private function fromCoordinates(AccidentCandidate $c, bool $keepSource = false): void
    {
        $commune = $this->communeAt((float) $c->lat, (float) $c->lng);
        if (! $keepSource) {
            $c->locationPrecision = AccidentLocationPrecision::Exact;
            $c->locationSource ??= 'wikidata';
        }
        if ($commune === null) {
            $c->note('outside_france', sprintf('%.5f, %.5f', $c->lat, $c->lng));

            return;
        }
        $c->communeInsee = $commune->code_insee;
        $c->communeName = $commune->nom;
        $c->departmentCode = $commune->departement;
        if ((int) $commune->distance_m > 0) {
            $c->offshoreM = (int) round($commune->distance_m);
            $c->note('location_offshore', "{$commune->nom}, ".round($commune->distance_m / 1000, 1).' km');
        }
    }

    private function setPoint(AccidentCandidate $c, float $lat, float $lng, AccidentLocationPrecision $precision, string $source): void
    {
        $c->lat = $lat;
        $c->lng = $lng;
        $c->locationPrecision = $precision;
        $c->locationSource = $source;
    }

    /** Commune dont le contour contient le point, sinon la plus proche à moins de 15 km. */
    private function communeAt(float $lat, float $lng): ?object
    {
        return DB::selectOne(
            'SELECT code_insee, nom, departement,
                    ST_Distance(geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) AS distance_m
             FROM commune_contours
             WHERE geom && ST_Expand(ST_SetSRID(ST_MakePoint(?, ?), 4326), 0.25)
               AND ST_DWithin(geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)
             ORDER BY distance_m, code_insee LIMIT 1',
            [$lng, $lat, $lng, $lat, $lng, $lat, self::OFFSHORE_MAX_M],
        );
    }

    /**
     * Commune désignée par le code INSEE (Wikidata), puis par chaque nom proposé (intitulé, lien
     * du rapport) avec son département. Avec un département, un nom introuvable tel quel est
     * raccourci par la fin (« Marseille St-Charles » → « Marseille ») ; sans département, le nom
     * doit désigner une seule commune de France.
     */
    private function resolveCommune(AccidentCandidate $c): ?object
    {
        $select = 'SELECT c.code_insee, c.nom, c.departement, ST_Y(c.geom) AS lat, ST_X(c.geom) AS lng FROM communes c';
        if ($c->communeInsee !== null) {
            $row = DB::selectOne("{$select} WHERE c.code_insee = ? ORDER BY (c.type = 'commune-actuelle') DESC LIMIT 1", [$c->communeInsee]);
            if ($row !== null) {
                return $row;
            }
        }
        $names = $c->communeName !== null ? [[$c->communeName, $c->departmentCode], ...$c->placeHints] : $c->placeHints;
        foreach ($names as [$name, $department]) {
            $words = explode(' ', PlaceNames::normalize($name));
            while ($words !== []) {
                $rows = DB::select(
                    "{$select} WHERE c.nom_norm = ? AND (?::text IS NULL OR c.departement = ?)
                     ORDER BY (c.type = 'commune-actuelle') DESC, c.population DESC NULLS LAST",
                    [implode(' ', $words), $department, $department],
                );
                $codes = array_values(array_unique(array_map(fn ($r) => $r->code_insee, $rows)));
                if (count($codes) === 1) {
                    return $rows[0];
                }
                if (count($codes) > 1) {
                    $c->note('commune_ambiguous', $name.' : '.implode(', ', array_map(fn ($r) => "{$r->nom} ({$r->departement})", array_slice($rows, 0, 5))));
                    if ($c->stopAtAmbiguousPlace) {
                        // Le lieu principal est ambigu : les lieux cités après lui ne le remplacent pas.
                        return null;
                    }
                    break;
                }
                if ($department === null) {
                    break;
                }
                array_pop($words);
            }
        }

        return null;
    }

    /**
     * Gare désignée par son nom seul (lieu-dit, triage), dans le département quand il est connu :
     * nom identique d'abord, sinon nom qui contient tous les mots cités ; une seule gare, jamais
     * un choix au hasard.
     */
    private function stationByName(?string $hint, ?string $department): ?object
    {
        $tokens = PlaceNames::significant($hint);
        if ($tokens === []) {
            return null;
        }
        $rows = DB::select(
            'SELECT s.name, ST_Y(s.geom) AS lat, ST_X(s.geom) AS lng FROM stations s
             WHERE (?::text IS NULL OR s.department_code = ?)',
            [$department, $department],
        );
        $exact = array_values(array_filter($rows, fn ($r) => PlaceNames::normalize($r->name) === PlaceNames::normalize($hint)));
        if (count($exact) === 1) {
            return $exact[0];
        }
        $superset = array_values(array_filter($rows, fn ($r) => array_diff($tokens, PlaceNames::significant($r->name)) === []));

        return count($superset) === 1 ? $superset[0] : null;
    }

    /** Passage à niveau « PN n » dans la commune ou à moins de 3 km de son contour. */
    private function levelCrossing(int $number, string $insee): ?object
    {
        return DB::selectOne(
            'SELECT pn.lat, pn.lon, pn.code_ligne, pn.pk_m
             FROM sncf_level_crossings pn
             JOIN commune_contours cc ON cc.code_insee = ?
             WHERE pn.libelle = ? AND pn.geom && ST_Expand(cc.geom, 0.05)
               AND ST_DWithin(pn.geom::geography, cc.geom::geography, 3000)
             ORDER BY ST_Distance(pn.geom::geography, cc.geom::geography) LIMIT 1',
            [$insee, "PN {$number}"],
        );
    }

    /** Gare dont le nom contient tous les mots de la gare citée, dans la commune ou à moins de 3 km. */
    private function station(string $hint, string $insee): ?object
    {
        $tokens = PlaceNames::significant($hint);
        if ($tokens === []) {
            return null;
        }
        $rows = DB::select(
            'SELECT s.name, ST_Y(s.geom) AS lat, ST_X(s.geom) AS lng
             FROM stations s JOIN commune_contours cc ON cc.code_insee = ?
             WHERE s.geom && ST_Expand(cc.geom, 0.05) AND ST_DWithin(s.geom::geography, cc.geom::geography, 3000)',
            [$insee],
        );
        $best = null;
        foreach ($rows as $r) {
            $name = PlaceNames::significant($r->name);
            if (array_diff($tokens, $name) === [] && ($best === null || mb_strlen($r->name) < mb_strlen($best->name))) {
                $best = $r;
            }
        }

        return $best;
    }
}
