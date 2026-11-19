<?php

namespace App\Models;

use App\Enums\LevelCrossingGroup;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Passage à niveau du réseau ferré national (jeu SNCF Réseau `liste-des-passages-a-niveau`,
 * ODbL), importé par vff:import-sncf-level-crossings dans `sncf_level_crossings`. Lecture seule :
 * l'état est celui de la publication du jeu, pas une donnée historique.
 *
 * @property int $id
 * @property string $id_if
 * @property string|null $libelle
 * @property string|null $mnemo
 * @property string|null $obstacle
 * @property string $code_ligne
 * @property int|null $rg_troncon
 * @property int|null $pk_m
 * @property string|null $commune
 * @property float $lat
 * @property float $lon
 * @property-read int|null $class
 * @property-read LevelCrossingGroup|null $group
 * @property-read Line|null $line
 */
class LevelCrossing extends Model
{
    protected $table = 'sncf_level_crossings';

    /** Données importées : jamais écrites par l'application. */
    protected $guarded = ['*'];

    /** La colonne PostGIS `geom` n'est jamais lue : `lat` et `lon` suffisent. */
    protected $hidden = ['geom'];

    protected function casts(): array
    {
        return [
            'rg_troncon' => 'integer',
            'pk_m' => 'integer',
            'lat' => 'float',
            'lon' => 'float',
        ];
    }

    /** @return BelongsTo<Line, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class, 'code_ligne', 'code');
    }

    /**
     * Classe SNCF Réseau (« CLASSE 17 » → 17), null si le libellé est illisible.
     *
     * @return Attribute<int|null, never>
     */
    protected function class(): Attribute
    {
        return Attribute::get(fn (): ?int => LevelCrossingGroup::parseClass($this->mnemo));
    }

    /** @return Attribute<LevelCrossingGroup|null, never> */
    protected function group(): Attribute
    {
        return Attribute::get(function (): ?LevelCrossingGroup {
            $class = LevelCrossingGroup::parseClass($this->mnemo);

            return $class === null ? null : LevelCrossingGroup::fromClass($class);
        });
    }

    /** PK en notation ferroviaire (19663 → « 19+663 », -771 → « -0+771 »), null si inconnu. */
    public static function pkLabel(?int $meters): ?string
    {
        if ($meters === null) {
            return null;
        }
        $abs = abs($meters);

        return ($meters < 0 ? '-' : '').sprintf('%d+%03d', intdiv($abs, 1000), $abs % 1000);
    }
}
