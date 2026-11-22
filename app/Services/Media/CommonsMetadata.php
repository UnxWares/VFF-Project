<?php

namespace App\Services\Media;

use App\Models\Media;
use App\Support\WebUrl;

/**
 * Normalisation des métadonnées d'un fichier Wikimedia Commons (imageinfo +
 * extmetadata) en champs VFF, avec les règles de DROITS du projet :
 *
 *   - la licence est reprise TELLE QUE déclarée par Commons (LicenseShortName),
 *     jamais réécrite ni « devinée » : sans information → NULL / unknown ;
 *   - CC BY / BY-SA / GFDL / FAL… → `open_license` : auteur + licence + lien
 *     obligatoires à l'affichage ;
 *   - domaine public / CC0 → `public_domain` : on cite quand même la source ;
 *   - l'année représentée vient de « DateTimeOriginal » (date de l'œuvre selon
 *     les conventions Commons - pas la date de téléversement), à défaut d'une
 *     catégorie datée (« 1931 photographs of Paris ») ;
 *   - les adresses (page du fichier, licence, images) ne sont gardées qu'en http(s) (WebUrl) : `LicenseUrl`
 *     vient du HTML de la page Commons, modifiable par tous, et une adresse `javascript:` y deviendrait un lien.
 */
class CommonsMetadata
{
    /** Formats affichables tels quels par un navigateur. */
    private const WEB_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /** Formats retenus (images + documents numérisés ; pas d'audio/vidéo). */
    public const ACCEPTED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/tiff',
        'image/svg+xml', 'application/pdf', 'image/vnd.djvu',
    ];

    /**
     * @param  array{title: string, info: array<string, mixed>}  $page
     * @return array<string, mixed>
     */
    public static function normalize(array $page): array
    {
        $info = $page['info'];
        $ext = array_map(fn ($v) => is_array($v) ? ($v['value'] ?? null) : $v, $info['extmetadata'] ?? []);
        $mime = (string) ($info['mime'] ?? '');
        $width = isset($info['width']) ? (int) $info['width'] : null;
        $height = isset($info['height']) ? (int) $info['height'] : null;
        $categories = array_values(array_filter(array_map('trim', explode('|', (string) ($ext['Categories'] ?? '')))));

        $fileTitle = $page['title'];
        $title = self::plainDashes(self::clean($ext['ObjectName'] ?? null, 250)
            ?: self::titleFromFile($fileTitle));

        $date = self::parseDate($ext['DateTimeOriginal'] ?? null)
            ?? self::dateFromCategories($categories);

        $type = self::detectType($fileTitle, $categories, $mime);
        // Garde-fou : une photographie (ou carte postale) ne peut pas précéder
        // 1839. Une telle date est une coquille de la source (ex. « 1828 » pour
        // 1928) : on se rabat sur l'année du titre si elle existe, sinon rien.
        // La date brute reste dans provider_meta.date_raw (on ne réécrit pas la source).
        if ($date !== null && in_array($type, ['photo', 'postcard'], true) && $date['year'] < 1839) {
            $date = preg_match('/\b(18[4-9]\d|19\d\d|20\d\d)\b/', self::titleFromFile($fileTitle), $ty) && self::plausibleYear((int) $ty[1])
                ? ['year' => (int) $ty[1], 'precision' => 'title', 'date' => null]
                : null;
        }

        // Carte postale datée de 1990 ou après : c'est presque toujours la date de
        // numérisation/téléversement d'une carte ANCIENNE, pas l'époque représentée.
        if ($date !== null && $type === 'postcard' && $date['year'] >= 1990) {
            $fromCats = self::dateFromCategories($categories);
            $date = ($fromCats !== null && $fromCats['year'] < 1990) ? $fromCats : null;
        }

        $rights = self::rights($ext);
        $author = self::author($ext['Artist'] ?? null);
        $thumb = WebUrl::safe(self::stripQuery($info['thumburl'] ?? null));
        $original = WebUrl::safe(self::stripQuery($info['url'] ?? null));

        $lat = is_numeric($ext['GPSLatitude'] ?? null) ? (float) $ext['GPSLatitude'] : null;
        $lon = is_numeric($ext['GPSLongitude'] ?? null) ? (float) $ext['GPSLongitude'] : null;

        return [
            'file_title' => $fileTitle,
            'title' => mb_substr($title, 0, 250),
            'caption' => self::plainDashes(self::clean($ext['ImageDescription'] ?? null, 800)),
            'mime' => $mime,
            'width' => $width,
            'height' => $height,
            'source_url' => WebUrl::safe($info['descriptionurl'] ?? null)
                ?? 'https://commons.wikimedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $fileTitle)),
            'thumb_url' => $thumb ?: $original,
            'display_url' => self::displayUrl($thumb, $original, $width, $mime),
            'author' => $author,
            'credit' => self::plainDashes(self::clean($ext['Credit'] ?? null, 300)),
            'license' => $rights['license'],
            'license_url' => $rights['license_url'],
            'rights_status' => $rights['rights_status'],
            'commercial_ok' => $rights['commercial_ok'],
            'share_alike' => $rights['share_alike'],
            'attribution_required' => $rights['attribution_required'],
            'attribution' => self::attribution($author, $rights, self::plainDashes(self::clean($ext['Attribution'] ?? null, 300))),
            'depicted_year' => $date['year'] ?? null,
            // Fin d'une plage (« 1900-1910 », gabarit {{Other date}} avec « au plus tard ») ; null sinon.
            'depicted_year_to' => $date['year_to'] ?? null,
            'date_precision' => $date['precision'] ?? null,
            'captured_at' => $date['date'] ?? null,
            'date_raw' => self::clean($ext['DateTimeOriginal'] ?? null, 120),
            'lat' => $lat,
            'lon' => $lon,
            'categories' => $categories,
            'type' => $type,
        ];
    }

    /**
     * Droits d'un fichier à partir des champs Commons, sans jamais inventer.
     *
     * @param  array<string, mixed>  $ext
     * @return array{license: ?string, license_url: ?string, rights_status: string, commercial_ok: ?bool, share_alike: ?bool, attribution_required: ?bool}
     */
    public static function rights(array $ext): array
    {
        $short = self::clean($ext['LicenseShortName'] ?? null, 180);
        $code = strtolower(trim((string) ($ext['License'] ?? '')));
        // Adresse lue dans le HTML de la page du fichier : rien d'autre que http(s), sinon la licence reste sans lien.
        $url = WebUrl::safe(self::stripQuery(self::clean($ext['LicenseUrl'] ?? null)));
        $haystack = $short.' '.$code.' '.self::clean($ext['UsageTerms'] ?? null);
        $nonFree = filter_var($ext['NonFree'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $copyrighted = strtolower((string) ($ext['Copyrighted'] ?? ''));

        $status = Media::RIGHTS_UNKNOWN;
        if (! $nonFree) {
            if (in_array($code, ['pd', 'cc0'], true)
                || preg_match('/\b(public domain|domaine public|gemeinfrei|cc0|cc-zero|no restrictions|pd-)/i', $haystack)
                || ($copyrighted === 'false' && $short !== null)) {
                $status = Media::RIGHTS_PUBLIC_DOMAIN;
            } elseif (preg_match('/\b(cc[- ]by|creative commons|gfdl|gnu free documentation|l?gplv?\d?|fal\b|art libre|free art|odbl|licence ouverte|etalab|open licen[cs]e|ogl)\b|^attribution\b/i', $haystack)) {
                $status = Media::RIGHTS_OPEN_LICENSE;
            }
        }

        $nc = (bool) preg_match('/\b(nc|non[- ]?commercial)\b|-nc-|-nc$/i', $haystack);
        $commercial = match ($status) {
            Media::RIGHTS_PUBLIC_DOMAIN => true,
            Media::RIGHTS_OPEN_LICENSE => ! $nc,
            default => $nonFree ? false : null,
        };
        $shareAlike = match ($status) {
            Media::RIGHTS_PUBLIC_DOMAIN => false,
            Media::RIGHTS_OPEN_LICENSE => (bool) preg_match('/(\bsa\b|-sa\b|-sa-|share ?alike|gfdl|gnu free|\bl?gpl|fal\b|art libre|free art|odbl)/i', $haystack),
            default => null,
        };
        $attrRequired = isset($ext['AttributionRequired'])
            ? filter_var($ext['AttributionRequired'], FILTER_VALIDATE_BOOLEAN)
            : ($status === Media::RIGHTS_OPEN_LICENSE ? true : null);

        return [
            'license' => $short,
            'license_url' => $url,
            'rights_status' => $status,
            'commercial_ok' => $commercial,
            'share_alike' => $shareAlike,
            'attribution_required' => $attrRequired,
        ];
    }

    /**
     * Ligne de crédit au format recommandé par Wikimedia :
     * « Auteur, Licence, via Wikimedia Commons ». Si le fichier impose un
     * texte d'attribution, c'est lui qu'on reprend.
     *
     * @param  array{license: ?string, rights_status: string}  $rights
     */
    public static function attribution(?string $author, array $rights, ?string $custom): string
    {
        $who = $custom ?: ($author ?: null);
        $license = $rights['license']
            ?? ($rights['rights_status'] === Media::RIGHTS_PUBLIC_DOMAIN ? 'Public domain' : 'licence non précisée');
        $parts = array_filter([$who, $license, 'via Wikimedia Commons']);

        return implode(', ', $parts);
    }

    /** Auteur lisible, NULL si inconnu/anonyme. */
    public static function author(?string $html): ?string
    {
        $txt = self::clean($html, 250);
        if ($txt === null) {
            return null;
        }
        if (preg_match('/^(auteur inconnu|unknown( author)?|anonyme?|anonymous|inconnu|non renseigné)\b/iu', $txt)) {
            return null;
        }

        return self::plainDashes($txt);
    }

    /**
     * Année représentée à partir de DateTimeOriginal (HTML Commons).
     *
     * @return array{year: int, year_to?: int, precision: string, date: ?string}|null
     */
    public static function parseDate(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        // Gabarits {{Other date}} / {{Circa}} : la date normalisée est dans un
        // commentaire « date QS:P,+1950-00-00T00:00:00Z/7,P1319,+1900…/9,P1326,+1910…/9 »
        // (valeur principale, puis qualificatifs « au plus tôt » P1319 / « au plus tard » P1326).
        if (preg_match('/date QS:P,\+(\d{4})-(\d{2})-(\d{2})T[^\/]*\/(\d+)([^<"]*)/', $raw, $m)) {
            $prec = (int) $m[4];
            $year = (int) $m[1];
            $quals = $m[5];
            $isCirca = (bool) preg_match('/P1480,Q5727902|circa|vers|environ/i', $raw);
            $earliest = preg_match('/P1319,\+(\d{4})-/', $quals, $e) ? (int) $e[1] : null;
            $latest = preg_match('/P1326,\+(\d{4})-/', $quals, $l) ? (int) $l[1] : null;
            if ($earliest !== null && $latest !== null && self::plausibleYear($earliest)) {
                return ['year' => $earliest, 'year_to' => $latest, 'precision' => $latest - $earliest <= 1 ? 'year' : 'range', 'date' => null];
            }
            if ($latest !== null && $earliest === null && self::plausibleYear($latest)) {
                return ['year' => $latest, 'precision' => 'before', 'date' => null];
            }
            if ($earliest !== null && $latest === null && self::plausibleYear($earliest)) {
                return ['year' => $earliest, 'precision' => 'after', 'date' => null];
            }
            if ($prec >= 8 && self::plausibleYear($year)) {
                return [
                    'year' => $year,
                    'precision' => $prec >= 11 ? ($isCirca ? 'circa' : 'day') : ($prec === 10 ? 'month' : ($prec === 9 ? ($isCirca ? 'circa' : 'year') : 'decade')),
                    'date' => $prec >= 11 && ! $isCirca ? sprintf('%04d-%s-%s', $year, $m[2], $m[3]) : null,
                ];
            }

            return null; // siècle ou moins précis : pas d'année affichable
        }

        $txt = self::clean($raw) ?? '';
        if (preg_match('/\b(entre|between|zwischen)\s+(?:\D{0,20})?(1[89]\d\d|20\d\d)\s+(?:et|and|und)\s+(?:\D{0,20})?(1[89]\d\d|20\d\d)\b/iu', $txt, $m) && self::plausibleYear((int) $m[2])) {
            return ['year' => (int) $m[2], 'year_to' => (int) $m[3], 'precision' => 'range', 'date' => null];
        }
        if (preg_match('/\b(avant|before|vor)\b\D{0,25}(1[89]\d\d|20\d\d)\b/iu', $txt, $m) && self::plausibleYear((int) $m[2])) {
            return ['year' => (int) $m[2], 'precision' => 'before', 'date' => null];
        }
        if (preg_match('/\b(après|after|nach)\b\D{0,25}(1[89]\d\d|20\d\d)\b/iu', $txt, $m) && self::plausibleYear((int) $m[2])) {
            return ['year' => (int) $m[2], 'precision' => 'after', 'date' => null];
        }
        if (preg_match('/\b(\d{4})[-:](\d{2})[-:](\d{2})\b/', $txt, $m) && self::plausibleYear((int) $m[1]) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return ['year' => (int) $m[1], 'precision' => 'day', 'date' => "{$m[1]}-{$m[2]}-{$m[3]}"];
        }
        if (preg_match('/\b(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})\b/', $txt, $m) && self::plausibleYear((int) $m[3]) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return ['year' => (int) $m[3], 'precision' => 'day', 'date' => sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1])];
        }
        if (preg_match('/\b(1[89]\d\d|20\d\d)\s*[–—-]\s*(1[89]\d\d|20\d\d)\b/u', $txt, $m) && self::plausibleYear((int) $m[1])) {
            return ['year' => (int) $m[1], 'year_to' => (int) $m[2], 'precision' => ((int) $m[2] - (int) $m[1]) <= 1 ? 'year' : 'range', 'date' => null];
        }
        if (preg_match('/\b(c\.|ca\.?|circa|vers|env\.?|environ|um|~)\s*(1[89]\d\d|20\d\d)\b/iu', $txt, $m) && self::plausibleYear((int) $m[2])) {
            return ['year' => (int) $m[2], 'precision' => 'circa', 'date' => null];
        }
        if (preg_match('/\b(?:années\s+)?(1[89]\d0|20\d0)\'?s\b|\bannées\s+(1[89]\d0|20\d0)\b/iu', $txt, $m)) {
            $y = (int) ($m[1] ?: $m[2]);
            if (self::plausibleYear($y)) {
                return ['year' => $y, 'precision' => 'decade', 'date' => null];
            }
        }
        if (preg_match('/\b(\d{4})-(\d{2})\b/', $txt, $m) && self::plausibleYear((int) $m[1]) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            return ['year' => (int) $m[1], 'precision' => 'month', 'date' => null];
        }
        if (preg_match('/\b(1[89]\d\d|20\d\d)\b/', $txt, $m) && self::plausibleYear((int) $m[1])) {
            return ['year' => (int) $m[1], 'precision' => 'year', 'date' => null];
        }

        return null;
    }

    /**
     * Année tirée d'une catégorie datée (« 1931 photographs of Paris »,
     * « Ligne de Vincennes in 1969 », « 1900s postcards of… »).
     *
     * @param  list<string>  $categories
     * @return array{year: int, precision: string, date: null}|null
     */
    public static function dateFromCategories(array $categories): ?array
    {
        foreach ($categories as $c) {
            if (preg_match('/^(1[89]\d\d|20\d\d) (photographs|photos|postcards|in rail transport|in France|in Paris|maps)\b/i', $c, $m)
                || preg_match('/\bin (1[89]\d\d|20[0-2]\d)$/', $c, $m)) {
                if (self::plausibleYear((int) $m[1])) {
                    return ['year' => (int) $m[1], 'precision' => 'category', 'date' => null];
                }
            }
        }
        foreach ($categories as $c) {
            if (preg_match('/^(1[89]\d0|20\d0)s (photographs|photos|postcards|in rail transport|in France|maps)\b/i', $c, $m)
                || preg_match('/\bin the (1[89]\d0|20\d0)s$/', $c, $m)) {
                return ['year' => (int) $m[1], 'precision' => 'decade', 'date' => null];
            }
        }

        return null;
    }

    /** @param  list<string>  $categories */
    public static function detectType(string $fileTitle, array $categories, string $mime): string
    {
        $hay = $fileTitle.' | '.implode(' | ', $categories);
        if (preg_match('/postcards?|cartes? postales?|\bCPA\b|\bCPSM\b|CP ?anciennes?|[-_ ]CPP?\d{3,}|Postkarten?|Ansichtskarten?/iu', $hay)) {
            return 'postcard';
        }
        if (in_array($mime, ['application/pdf', 'image/vnd.djvu'], true)) {
            return 'document';
        }
        if (preg_match('/track plans?|plans? de voies?|schémas?|Gleispl[aä]ne?|diagrams? of/iu', $hay)) {
            return 'plan';
        }
        if (preg_match('/\bmaps?\b|\bcartes? (de|du|des|ferroviaires?|IGN|d\'état-major)\b|\bKarten?\b|Cassini/iu', $hay)) {
            return 'map';
        }

        return 'photo';
    }

    /**
     * URL pour la visionneuse : l'original s'il est léger et affichable, sinon
     * une miniature standard (1280/960 px) dérivée de l'URL de miniature.
     */
    public static function displayUrl(?string $thumb, ?string $original, ?int $width, string $mime): ?string
    {
        $isSvg = $mime === 'image/svg+xml';
        if (! $isSvg && in_array($mime, self::WEB_MIMES, true) && $width !== null && $width <= 1280) {
            return $original ?: $thumb;
        }
        $target = ($isSvg || ($width ?? 0) > 1280) ? 1280 : (($width ?? 0) >= 960 ? 960 : CommonsClient::THUMB_WIDTH);
        if ($thumb && preg_match('#/(lossy-|lossless-)?(page\d+-)?'.CommonsClient::THUMB_WIDTH.'px-[^/]+$#', $thumb)) {
            return preg_replace('#/((?:lossy-|lossless-)?(?:page\d+-)?)'.CommonsClient::THUMB_WIDTH.'px-([^/]+)$#', '/${1}'.$target.'px-$2', $thumb);
        }

        return $thumb ?: $original;
    }

    /** HTML Commons → texte brut (supprime les éléments masqués et liens de catégorie). */
    public static function clean(?string $html, int $max = 0): ?string
    {
        if ($html === null) {
            return null;
        }
        $s = preg_replace('#<(span|div)[^>]*display:\s*none[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $s = preg_replace('#<link[^>]*>#i', ' ', $s) ?? $s;
        $s = preg_replace('#<br\s*/?>|</p>|</li>|</div>#i', ' ', $s) ?? $s;
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
        if ($s === '') {
            return null;
        }
        if ($max > 0 && mb_strlen($s) > $max) {
            $s = rtrim(mb_substr($s, 0, $max - 1)).'…';
        }

        return $s;
    }

    /**
     * Typographie VFF pour les textes affichés : pas de tiret long ni de double tiret,
     * remplacés par un tiret simple. Appliqué aux auteurs, titres, légendes et
     * crédits, JAMAIS au nom de licence (repris tel quel de la source).
     */
    public static function plainDashes(?string $s): ?string
    {
        if ($s === null) {
            return null;
        }

        return preg_replace(['/\s*[—―]\s*/u', '/\s*-{2,}\s*/', '/–/u'], [' - ', ' - ', '-'], $s) ?? $s;
    }

    public static function titleFromFile(string $fileTitle): string
    {
        $t = preg_replace('/^(File|Fichier):/i', '', $fileTitle) ?? $fileTitle;
        $t = preg_replace('/\.[a-z0-9]{2,5}$/i', '', $t) ?? $t;

        return trim(str_replace('_', ' ', $t));
    }

    public static function stripQuery(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        $parts = explode('?', $url, 2);

        return $parts[0];
    }

    private static function plausibleYear(int $y): bool
    {
        return $y >= 1820 && $y <= (int) date('Y');
    }
}
