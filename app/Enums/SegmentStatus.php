<?php

namespace App\Enums;

enum SegmentStatus: string
{
    case InService = 'in_service';
    case SingleTrack = 'single_track';
    case ServiceTrack = 'service_track'; // voie de service / portuaire (roule, hors ligne RFN)
    case Construction = 'construction';
    case Abandoned = 'abandoned';
    case Dismantled = 'dismantled';
    case Razed = 'razed';

    /**
     * Mapping UNIQUE et autoritaire d'un libellé de statut SNCF Réseau
     * (dataset lignes-par-statut / sncf_line_geometries.libelle) vers notre enum.
     *
     * Source de vérité partagée par tous les importeurs (ImportSegmentsFromSncf,
     * SncfOpenDataImporter, LineClassifier) pour éliminer les divergences
     * historiques (« Fermée » → razed ici, → abandoned là).
     *
     * Les 13 libellés réels observés (1638 tronçons, audit 2026-05) :
     *   Exploitée (787) · Neutralisée (235) · Fermée (138) · Déclassée vendue (136)
     *   Transférée en voie de service (132) · Déclassée non vendue (64)
     *   Fermée non déposée (Plus utilisable) (45) · Retranchée (Plus utilisable) (28)
     *   Fermée et déposée (Plus utilisable) (27) · Fermée avec maintien en place (22)
     *   Fermée et mise à disposition de tiers (21) · Neutralisée Défense (2)
     *   S9A3 - Ligne en travaux (1)
     *
     * Principe de prudence (cf. audit, règle dismantled≠abandoned) : on ne
     * descend à `Dismantled` que sur preuve POSITIVE de dépose (« et déposée »).
     * Un déclassement juridique (« Déclassée vendue/non vendue ») ne prouve pas
     * la dépose physique → `Abandoned` ; l'escalade vers `Dismantled`/`Razed` se
     * fait ensuite via OSM (railway=razed) ou archeoferroviaire.
     */
    public static function fromSncfLibelle(?string $libelle): self
    {
        $s = mb_strtolower(trim((string) $libelle));

        return match (true) {
            $s === '' => self::InService,

            // En travaux / en service → exploité
            str_contains($s, 'exploit'), str_contains($s, 'travaux'), str_contains($s, 's9a3') => self::InService,

            // « Fermée non déposée » : rails en place → abandoned (AVANT le test 'déposée')
            str_contains($s, 'non déposée'), str_contains($s, 'non deposee') => self::Abandoned,

            // Preuve positive de dépose → dismantled
            str_contains($s, 'déposée'), str_contains($s, 'deposee'),
            str_contains($s, 'déferré'), str_contains($s, 'deferre') => self::Dismantled,

            // Voie de service / portuaire : roule encore (fret/manœuvres) mais hors
            // ligne RFN exploitée → statut dédié (ni « en service » ni « abandonné »).
            str_contains($s, 'voie de service'), str_contains($s, 'transf') => self::ServiceTrack,

            // Suspendue / retirée du réseau exploité, voie potentiellement en place
            str_contains($s, 'neutralis') => self::Abandoned,
            str_contains($s, 'retranch') => self::Abandoned,

            // Déclassement juridique (≠ dépose physique) → abandoned, escalade par preuve
            str_contains($s, 'déclass'), str_contains($s, 'declass') => self::Abandoned,

            // Fermeture administrative (rails souvent en place)
            str_contains($s, 'ferm') => self::Abandoned,

            default => self::InService,
        };
    }

    /**
     * Le segment porte-t-il (potentiellement) du trafic à cette ère ?
     * Utilisé pour scoper les stats « réseau exploité » et la dérivation du
     * traffic_type (un segment non actif ne doit pas être typé voyageurs/fret).
     */
    public function isActive(): bool
    {
        return $this === self::InService || $this === self::SingleTrack;
    }
}
