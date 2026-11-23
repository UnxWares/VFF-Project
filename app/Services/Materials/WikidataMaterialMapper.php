<?php

namespace App\Services\Materials;

use App\Enums\MaterialType;
use App\Models\Material;
use App\Models\Media;
use App\Services\Media\CommonsMetadata;
use App\Support\MaterialDateCorrections;
use App\Support\MaterialDateSupplements;
use App\Support\MaterialPhotoChoices;
use App\Support\MaterialPhotoExclusions;
use App\Support\MaterialTypeOverrides;

/**
 * Traduit un élément Wikidata (réduit par WikidataMaterialSource::trimEntity) en fiche de
 * matériel VFF, sans rien estimer : un champ que Wikidata ne renseigne pas reste nul, et chaque
 * champ rempli garde sa provenance (élément et propriété Wikidata, fichier Commons).
 *
 * Règles de prudence :
 *   - années de service lues seulement à la précision « année » ou mieux (une décennie
 *     n'est pas une année) ; un retrait antérieur à la mise en service est écarté ;
 *   - nombre construit retenu seulement s'il est univoque ;
 *   - vitesse et puissance : la plus grande valeur déclarée (modes d'alimentation), convertie en
 *     km/h et kW, la valeur d'origine gardée dans la provenance quand l'unité diffère ;
 *   - catégorie « bimode » : seulement si Wikidata range la série dans une classe bimode, ou si
 *     l'infobox de son article Wikipédia l'annonce (`Type`) pour une automotrice ou un autorail ;
 *   - état d'utilisation : « en service » seulement si Wikidata le dit (P5817) ou si l'infobox
 *     donne un effectif daté d'une année récente ; rien sinon.
 */
final readonly class WikidataMaterialMapper
{
    public const SKIP_NO_ENTITY = 'absent_de_wikidata';

    public const SKIP_FOREIGN = 'exploitants_hors_de_france';

    public const SKIP_METRO = 'materiel_de_metro';

    public const SKIP_NO_TYPE = 'categorie_inconnue';

    public const SKIP_NO_NAME = 'sans_nom';

    public function __construct(private WikidataMaterialRules $rules) {}

    /**
     * @param  array<string, mixed>|null  $entity  élément réduit
     * @param  list<string>  $ancestors  classes consultées par les règles dont la série descend
     * @param  array<string, array{fr: ?string, en: ?string}|null>  $labels  libellés des éléments cités
     * @param  array<string, int|null>  $gaugeWidths  largeur des écartements, en mm
     * @param  array<string, array<string, mixed>|null>  $images  métadonnées Commons par nom de fichier
     * @param  list<string>  $categories  catégories de l'article Wikipédia, repli quand Wikidata ne range pas la série
     * @param  array{title: string, revid: int, parameters: array<string, string>, read_on?: string, shared?: bool}|null  $infobox  infobox de l'article Wikipédia en français (`shared` : article commun à plusieurs séries, dont l'effectif est celui de la famille)
     * @param  array{source: string, ref: string}|null  $attestation  source qui atteste la circulation en France d'une série que Wikidata ne rattache pas à la France
     * @param  array<string, list<string>|null>  $categoryFiles  premiers fichiers des catégories Commons (P373), repli d'image (imageCandidates())
     * @return array<string, mixed>|string attributs de la fiche, ou motif d'exclusion (constantes SKIP_*)
     */
    public function map(string $qid, ?array $entity, array $ancestors, array $labels, array $gaugeWidths, array $images, array $categories = [], ?array $infobox = null, ?array $attestation = null, array $categoryFiles = []): array|string
    {
        if ($entity === null) {
            return self::SKIP_NO_ENTITY;
        }
        $claims = $entity['claims'] ?? [];
        $operatorIds = array_values(array_unique(array_filter(array_map(fn (array $c): mixed => is_string($c['v']) ? $c['v'] : null, $claims['P137'] ?? []))));
        if ($attestation === null && $this->rules->onlyForeignOperators($operatorIds)) {
            return self::SKIP_FOREIGN;
        }
        if ($this->rules->excludedRoot($ancestors) !== null) {
            return self::SKIP_METRO;
        }
        $ref = fn (string $what): array => ['source' => 'wikidata', 'ref' => "{$qid}#{$what}"];
        $type = $this->rules->typeFor($ancestors);
        $typeProvenance = $type !== null ? $ref('P279:'.$type[1]) : null;
        if ($type === null && ($fallback = $this->rules->typeFromCategories($categories)) !== null) {
            if ($fallback[0] === null) {
                return self::SKIP_METRO;
            }
            $type = $fallback;
            $typeProvenance = ['source' => 'wikipedia', 'ref' => 'Catégorie:'.$fallback[1]];
        }
        if ($type === null && ($override = MaterialTypeOverrides::for($qid)) !== null) {
            $type = [$override[0], $override[1]];
            $typeProvenance = ['source' => 'wikipedia', 'ref' => $override[1]];
        }
        if ($type === null || $typeProvenance === null) {
            return self::SKIP_NO_TYPE;
        }
        // Bimode d'après l'article : Wikidata range le Régiolis en « bimode », pas toujours ses cousins.
        if (in_array($type[0], [MaterialType::Automotrice, MaterialType::Autorail], true) && $infobox !== null && WikipediaMaterialInfobox::saysBimode($infobox['parameters'])) {
            $type = [MaterialType::Bimode, $type[1]];
            $typeProvenance = ['source' => 'wikipedia', 'ref' => $infobox['title'].'#infobox:Type', 'revid' => $infobox['revid']];
        }
        [$name, $nameRef] = $this->name($entity);
        if ($name === null && ($handName = MaterialTypeOverrides::nameFor($qid)) !== null) {
            [$name, $nameRef] = [$handName, 'sitelink:dewiki'];
        }
        if ($name === null) {
            return self::SKIP_NO_NAME;
        }

        $label = fn (string $id): string => self::plain($labels[$id]['fr'] ?? $labels[$id]['en'] ?? null) ?? $id;
        $provenance = ['name' => $ref($nameRef), 'type' => $typeProvenance];

        $builders = [];
        foreach ($claims['P176'] ?? [] as $c) {
            if (is_string($c['v'])) {
                $builders[] = ['name' => $label($c['v']), 'wikidata_id' => $c['v'], 'units' => self::firstInt($c['q']['P1092'] ?? [])];
            }
        }
        $operators = [];
        foreach ($claims['P137'] ?? [] as $c) {
            if (is_string($c['v'])) {
                $operators[] = [
                    'name' => $label($c['v']),
                    'wikidata_id' => $c['v'],
                    'from_year' => self::year($c['q']['P580'][0] ?? null),
                    'to_year' => self::year($c['q']['P582'][0] ?? null),
                    'units' => self::firstInt($c['q']['P1114'] ?? []),
                ];
            }
        }

        $from = self::extremeYear($claims['P729'] ?? [], 'min');
        // Fin de service : P730 (retrait), à défaut P2669 (date de fin).
        $toProperty = ($claims['P730'] ?? []) !== [] ? 'P730' : 'P2669';
        $to = self::extremeYear($claims[$toProperty] ?? [], 'max');
        if ($from !== null && $to !== null && $to < $from) {
            $to = null;
        }
        // Date contredite par une source lue (database/data/material_date_corrections.php).
        $corrected = [];
        foreach (['in_service_from' => $from, 'in_service_to' => $to] as $field => $year) {
            $correction = MaterialDateCorrections::for($qid, $field, $year);
            if ($correction !== null) {
                $corrected[$field] = ['source' => 'wikipedia', 'ref' => $correction['source'], 'quote' => $correction['quote'], 'replaces' => $year];
                if ($field === 'in_service_from') {
                    $from = $correction['year'];
                } else {
                    $to = $correction['year'];
                }
            }
        }

        // Valeurs que Wikidata ne donne pas : relevé à la main d'une source citée, puis infobox de l'article (jamais celle
        // d'un article commun à plusieurs séries). Une valeur de Wikidata n'est jamais remplacée.
        $units = self::units($claims['P1092'] ?? []);
        $builderText = ($named = array_filter(array_unique(array_column($builders, 'name')), Material::isReadableName(...))) !== []
            ? mb_strimwidth(implode(', ', $named), 0, 255, '…') : null;
        $filled = [];
        $own = $infobox !== null && ! ($infobox['shared'] ?? false) ? $infobox : null;
        $years = $own !== null ? WikipediaMaterialInfobox::serviceYears($own['parameters']) : [];
        $unitsRead = $own !== null ? WikipediaMaterialInfobox::unitsBuilt($own['parameters']) : null;
        $builderRead = $infobox !== null ? WikipediaMaterialInfobox::builder($infobox['parameters']) : null;
        $candidates = [
            'in_service_from' => isset($years['from']) ? [$years['from']['year'], $years['from']] : null,
            'in_service_to' => isset($years['to']) ? [$years['to']['year'], $years['to']] : null,
            'units_built' => $unitsRead !== null ? [$unitsRead['value'], $unitsRead + ['basis' => 'nombre d\'engins construits']] : null,
            'builder' => $builderRead !== null ? [$builderRead, ['field' => 'constructeur', 'quote' => $builderRead, 'basis' => 'constructeur']] : null,
        ];
        $values = ['in_service_from' => $from, 'in_service_to' => $to, 'units_built' => $units, 'builder' => $builderText];
        foreach ($values as $property => $current) {
            if ($current !== null) {
                continue;
            }
            if (($row = MaterialDateSupplements::for($qid, $property)) !== null) {
                $values[$property] = $row['value'];
                $filled[$property] = array_filter(['source' => 'wikipedia', 'ref' => $row['source'], 'revid' => $row['revid'], 'quote' => $row['quote'], 'basis' => $row['basis'], 'lang' => $row['lang'] ?? null], fn (mixed $v): bool => $v !== null);
            } elseif ($candidates[$property] !== null && $infobox !== null) {
                [$value, $entry] = $candidates[$property];
                $values[$property] = $value;
                $filled[$property] = ['source' => 'wikipedia', 'ref' => $infobox['title'].'#infobox:'.$entry['field'], 'revid' => $infobox['revid'], 'quote' => $entry['quote'], 'basis' => $entry['basis']];
            }
        }
        $from = is_int($values['in_service_from']) ? $values['in_service_from'] : null;
        $to = is_int($values['in_service_to']) ? $values['in_service_to'] : null;
        $units = is_int($values['units_built']) ? $values['units_built'] : null;
        $builderText = is_string($values['builder']) ? $values['builder'] : null;
        if ($from !== null && $to !== null && $to < $from) {
            $to = null;
            unset($filled['in_service_to']);
        }

        [$speed, $speedOrigin] = $this->maxConverted($claims['P2052'] ?? [], fn (float $a, string $u): ?int => $this->rules->speedKmh($a, $u), 'Q180154');
        [$power, $powerOrigin] = $this->maxConverted($claims['P2109'] ?? [], fn (float $a, string $u): ?int => $this->rules->powerKw($a, $u), 'Q3320608');

        $electrification = [];
        foreach ($claims['P930'] ?? [] as $c) {
            if (is_string($c['v'])) {
                $electrification[] = ['label' => $label($c['v']), 'wikidata_id' => $c['v']];
            }
        }
        $gauges = [];
        foreach ($claims['P1064'] ?? [] as $c) {
            if (is_string($c['v'])) {
                $gauges[] = ['label' => $label($c['v']), 'mm' => $gaugeWidths[$c['v']] ?? null, 'wikidata_id' => $c['v']];
            }
        }
        $gaugeValues = array_values(array_unique(array_filter(array_column($gauges, 'mm'))));

        $wheel = self::firstString($claims['P2978'] ?? []);
        $fleet = array_values(array_filter(array_map(fn (array $c): ?string => is_string($c['v']) ? self::plain($c['v']) : null, $claims['P2802'] ?? [])));
        $file = self::imageFile($entity);
        $image = $file !== null && is_array($images[$file] ?? null) ? self::image($images[$file]) : null;
        $imageVia = "{$qid}#P18";
        if ($file === null) {
            foreach (self::imageCandidates($entity, $infobox, $categoryFiles, $name, $qid) as [$candidate, $via]) {
                if (is_array($images[$candidate] ?? null) && self::isFreeImage($images[$candidate]) && ! MaterialPhotoExclusions::excludes($candidate)) {
                    [$image, $imageVia] = [self::image($images[$candidate]), $via];
                    break;
                }
            }
        }
        $category = self::firstString($claims['P373'] ?? []);

        $attributes = [
            'wikidata_id' => $qid,
            'name' => $name,
            'type' => $type[0]->value,
            'designations' => $this->designations($entity, $name) ?: null,
            'description' => self::plain($entity['descriptions']['fr'] ?? null),
            // Texte joint sans les constructeurs sans libellé (nom = identifiant Wikidata, Material::isReadableName).
            'builder' => $builderText,
            'builders' => $builders ?: null,
            'operators' => $operators ?: null,
            'in_service_from' => $from,
            'in_service_to' => $to,
            'units_built' => $units,
            'max_speed_kmh' => $speed,
            'power_kw' => $power,
            'electrification' => $electrification ?: null,
            'gauges' => $gauges ?: null,
            'gauge_mm' => count($gaugeValues) === 1 ? (int) $gaugeValues[0] : null,
            'wheel_arrangement' => $wheel !== null ? mb_substr($label($wheel), 0, 60) : null,
            'fleet_numbers' => $fleet !== [] ? mb_strimwidth(implode(' ; ', $fleet), 0, 255, '…') : null,
            'image' => $image,
            'commons_category' => $category,
            'wikipedia_fr' => $entity['sitelinks']['frwiki'] ?? null,
            'wikipedia_en' => $entity['sitelinks']['enwiki'] ?? null,
            'scope' => $attestation === null ? 'france' : 'attested',
        ];
        $service = $this->serviceStatus($qid, $claims, $infobox, $entity['read_on'] ?? null);
        $attributes['service_status'] = $service['status'] ?? null;
        $attributes['service_status_checked_at'] = $service['checked_at'] ?? null;

        $sources = [
            'designations' => 'labels', 'description' => 'description:fr', 'builder' => 'P176', 'builders' => 'P176',
            'operators' => 'P137', 'in_service_from' => 'P729', 'in_service_to' => $toProperty, 'units_built' => 'P1092',
            'max_speed_kmh' => 'P2052', 'power_kw' => 'P2109', 'electrification' => 'P930', 'gauges' => 'P1064',
            'gauge_mm' => 'P1064', 'wheel_arrangement' => 'P2978', 'fleet_numbers' => 'P2802', 'commons_category' => 'P373',
            'wikipedia_fr' => 'sitelink:frwiki', 'wikipedia_en' => 'sitelink:enwiki',
        ];
        foreach ($sources as $field => $what) {
            if ($attributes[$field] !== null) {
                $provenance[$field] = $ref($what);
            }
        }
        $provenance = array_replace($provenance, $corrected, $filled);
        if ($speedOrigin !== null && $speed !== null) {
            $provenance['max_speed_kmh']['original'] = $speedOrigin;
        }
        if ($powerOrigin !== null && $power !== null) {
            $provenance['power_kw']['original'] = $powerOrigin;
        }
        if ($service !== null) {
            $provenance['service_status'] = $service['provenance'];
        }
        // Périmètre : rattachée à la France par Wikidata (P17 ou exploitant français), ou attestée par une ligne citée.
        $provenance['scope'] = $attestation ?? $ref('P17|P137');
        if ($image !== null) {
            $provenance['image'] = ['source' => 'commons', 'ref' => $image['file'], 'via' => $imageVia];
        }
        $attributes['provenance'] = $provenance;

        return $attributes;
    }

    /**
     * État d'utilisation, seulement quand une source le dit : Wikidata P5817 d'abord, sinon l'effectif
     * de l'infobox Wikipédia (nombre d'engins en service, daté d'une année assez récente).
     *
     * @param  array<string, list<array<string, mixed>>>  $claims
     * @param  array{title: string, revid: int, parameters: array<string, string>, read_on?: string, shared?: bool}|null  $infobox
     * @return array{status: string, checked_at: string, provenance: array<string, mixed>}|null
     */
    private function serviceStatus(string $qid, array $claims, ?array $infobox, ?string $wikidataReadOn): ?array
    {
        if ($wikidataReadOn !== null) {
            foreach ($claims['P5817'] ?? [] as $claim) {
                $status = is_string($claim['v']) ? $this->rules->serviceStatus($claim['v']) : null;
                if ($status !== null) {
                    $asOf = self::asOf($claim['q']['P585'][0] ?? null);

                    return [
                        'status' => $status,
                        'checked_at' => $wikidataReadOn,
                        'provenance' => array_filter(['source' => 'wikidata', 'ref' => "{$qid}#P5817", 'value' => $claim['v'], 'as_of' => $asOf], fn ($v): bool => $v !== null),
                    ];
                }
            }
        }
        $fleet = $infobox !== null ? WikipediaMaterialInfobox::fleet($infobox['parameters']['effectif'] ?? null) : null;
        if ($infobox === null || $fleet === null || ! isset($infobox['read_on']) || (int) substr($fleet['as_of'], 0, 4) < $this->rules->fleetMinYear()) {
            return null;
        }

        $provenance = ['source' => 'wikipedia', 'ref' => $infobox['title'].'#infobox:Effectif', 'revid' => $infobox['revid'], 'as_of' => $fleet['as_of']];
        // L'effectif d'un article commun à plusieurs séries est celui de la famille : il n'est pas repris pour une série.
        if (! ($infobox['shared'] ?? false)) {
            $provenance['units'] = $fleet['count'];
        }

        return ['status' => $fleet['count'] > 0 ? 'in_service' : 'withdrawn', 'checked_at' => $infobox['read_on'], 'provenance' => $provenance];
    }

    /** Date d'une valeur temporelle Wikidata (AAAA-MM-JJ, AAAA-MM ou AAAA selon la précision), nulle si illisible. */
    private static function asOf(mixed $time): ?string
    {
        if (! is_array($time) || ! preg_match('/^\+(\d{4})-(\d{2})-(\d{2})/', (string) ($time['time'] ?? ''), $m)) {
            return null;
        }
        $precision = (int) ($time['precision'] ?? 0);

        return match (true) {
            $precision >= 11 => "{$m[1]}-{$m[2]}-{$m[3]}",
            $precision === 10 => "{$m[1]}-{$m[2]}",
            $precision === 9 => $m[1],
            default => null,
        };
    }

    /** @return list<string> identifiants Wikidata cités par l'élément (constructeurs, exploitants, alimentations, écartements, essieux) */
    public static function referencedIds(array $entity): array
    {
        $ids = [];
        foreach (['P176', 'P137', 'P930', 'P1064', 'P2978'] as $property) {
            foreach ($entity['claims'][$property] ?? [] as $c) {
                if (is_string($c['v']) && str_starts_with($c['v'], 'Q')) {
                    $ids[] = $c['v'];
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return list<string> */
    public static function gaugeIds(array $entity): array
    {
        return array_values(array_unique(array_filter(array_map(fn (array $c): ?string => is_string($c['v']) ? $c['v'] : null, $entity['claims']['P1064'] ?? []))));
    }

    /**
     * Images de repli d'une série sans image sur Wikidata (P18), dans l'ordre : image de l'infobox de son
     * article Wikipédia en français (choisie par ses rédacteurs ; pas pour un article partagé par plusieurs
     * séries, ni une image par défaut, un plan ou un dessin vectoriel), puis premiers fichiers de sa
     * catégorie Commons (P373). Une catégorie range aussi des vues de gare, des accidents ou d'autres engins
     * (« Pályaudvar. Fortepan 75574 » dans « U46 coaches ») : avec `$name`, un fichier de catégorie n'est
     * candidat que si son nom reprend un mot distinctif de la série (« Pauline », « 0543 », « PBA ») et ne
     * parle pas d'accident. Chaque candidat est gardé avec sa provenance (`via`) ; map() retient le premier
     * dont Commons donne une licence libre et un auteur (isFreeImage()). Avec `$qid`, la photo choisie après
     * relecture (database/data/material_photo_choices.php) passe en premier.
     *
     * @param  array<string, mixed>  $entity
     * @param  array{title: string, parameters: array<string, string>, shared?: bool}|null  $infobox
     * @param  array<string, list<string>|null>  $categoryFiles
     * @return list<array{0: string, 1: string}> nom de fichier (sans « File: ») et provenance
     */
    public static function imageCandidates(array $entity, ?array $infobox, array $categoryFiles = [], ?string $name = null, ?string $qid = null): array
    {
        if (self::imageFile($entity) !== null) {
            return [];
        }
        $out = [];
        $chosen = $qid === null ? null : MaterialPhotoChoices::for($qid);
        if ($chosen !== null) {
            $out[] = [$chosen['file'], $chosen['found_in']];
        }
        $file = trim((string) preg_replace('/^(?:fichier|file|image)\s*:\s*/iu', '', (string) ($infobox['parameters']['image'] ?? '')));
        if ($infobox !== null && ($infobox['shared'] ?? false) !== true && $file !== ''
            && preg_match('/\.(?:jpe?g|png|tiff?|webp)$/iu', $file) === 1 && preg_match('/^d[ée]faut\b|\bplan\b|\bsch[ée]ma\b/iu', $file) !== 1) {
            $out[] = [str_replace('_', ' ', $file), 'frwiki:'.$infobox['title'].'#infobox'];
        }
        $category = self::firstString($entity['claims']['P373'] ?? []);
        if ($category !== null) {
            $words = $name === null ? [] : array_filter(preg_split('/[^\p{L}\d]+/u', $name) ?: [],
                fn (string $w): bool => mb_strlen($w) >= 3 && ! in_array(mb_strtolower($w), ['voiture', 'voitures', 'and', 'des', 'les', 'type', 'série'], true));
            foreach ($categoryFiles[$category] ?? [] as $candidate) {
                $named = $name === null || array_filter($words, fn (string $w): bool => preg_match('/(?<![\p{L}\d])'.preg_quote($w, '/').'(?![\p{L}\d])/iu', $candidate) === 1) !== [];
                if ($named && preg_match('/catastrophe|accident|d[ée]raill/iu', $candidate) !== 1) {
                    $out[] = [$candidate, 'commons:Category:'.$category];
                }
            }
        }

        return $out;
    }

    /**
     * Image de repli réutilisable : licence libre ou domaine public d'après Commons, auteur connu pour une
     * licence libre, format affichable (pas de SVG ni de document).
     *
     * @param  array<string, mixed>  $meta  sortie de CommonsMetadata::normalize
     */
    public static function isFreeImage(array $meta): bool
    {
        return match ($meta['rights_status'] ?? null) {
            Media::RIGHTS_PUBLIC_DOMAIN => true,
            Media::RIGHTS_OPEN_LICENSE => trim((string) ($meta['author'] ?? '')) !== '',
            default => false,
        } && in_array($meta['mime'] ?? null, ['image/jpeg', 'image/png', 'image/tiff', 'image/webp'], true);
    }

    /**
     * Nom de l'image principale (P18 de rang préféré, sinon le premier), null si elle a été écartée après
     * relecture (MaterialPhotoExclusions) : la série prend alors une image de repli.
     */
    public static function imageFile(array $entity): ?string
    {
        $file = self::preferredString($entity['claims']['P18'] ?? []);

        return MaterialPhotoExclusions::excludes($file) ? null : $file;
    }

    /**
     * Nom d'usage : titre de l'article Wikipédia en français (sans la précision entre parenthèses),
     * sinon libellé français, sinon libellé anglais.
     *
     * @param  array<string, mixed>  $entity
     * @return array{0: ?string, 1: string} nom et provenance
     */
    private function name(array $entity): array
    {
        $title = $entity['sitelinks']['frwiki'] ?? null;
        if (is_string($title) && ($clean = self::plain(preg_replace('/\s*\([^()]*\)$/u', '', $title))) !== null) {
            return [$clean, 'sitelink:frwiki'];
        }
        foreach (['fr', 'en'] as $lang) {
            if (($label = self::plain($entity['labels'][$lang] ?? null)) !== null) {
                return [$label, "label:{$lang}"];
            }
        }

        return [null, ''];
    }

    /**
     * Autres noms : libellés, alias et surnoms, sans le nom d'usage ni doublon (casse ignorée).
     *
     * @param  array<string, mixed>  $entity
     * @return list<string>
     */
    private function designations(array $entity, string $name): array
    {
        $candidates = [
            $entity['labels']['fr'] ?? null,
            $entity['labels']['en'] ?? null,
            ...($entity['aliases']['fr'] ?? []),
            ...($entity['aliases']['en'] ?? []),
        ];
        foreach ($entity['claims']['P1449'] ?? [] as $c) {
            $candidates[] = is_array($c['v']) ? ($c['v']['text'] ?? null) : null;
        }
        $seen = [mb_strtolower($name) => true];
        $out = [];
        foreach ($candidates as $candidate) {
            $text = self::plain(is_string($candidate) ? $candidate : null);
            if ($text !== null && ! isset($seen[$key = mb_strtolower($text)])) {
                $seen[$key] = true;
                $out[] = mb_substr($text, 0, 120);
            }
        }

        return array_slice($out, 0, 20);
    }

    /**
     * Plus grande valeur convertie ; valeur d'origine renvoyée quand son unité n'est pas l'unité VFF.
     *
     * @param  list<array<string, mixed>>  $claims
     * @param  callable(float, string): ?int  $convert
     * @return array{0: ?int, 1: ?array{amount: float, unit: string}}
     */
    private function maxConverted(array $claims, callable $convert, string $baseUnit): array
    {
        $best = null;
        $origin = null;
        foreach ($claims as $c) {
            if (! is_array($c['v']) || ! isset($c['v']['amount']) || ! is_string($c['v']['unit'] ?? null)) {
                continue;
            }
            $value = $convert((float) $c['v']['amount'], $c['v']['unit']);
            if ($value !== null && ($best === null || $value > $best)) {
                $best = $value;
                $origin = $c['v']['unit'] !== $baseUnit ? ['amount' => (float) $c['v']['amount'], 'unit' => $c['v']['unit']] : null;
            }
        }

        return [$best, $origin];
    }

    /**
     * Image Commons : adresses, dimensions et droits tels que déclarés par Commons.
     *
     * @param  array<string, mixed>  $meta  sortie de CommonsMetadata::normalize
     * @return array<string, mixed>
     */
    private static function image(array $meta): array
    {
        return [
            'provider' => 'commons',
            'file' => $meta['file_title'],
            'thumb_url' => $meta['thumb_url'],
            'display_url' => $meta['display_url'],
            'source_url' => $meta['source_url'],
            'width' => $meta['width'],
            'height' => $meta['height'],
            'author' => $meta['author'],
            'license' => $meta['license'],
            'license_url' => $meta['license_url'],
            'rights_status' => $meta['rights_status'],
            'commercial_ok' => $meta['commercial_ok'],
            'share_alike' => $meta['share_alike'],
            'attribution_required' => $meta['attribution_required'],
            'attribution' => $meta['attribution'],
        ];
    }

    /** Année d'une valeur temporelle Wikidata, à la précision « année » ou mieux ; null sinon. */
    public static function year(mixed $time): ?int
    {
        if (! is_array($time) || ($time['precision'] ?? 0) < 9 || ! preg_match('/^([+-])(\d{1,4})-/', (string) ($time['time'] ?? ''), $m)) {
            return null;
        }
        $year = (int) $m[2];

        return $m[1] === '+' && $year >= 1800 && $year <= 2100 ? $year : null;
    }

    /** @param list<array<string, mixed>> $claims */
    private static function extremeYear(array $claims, string $mode): ?int
    {
        $years = array_values(array_filter(array_map(fn (array $c): ?int => self::year($c['v']), $claims)));
        if ($years === []) {
            return null;
        }

        return $mode === 'min' ? min($years) : max($years);
    }

    /**
     * Nombre construit univoque : une seule valeur, ou une seule sans qualificatif, ou des valeurs égales.
     *
     * @param  list<array<string, mixed>>  $claims
     */
    private static function units(array $claims): ?int
    {
        $values = [];
        $bare = [];
        foreach ($claims as $c) {
            if (is_array($c['v']) && isset($c['v']['amount']) && $c['v']['amount'] > 0) {
                $values[] = (int) round($c['v']['amount']);
                if (($c['q'] ?? []) === []) {
                    $bare[] = (int) round($c['v']['amount']);
                }
            }
        }
        $distinct = array_values(array_unique($values));
        if (count($distinct) === 1) {
            return $distinct[0];
        }

        return count($bare) === 1 ? $bare[0] : null;
    }

    /** @param list<mixed> $quantities */
    private static function firstInt(array $quantities): ?int
    {
        $first = $quantities[0] ?? null;

        return is_array($first) && isset($first['amount']) && $first['amount'] > 0 ? (int) round($first['amount']) : null;
    }

    /** @param list<array<string, mixed>> $claims */
    private static function firstString(array $claims): ?string
    {
        foreach ($claims as $c) {
            if (is_string($c['v']) && trim($c['v']) !== '') {
                return trim($c['v']);
            }
        }

        return null;
    }

    /** @param list<array<string, mixed>> $claims */
    private static function preferredString(array $claims): ?string
    {
        usort($claims, fn (array $a, array $b): int => (($b['rank'] ?? '') === 'preferred') <=> (($a['rank'] ?? '') === 'preferred'));

        return self::firstString($claims);
    }

    /** Texte d'une source, espaces normalisés et tirets longs ou moyens remplacés par un tiret simple. */
    public static function plain(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) CommonsMetadata::plainDashes($text)));

        return $text !== '' ? $text : null;
    }
}
