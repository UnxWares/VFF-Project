<?php

namespace App\Services\StationPositioning;

/**
 * Point d'appui : PK de la liste (m) et abscisse du point sur l'axe de la ligne (m).
 */
final readonly class PkAnchor
{
    public function __construct(
        public int $pk,
        public float $measure,
        public AnchorKind $kind,
        public string $label,
    ) {}

    /** Décalage de l'origine des PK pour un sens de parcours (abscisse = sens × PK + décalage). */
    public function offset(int $direction): float
    {
        return $this->measure - $direction * $this->pk;
    }
}
