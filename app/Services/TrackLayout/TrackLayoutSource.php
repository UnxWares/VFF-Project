<?php

namespace App\Services\TrackLayout;

use App\Models\Line;

/**
 * Une source du schéma des voies (`GET /api/v1/lines/{line}/track-layout`, contrat
 * agents-commun/contrat-schema-voies.md) : RINF, OpenStreetMap, données déjà dans VFF.
 *
 * Brancher une source : une classe qui implémente cette interface, déclarée dans TrackLayout::SOURCES (ordre de
 * préférence) ou étiquetée TrackLayout::TAG dans le conteneur. TrackLayout la lit après les sources préférées et
 * ne garde d'elle que ce qu'elles ne donnent pas (voir TrackLayout, règles de fusion).
 *
 * Ce que `part()` rend, PK en mètres dans la numérotation de la ligne (celle de SNCF Réseau, que VFF suit pour
 * les PK sourcés des gares ; ligne déroulée sur le tracé de référence de l'ère courante) :
 * - les objets au format du contrat, SANS `lane` (calculée par TrackLayout) et avec `source` = key() ;
 * - `covered` : par famille (`tracks`, `switches`, `signals`, `crossings`, `operational_points`, `speeds`,
 *   `gradients`), les portions de PK que la source décrit vraiment (pour une portion couverte, l'absence d'objet
 *   veut dire « il n'y en a pas », pas « on ne sait pas »). Une source plus loin dans l'ordre ne pose pas de voie
 *   sur une portion `tracks` couverte par une source préférée ;
 * - `source` : la fiche de provenance (key, label, licence, dataset, fetched_at, url), nulle sans donnée.
 *
 * Le réseau décrit est celui d'aujourd'hui : aucune voie inventée pour le passé. Une ligne que la source ne
 * connaît pas rend TrackLayoutPart::empty(), jamais une exception.
 */
interface TrackLayoutSource
{
    /** Clé de provenance des objets (`rinf`, `osm`, `vff`). */
    public function key(): string;

    /**
     * Objets de la ligne entre deux PK (mètres, bornes comprises ; null : sans borne). La source peut ignorer la
     * portion demandée : TrackLayout filtre de nouveau.
     */
    public function part(Line $line, ?int $fromM = null, ?int $toM = null): TrackLayoutPart;
}
