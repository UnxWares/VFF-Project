<?php

namespace App\Services\Media;

use App\Enums\MediaType;
use App\Models\Line;
use App\Models\Material;
use App\Models\Media;
use App\Models\Station;
use App\Services\Media\Sources\MediaRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Règles de couverture Commons des fiches sans image (gares, lignes, séries de matériel) : tout ce
 * qui décide si un fichier peut être rattaché à une fiche, sans réseau ni base (sauf `row`), pour
 * que la découverte (`vff:discover-commons-candidates`) et l'application
 * (`vff:import-commons-candidates`) appliquent les mêmes règles et que les tests les verrouillent.
 *
 * Droits : seuls CC BY, CC BY-SA, CC0 et le domaine public sont acceptés (compatibles avec une
 * société), avec un auteur ou un crédit lisible. GFDL, licence art libre, licence ouverte et
 * tout le reste restent aux mains de l'import historique : ici, on écarte.
 */
final class CommonsCoverage
{
    /** Version de la règle, écrite dans chaque ligne de candidats. */
    public const RULES_VERSION = 1;

    /** Distance maximale entre la position d'un fichier géolocalisé et celle de la gare. */
    public const MAX_FILE_DISTANCE_M = 500;

    /** Distance maximale pour l'image de référence Wikidata (P18), plus tolérante : l'élément fait foi. */
    public const MAX_P18_DISTANCE_M = 2000;

    /** Distance maximale d'un fichier trouvé par recherche géographique seule (m) : la moins sûre des méthodes. */
    public const MAX_GEOSEARCH_DISTANCE_M = 150;

    /** Largeur minimale (px) : en dessous, c'est une vignette ou une icône. */
    public const MIN_WIDTH = 300;

    private const OPEN_LICENSE = '/^(CC[ -]BY([ -]SA)?[ -]\d(\.\d)?|CC0|CC[ -]Zero|Public domain|PD[ -]|No restrictions)/i';

    private const FORBIDDEN_LICENSE = '/(\bNC\b|-NC|\bND\b|-ND|non[- ]?commercial|no derivatives)/i';

    private const GALLICA = '/\b(gallica|bnf|biblioth[eè]que nationale de France)\b/iu';

    /** Métro, tramway, bus : une gare ferroviaire n'est pas illustrée par une station urbaine du même nom. */
    private const URBAN = '/\b(m[ée]tro|subway|tramways?|trams?|T\d{1,2} express|bus|autobus)\b/iu';

    private const HEAVY_RAIL = '/\b(SNCF|RER|TER|TGV|Transilien|railway stations?|train stations?|chemins? de fer|bahnhof)\b/iu';

    /** Mots d'un titre de fichier ferroviaire, pour la recherche géographique (plus étroit que STATION_WORD : pas de « quai » ni de « voie »). */
    private const RAILWAY_TITLE = '/\b(gares?|haltes?|stations?|bahnhof|SNCF|TER|RER|trains?|voies? ferrées?|chemins? de fer|railway|ferroviaires?)\b/iu';

    /** Titres où « gare » ou « station » désigne autre chose qu'une gare : voirie, commerce, station d'épuration… */
    private const NOT_A_STATION = '/\b(rues?|avenues?|boulevards?|impasse|route|places?|parking|street|stra(ss|ß)e|chemin de la gare|bo[iî]tes? à livres|bookcase|[ée]puration|service|essence|m[ée]t[ée]o|ski|radio|bar|caf[ée]|h[ôo]tel|restaurant|taxi|bus|gare routi[èe]re|pr[eè]s de|proche|devant|parcours|monument|panneaux?|sant[eé])\b/iu';

    private const NOT_PICTURE = '/\b(logos?|icons?|icônes?|pictogram(me)?s?|pictos?|symbols?|wappen|blasons?|coats? of arms|signatures?|rdt icons|BSicon)\b/iu';

    /** Mots d'une catégorie ou d'un fichier de gare. */
    private const STATION_WORD = '/\b(gares?|stations?|haltes?|bahnhof|railway|chemins? de fer|ferroviaires?|SNCF|trains?|quais?|platforms?|bâtiments? voyageurs|passages? à niveau|voies?)\b/iu';

    /** Sous-catégories d'une gare où l'on descend d'un niveau. */
    private const STATION_SUBCAT = '/\b(gares?|stations?|haltes?|bahnhof|ext[eé]rieurs?|int[eé]rieurs?|exterior|interior|quais?|platforms?|bâtiments? voyageurs|building|postcards?|cartes? postales?|plans?|maps?|historical|historique)\b/iu';

    /**
     * Motif de refus d'un fichier (métadonnées de CommonsMetadata::normalize), null s'il est acceptable.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function rejectReason(array $meta): ?string
    {
        $mime = (string) ($meta['mime'] ?? '');
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/tiff'], true)) {
            return 'mime';
        }
        $status = $meta['rights_status'] ?? null;
        if (! in_array($status, [Media::RIGHTS_OPEN_LICENSE, Media::RIGHTS_PUBLIC_DOMAIN], true) || ($meta['commercial_ok'] ?? null) !== true) {
            return 'licence';
        }
        $license = trim((string) ($meta['license'] ?? ''));
        if ($license === '' || ! preg_match(self::OPEN_LICENSE, $license) || preg_match(self::FORBIDDEN_LICENSE, $license)) {
            return 'licence';
        }
        $who = trim((string) ($meta['author'] ?? ''));
        if (preg_match('#^https?://#i', $who)) {
            return 'auteur';
        }
        if ($who === '') {
            $who = trim((string) ($meta['credit'] ?? ''));
        }
        if ($who === '') {
            return 'auteur';
        }
        // Règle Gallica de VFF : métadonnées et liens seulement, jamais d'image numérisée par la BnF.
        if (preg_match(self::GALLICA, (string) ($meta['credit'] ?? '').' '.(string) ($meta['author'] ?? '').' '.(string) ($meta['attribution'] ?? ''))) {
            return 'gallica';
        }
        if ($mime !== 'image/svg+xml' && (int) ($meta['width'] ?? 0) < self::MIN_WIDTH) {
            return 'petite';
        }
        $name = CommonsMetadata::titleFromFile((string) ($meta['file_title'] ?? ''));
        $cats = implode(' | ', array_slice((array) ($meta['categories'] ?? []), 0, 40));
        if (preg_match(self::NOT_PICTURE, $name) || preg_match('/\b(Logos|Icons|Pictograms)\b/u', $cats)) {
            return 'logo';
        }

        return null;
    }

    /**
     * Le fichier peut-il illustrer cette gare ? `$via` : `p18` (image de référence Wikidata),
     * `category` (catégorie propre à la gare) ou `weak_category` (catégorie sans mot de gare,
     * souvent celle de la commune : le fichier doit alors parler de chemin de fer).
     *
     * @param  array<string, mixed>  $meta
     * @return string|null motif de refus
     */
    public static function stationReject(array $meta, string $via, ?float $stationLat, ?float $stationLon): ?string
    {
        $base = self::rejectReason($meta);
        if ($base !== null) {
            return $base;
        }
        $lat = $meta['lat'] ?? null;
        $lon = $meta['lon'] ?? null;
        if ($lat !== null && $lon !== null && $stationLat !== null && $stationLon !== null) {
            $limit = $via === 'p18' ? self::MAX_P18_DISTANCE_M : self::MAX_FILE_DISTANCE_M;
            if (self::distanceM((float) $lat, (float) $lon, $stationLat, $stationLon) > $limit) {
                return 'loin';
            }
        }
        $hay = CommonsMetadata::titleFromFile((string) ($meta['file_title'] ?? '')).' | '.implode(' | ', (array) ($meta['categories'] ?? []));
        if (preg_match(self::URBAN, $hay) && ! preg_match(self::HEAVY_RAIL, $hay)) {
            return 'urbain';
        }
        if ($via === 'weak_category') {
            if (! preg_match(self::STATION_WORD, $hay)) {
                return 'hors_sujet';
            }
        }

        return null;
    }

    /** Le fichier peut-il illustrer cette ligne ou cette série ? Mêmes règles de droits, mot ferroviaire exigé hors P18. */
    /** @param  array<string, mixed>  $meta */
    public static function railReject(array $meta, string $via): ?string
    {
        $base = self::rejectReason($meta);
        if ($base !== null) {
            return $base;
        }
        if ($via !== 'p18') {
            $hay = CommonsMetadata::titleFromFile((string) ($meta['file_title'] ?? '')).' | '.implode(' | ', (array) ($meta['categories'] ?? []));
            if (! preg_match(self::STATION_WORD, $hay)) {
                return 'hors_sujet';
            }
        }

        return null;
    }

    public static function isStationCategory(string $category): bool
    {
        return (bool) preg_match(self::STATION_WORD, preg_replace('/^Category:/', '', $category) ?? $category);
    }

    /** Sous-catégorie où descendre d'un niveau pour compléter une gare pauvre en images. */
    public static function isStationSubcategory(string $category): bool
    {
        return (bool) preg_match(self::STATION_SUBCAT, preg_replace('/^Category:/', '', $category) ?? $category);
    }

    /** Le titre du fichier parle-t-il de chemin de fer (mot de gare, de halte, de voie) ? */
    public static function titleIsRailway(string $fileTitle): bool
    {
        $title = CommonsMetadata::titleFromFile($fileTitle);

        return preg_match(self::RAILWAY_TITLE, $title) && ! self::offTopicTitle($fileTitle);
    }

    /** Titre qui parle d'autre chose que de la gare (voirie, commerce, « près de la gare », panneaux…). */
    public static function offTopicTitle(string $fileTitle): bool
    {
        return (bool) preg_match(self::NOT_A_STATION, CommonsMetadata::titleFromFile($fileTitle));
    }

    /**
     * Le fichier nomme-t-il le lieu de la gare (titre ou catégories) ? Le mot distinctif le plus long
     * du nom de la gare (4 lettres ou plus, sans accents ni majuscules, hors « paris », « gare »,
     * « saint »…) doit y figurer : le nom d'une grande commune voisine ne suffit pas. Sans mot
     * distinctif dans le nom, c'est celui de la commune qui est exigé.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function mentionsPlace(array $meta, string $name, ?string $city = null): bool
    {
        $hay = self::fold(CommonsMetadata::titleFromFile((string) ($meta['file_title'] ?? '')).' '.implode(' ', (array) ($meta['categories'] ?? [])));
        $word = self::distinctiveWord($name) ?? ($city !== null ? self::distinctiveWord($city) : null);

        return $word !== null && str_contains($hay, $word);
    }

    /**
     * Le fichier nomme-t-il aussi la commune (mot distinctif de son nom) dans son titre ou ses
     * catégories ? Évite les homonymes de gares d'autres communes.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function mentionsCity(array $meta, ?string $city, string $name): bool
    {
        $word = $city !== null && $city !== '' ? self::distinctiveWord($city) : null;
        if ($word === null) {
            return false;
        }
        $hay = self::fold(CommonsMetadata::titleFromFile((string) ($meta['file_title'] ?? '')).' '.implode(' ', (array) ($meta['categories'] ?? [])));

        return str_contains($hay, $word);
    }

    /**
     * Le titre du fichier désigne-t-il la gare elle-même : le nom entier de la gare (« Gare de X »,
     * « Halte de X », « X gare », « Gare SNCF de X »…) accolé à un mot de gare ? Beaucoup plus strict que
     * mentionsPlace() : écarte un viaduc, une ligne ou un éditeur qui portent le même nom.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function titleNamesStation(array $meta, string $name): bool
    {
        $name = preg_replace('/\s*\([^)]*\)\s*$/u', '', $name) ?? $name;
        $name = preg_replace(['/\bSt-/u', '/\bSte-/u'], ['Saint-', 'Sainte-'], $name) ?? $name;
        $n = trim(self::fold($name));
        if (strlen($n) < 4) {
            return false;
        }
        $title = ' '.trim(self::fold(CommonsMetadata::titleFromFile((string) ($meta['file_title'] ?? '')))).' ';
        $q = preg_quote($n, '/');
        $words = '(gare|gares|halte|haltes|station|bahnhof|arret)';
        $pattern = '/ '.$words.'( sncf| ter| ferroviaire| routiere)?( (de|du|des|d|de la|de l|von|zu))? '.$q.' | '.$q.' (la |l |de la |en |cote |vue |facade |batiment )?'.$words.' /';

        return (bool) preg_match($pattern, $title) && ! preg_match('/ gare routiere /', $title);
    }

    private static function distinctiveWord(string $place): ?string
    {
        $best = null;
        foreach (preg_split('/[^a-z0-9]+/', self::fold($place), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (strlen($word) >= 4 && ! in_array($word, ['gare', 'halte', 'saint', 'sainte', 'ancienne', 'paris', 'sous', 'sur', 'les', 'des', 'aux'], true)
                && ($best === null || strlen($word) > strlen($best))) {
                $best = $word;
            }
        }

        return $best;
    }

    private static function fold(string $s): string
    {
        $s = mb_strtolower($s);
        $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);

        return preg_replace('/[^a-z0-9]+/', ' ', $t === false ? $s : $t) ?? $s;
    }

    /** Distance en mètres entre deux points (haversine). */
    public static function distanceM(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6_371_000.0;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $a = sin(($p2 - $p1) / 2) ** 2 + cos($p1) * cos($p2) * sin(deg2rad($lon2 - $lon1) / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    /**
     * Titres de catégories Commons plausibles pour une gare sans élément Wikidata, d'après son nom.
     *
     * @return list<string> titres « Category:… », sans doublon
     */
    public static function stationCategoryNames(string $name, string $type): array
    {
        $name = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $name) ?? $name);
        $name = preg_replace('/\bSt-/u', 'Saint-', $name) ?? $name;
        $name = preg_replace('/\bSte-/u', 'Sainte-', $name) ?? $name;
        $name = str_replace('’', "'", $name);
        if ($name === '' || mb_strlen($name) < 3 || preg_match('/^(ancienne )?(gare|halte)$/iu', $name)) {
            return [];
        }
        $vowel = (bool) preg_match('/^[AEIOUYÀÂÄÉÈÊËÎÏÔÖÙÛÜH]/u', $name);
        $of = $vowel ? "d'" : 'de ';
        $stems = $type === 'halte' ? ['Halte', 'Gare', 'Ancienne gare'] : ['Gare', 'Ancienne gare'];
        $out = [];
        foreach ($stems as $stem) {
            $out[] = "Category:{$stem} {$of}{$name}";
        }

        return array_values(array_unique($out));
    }

    /**
     * MediaRecord d'un fichier pour une fiche (écrit par MediaWriter, comme toutes les sources). Commons
     * n'accepte que des licences qui permettent la modification : `derivatives_ok` est vrai.
     *
     * @param  class-string  $mediableType
     * @param  array<string, mixed>  $meta  CommonsMetadata::normalize
     * @param  array<string, mixed>  $origin  via, category, qid, distance_m, featured
     */
    public static function record(string $mediableType, int $mediableId, int $pageId, array $meta, array $origin): MediaRecord
    {
        $precision = $meta['date_precision'] ?? null;
        $keep = array_intersect_key($meta, array_flip(['file_title', 'credit', 'attribution_required', 'date_raw']));
        $categories = array_slice(array_values(array_filter(
            (array) ($meta['categories'] ?? []),
            fn ($c) => ! preg_match('/^(CC-|PD|GFDL|License|Self-published|Files with|Media (contributed|supported|needing)|Uploaded|Pages with|Images with|Photos with|Taken with|Created with)/i', (string) $c),
        )), 0, 25);

        return new MediaRecord(
            provider: Media::PROVIDER_COMMONS,
            externalId: (string) $pageId,
            mediableType: $mediableType,
            mediableId: $mediableId,
            rightsStatus: (string) $meta['rights_status'],
            license: $meta['license'] ?? null,
            licenseUrl: $meta['license_url'] ?? null,
            commercialOk: $meta['commercial_ok'] ?? null,
            derivativesOk: true,
            shareAlike: $meta['share_alike'] ?? null,
            attribution: (string) ($meta['attribution'] ?? ''),
            author: $meta['author'] ?? null,
            sourceUrl: (string) $meta['source_url'],
            thumbUrl: $meta['thumb_url'] ?? null,
            displayUrl: $meta['display_url'] ?? null,
            type: MediaType::tryFrom((string) ($meta['type'] ?? '')) ?? MediaType::Photo,
            title: $meta['title'] ?? null,
            caption: $meta['caption'] ?? null,
            width: $meta['width'] ?? null,
            height: $meta['height'] ?? null,
            capturedAt: $meta['captured_at'] ?? null,
            datePrecision: in_array($precision, MediaRecord::PRECISIONS, true) ? $precision : null,
            depictedYear: $meta['depicted_year'] ?? null,
            lat: isset($meta['lat'], $meta['lon']) ? (float) $meta['lat'] : null,
            lng: isset($meta['lat'], $meta['lon']) ? (float) $meta['lon'] : null,
            featured: (bool) ($origin['featured'] ?? false),
            meta: array_filter([
                ...$keep,
                'via' => $origin['via'] ?? null,
                'rule' => 'coverage-v'.self::RULES_VERSION,
                'category' => $origin['category'] ?? null,
                'distance_m' => $origin['distance_m'] ?? null,
                'qid' => $origin['qid'] ?? null,
                'year_to' => $meta['depicted_year_to'] ?? null,
                'categories' => $categories,
            ], fn ($v) => $v !== null && $v !== []),
        );
    }

    /**
     * Classe de la fiche pour un sujet de candidats (`station`, `line`, `material`), null si inconnu.
     *
     * @return class-string<Model>|null
     */
    public static function modelClass(string $subject): ?string
    {
        return match ($subject) {
            'station' => Station::class,
            'line' => Line::class,
            'material' => Material::class,
            default => null,
        };
    }
}
