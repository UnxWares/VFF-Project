<?php

namespace App\Models;

use App\Enums\LevelCrossingGroup;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Ligne VFF qui porte le code SNCF du PN. `lines.code` n'est pas unique (aucune contrainte) :
     * quand plusieurs fiches partagent un code, la relation, la fiche ligne, les filtres et les
     * tuiles retiennent la même, voir ownerLineIdSql().
     *
     * @return BelongsTo<Line, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class, 'code_ligne', 'code')
            ->whereRaw('lines.id = '.self::ownerLineIdSql('lines.code'));
    }

    /**
     * Expression SQL de l'identifiant de la ligne qui porte un code SNCF : la fiche du réseau
     * principal (RFN) d'abord, puis la plus ancienne (plus petit identifiant). Choix déterministe,
     * pour qu'un PN ne soit ni dupliqué ni attribué à deux fiches.
     *
     * @param  string  $codeColumn  colonne ou expression du code (`pn.code_ligne`, `lines.code`)
     */
    public static function ownerLineIdSql(string $codeColumn): string
    {
        return "(SELECT o.id FROM lines o WHERE o.code = {$codeColumn} ORDER BY COALESCE(o.network = 'principal', false) DESC, o.id LIMIT 1)";
    }

    /**
     * PN d'une ligne : ceux de son code SNCF, si la ligne en est la fiche (ownerLineIdSql).
     * Une ligne sans code n'en a aucun.
     *
     * @param  Builder<LevelCrossing>  $query
     */
    public function scopeOfLine(Builder $query, int $lineId, ?string $code): void
    {
        if ($code === null || $code === '') {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->where('code_ligne', $code)
            ->whereRaw(self::ownerLineIdSql('sncf_level_crossings.code_ligne').' = ?', [$lineId]);
    }

    /**
     * Définition de la classe dans la langue demandée (langue courante par défaut). Null si la
     * classe est illisible ou hors de la liste officielle : jamais de clé de traduction brute.
     */
    public function classLabel(?string $lang = null): ?string
    {
        $class = $this->class;

        return $class !== null && in_array($class, LevelCrossingGroup::CLASSES, true)
            ? trans("level_crossings.classes.{$class}", [], $lang)
            : null;
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
