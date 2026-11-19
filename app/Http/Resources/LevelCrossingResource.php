<?php

namespace App\Http\Resources;

use App\Models\LevelCrossing;
use App\Services\Api\Provenance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Passage à niveau du réseau ferré national, tel que publié par SNCF Réseau (jeu
 * `liste-des-passages-a-niveau`, ODbL : mention « SNCF Réseau » et partage à l'identique des bases
 * dérivées). État à la date de publication du jeu, pas une donnée historique.
 *
 * @mixin LevelCrossing
 */
class LevelCrossingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $class = $this->resource->class;
        $group = $this->resource->group;

        return [
            /**
             * Identifiant de l'installation fixe dans les bases de SNCF Réseau (stable d'une publication à l'autre).
             *
             * @example 30000010436
             */
            'id' => $this->id_if,
            /**
             * Libellé SNCF Réseau, en général « PN » suivi du numéro du passage sur la ligne.
             *
             * @example PN 32
             */
            'label' => $this->libelle,
            /**
             * Classe SNCF Réseau (10 à 46, 0 pour un PN secondaire), null si la source ne la donne pas. Définitions :
             * 10 à 16 gardés (agent, barrières manœuvrées sur place ou à distance), 17 à 19 barrières ou demi-barrières
             * automatiques (SAL 2, SAL 2B, SAL 4), 20 sans barrières ni signalisation lumineuse, 21 sans barrières avec
             * feux (SAL 0), 31 et 32 piétons, 41 à 46 privés.
             *
             * @example 17
             */
            'class' => $class,
            /**
             * Définition de la classe, en français (texte de VFF d'après la description du jeu).
             *
             * @example Public pour voitures, barrières ou demi-barrières automatiques, signalisation lumineuse SAL 2 ou SAL 2B
             */
            'class_label' => $class === null ? null : __("level_crossings.classes.{$class}", [], 'fr'),
            /**
             * Groupe de la classe : `automatic` (17 à 19), `guarded` (10 à 16), `no_barrier` (20, 21), `pedestrian`
             * (31, 32), `private` (41 à 46), `secondary` (0 : passage déjà compté sur une autre ligne, voir `crossed_way`).
             *
             * @example automatic
             */
            'group' => $group?->value,
            /**
             * Point kilométrique officiel, en mètres (numérotation de SNCF Réseau), null s'il est inconnu.
             *
             * @example 112866
             */
            'pk_m' => $this->pk_m,
            /**
             * Point kilométrique en notation ferroviaire `km+mmm`.
             *
             * @example 112+866
             */
            'pk_label' => LevelCrossing::pkLabel($this->pk_m),
            /**
             * Commune, telle que l'écrit SNCF Réseau (sans le code de département), null si elle manque.
             *
             * @example Courthiézy
             */
            'commune' => $this->commune,
            /**
             * Voie franchie, libellé brut de SNCF Réseau : `VC` voie communale, `CD 106-0` route départementale, `CR`
             * chemin rural, `Autre`… Pour un PN secondaire : le libellé, la ligne et le PK du PN qui porte la classe.
             *
             * @example VC
             */
            'crossed_way' => $this->obstacle,
            /**
             * Ligne VFF qui porte ce code SNCF (`slug`, `code`, `name`), null si le code n'a pas de fiche.
             *
             * @var array{slug: string, code: string|null, name: string}|null
             */
            'line' => $this->line ? ['slug' => $this->line->slug, 'code' => $this->line->code, 'name' => $this->line->name] : null,
            /**
             * Code de ligne SNCF Réseau à 6 chiffres.
             *
             * @example 070000
             */
            'line_code' => $this->code_ligne,
            /**
             * Latitude WGS 84, au centre du croisement de la route et de la voie (à moins d'un mètre pour plus de 90 % des PN selon SNCF Réseau).
             *
             * @example 49.062227
             */
            'lat' => round($this->lat, 6),
            /**
             * Longitude WGS 84.
             *
             * @example 3.602119
             */
            'lng' => round($this->lon, 6),
            /** Fournisseur et licence de la donnée (SNCF Réseau, ODbL). */
            'provenance' => app(Provenance::class)->for('sncf_level_crossings', 'sncf'),
        ];
    }
}
