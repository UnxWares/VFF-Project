<?php

namespace App\Services\Accidents\Context;

use App\Enums\AccidentContextSection;
use App\Enums\AccidentKind;
use App\Enums\AccidentReferenceAccess;
use App\Enums\AccidentReferenceKind;
use App\Enums\AccidentReferenceReliability;
use App\Support\SourceRights;

/**
 * Contrôle d'un fichier de contexte d'accident (database/data/accident_contexts/<slug>.php) avant tout
 * enregistrement. Le fichier est refusé en entier à la première règle enfreinte (liste de toutes les erreurs) :
 *
 *   - clés connues seulement, slug identique au nom du fichier, dates au format ISO ;
 *   - chaque document : fournisseur déclaré dans le registre des droits (table accident_references), nature,
 *     fiabilité et accès connus, titre, lien http(s), date du document AAAA, AAAA-MM ou AAAA-MM-JJ ;
 *   - chaque énoncé : texte en fr, en et de, sans tiret long ni tiret moyen, au moins un document cité, et
 *     chaque document cité lu (`open`) : un document payant ou seulement repéré n'appuie rien ;
 *   - une seule citation par fichier, de 200 caractères au plus, tirée d'une source ouverte dont la licence
 *     permet la citation : rapport du BEA-TT ou texte du Journal officiel (Licence Ouverte 2.0 ; les pages du site de
 *     l'Assemblée nationale et du Sénat restreignent la réutilisation commerciale ou payante : faits seulement) ;
 *   - corrections : champ permis (date, heure, morts, blessés, nature publiée, commune par code INSEE), valeur du
 *     bon type, raison en trois langues, documents lus ;
 *   - doublons (`aliases`) : slug d'une autre fiche, raison en trois langues, documents lus ;
 *   - images : 3 au plus, fichier de Commons (`File:…`), légende en trois langues.
 */
final class AccidentContextValidator
{
    public const LOCALES = ['fr', 'en', 'de'];

    /** Fournisseurs dont la licence permet une courte citation exacte (Licence Ouverte 2.0). */
    public const QUOTABLE_PROVIDERS = ['bea-tt', 'legifrance'];

    public const QUOTE_MAX = 200;

    public const MAX_IMAGES = 3;

    /** Champs de la fiche qu'une correction peut changer. */
    public const CORRECTABLE = ['occurred_on', 'occurred_at_time', 'deaths', 'injuries', 'kind', 'commune_insee'];

    private const TOP_KEYS = ['slug', 'checked_on', 'sources', 'sections', 'corrections', 'aliases', 'images'];

    private const SOURCE_KEYS = ['provider', 'kind', 'reliability', 'title', 'publisher', 'author', 'published', 'locator', 'url', 'access', 'consulted_on', 'note'];

    public function __construct(private readonly SourceRights $rights = new SourceRights) {}

    /**
     * @param  mixed  $data  tableau renvoyé par le fichier
     * @return list<string> erreurs (liste vide : fichier valide)
     */
    public function errors(mixed $data, string $expectedSlug): array
    {
        if (! is_array($data)) {
            return ['le fichier ne renvoie pas un tableau'];
        }
        $errors = [];
        foreach (array_diff(array_keys($data), self::TOP_KEYS) as $unknown) {
            $errors[] = "clé inconnue « {$unknown} »";
        }
        if (($data['slug'] ?? null) !== $expectedSlug) {
            $errors[] = 'slug « '.(is_string($data['slug'] ?? null) ? $data['slug'] : '')." » différent du nom du fichier « {$expectedSlug} »";
        }
        if (! $this->isDay($data['checked_on'] ?? null)) {
            $errors[] = 'checked_on : date AAAA-MM-JJ attendue';
        }

        $sources = $data['sources'] ?? null;
        if (! is_array($sources) || $sources === []) {
            $errors[] = 'sources : au moins un document attendu';
            $sources = [];
        }
        $open = [];
        foreach ($sources as $key => $source) {
            $errors = [...$errors, ...$this->sourceErrors($key, $source)];
            if (is_array($source) && ($source['access'] ?? null) === AccidentReferenceAccess::Open->value) {
                $open[(string) $key] = (string) ($source['provider'] ?? '');
            }
        }

        $sections = $data['sections'] ?? null;
        if (! is_array($sections)) {
            $errors[] = 'sections : tableau attendu';
            $sections = [];
        }
        $quotes = 0;
        $entries = 0;
        foreach ($sections as $section => $list) {
            if (AccidentContextSection::tryFrom((string) $section) === null) {
                $errors[] = "section inconnue « {$section} »";

                continue;
            }
            if (! is_array($list) || ! array_is_list($list)) {
                $errors[] = "section {$section} : liste attendue";

                continue;
            }
            foreach ($list as $i => $entry) {
                $where = "{$section}[{$i}]";
                $entries++;
                if (! is_array($entry)) {
                    $errors[] = "{$where} : tableau attendu";

                    continue;
                }
                foreach (array_diff(array_keys($entry), [...self::LOCALES, 'sources', 'quote']) as $unknown) {
                    $errors[] = "{$where} : clé inconnue « {$unknown} »";
                }
                $errors = [...$errors, ...$this->textErrors($entry, $where)];
                $errors = [...$errors, ...$this->citedErrors($entry['sources'] ?? null, $open, $sources, $where)];
                if (array_key_exists('quote', $entry)) {
                    $quotes++;
                    $errors = [...$errors, ...$this->quoteErrors($entry['quote'], $open, $where)];
                }
            }
        }
        if ($entries === 0) {
            $errors[] = 'sections : au moins un énoncé attendu';
        }
        if ($quotes > 1) {
            $errors[] = "citations : {$quotes} dans le fichier, une au plus";
        }

        foreach ((array) ($data['corrections'] ?? []) as $i => $c) {
            $errors = [...$errors, ...$this->correctionErrors($c, $open, $sources, "corrections[{$i}]")];
        }
        $fields = array_map(fn ($c) => is_array($c) ? ($c['field'] ?? null) : null, (array) ($data['corrections'] ?? []));
        if (count($fields) !== count(array_unique($fields))) {
            $errors[] = 'corrections : un même champ corrigé deux fois';
        }

        foreach ((array) ($data['aliases'] ?? []) as $i => $alias) {
            $where = "aliases[{$i}]";
            if (! is_array($alias) || ! is_string($alias['slug'] ?? null) || $alias['slug'] === $expectedSlug) {
                $errors[] = "{$where} : slug d'une autre fiche attendu";

                continue;
            }
            $errors = [...$errors, ...$this->textErrors($alias, $where)];
            $errors = [...$errors, ...$this->citedErrors($alias['sources'] ?? null, $open, $sources, $where)];
        }

        $images = (array) ($data['images'] ?? []);
        if (count($images) > self::MAX_IMAGES) {
            $errors[] = 'images : '.self::MAX_IMAGES.' au plus';
        }
        foreach ($images as $i => $image) {
            $where = "images[{$i}]";
            if (! is_array($image) || ! is_string($image['file'] ?? null) || ! preg_match('/^File:[^\/|#<>\[\]{}]+\.\w{3,4}$/u', $image['file'])) {
                $errors[] = "{$where} : nom de fichier de Commons « File:… » attendu";

                continue;
            }
            $errors = [...$errors, ...$this->textErrors((array) ($image['caption'] ?? []), "{$where}.caption")];
        }

        return $errors;
    }

    /** @return list<string> */
    private function sourceErrors(int|string $key, mixed $s): array
    {
        $where = "sources.{$key}";
        if (! is_string($key) || ! preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', $key)) {
            return ["{$where} : clé en minuscules, chiffres et tirets attendue"];
        }
        if (! is_array($s)) {
            return ["{$where} : tableau attendu"];
        }
        $errors = [];
        foreach (array_diff(array_keys($s), self::SOURCE_KEYS) as $unknown) {
            $errors[] = "{$where} : clé inconnue « {$unknown} »";
        }
        $provider = (string) ($s['provider'] ?? '');
        if (! $this->rights->knows('accident_references', $provider)) {
            $errors[] = "{$where} : fournisseur « {$provider} » absent du registre des droits (accident_references)";
        }
        if (AccidentReferenceKind::tryFrom((string) ($s['kind'] ?? '')) === null) {
            $errors[] = "{$where} : nature « ".($s['kind'] ?? '').' » inconnue';
        }
        if (AccidentReferenceReliability::tryFrom((string) ($s['reliability'] ?? '')) === null) {
            $errors[] = "{$where} : fiabilité « ".($s['reliability'] ?? '').' » inconnue';
        }
        if (AccidentReferenceAccess::tryFrom((string) ($s['access'] ?? '')) === null) {
            $errors[] = "{$where} : accès « ".($s['access'] ?? '').' » inconnu';
        }
        $title = $s['title'] ?? null;
        if (! is_string($title) || trim($title) === '' || mb_strlen($title) > 500) {
            $errors[] = "{$where} : titre de 1 à 500 caractères attendu";
        }
        $url = $s['url'] ?? null;
        if (! is_string($url) || ! preg_match('#^https?://[^\s]+$#', $url) || strlen($url) > 800) {
            $errors[] = "{$where} : lien http(s) attendu";
        }
        $published = $s['published'] ?? null;
        if ($published !== null && (! is_string($published) || ! preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $published))) {
            $errors[] = "{$where} : date du document AAAA, AAAA-MM ou AAAA-MM-JJ attendue";
        }
        if (! $this->isDay($s['consulted_on'] ?? null)) {
            $errors[] = "{$where} : consulted_on AAAA-MM-JJ attendue";
        }
        foreach (['publisher' => 200, 'author' => 200, 'locator' => 160, 'note' => 500] as $field => $max) {
            $v = $s[$field] ?? null;
            if ($v !== null && (! is_string($v) || mb_strlen($v) > $max)) {
                $errors[] = "{$where} : {$field} texte de {$max} caractères au plus ou null";
            }
        }
        foreach (['title', 'note'] as $field) {
            if (is_string($s[$field] ?? null) && $this->hasDash($s[$field])) {
                $errors[] = "{$where}.{$field} : tiret long ou moyen interdit";
            }
        }

        return $errors;
    }

    /**
     * @param  array<mixed>  $entry
     * @return list<string>
     */
    private function textErrors(array $entry, string $where): array
    {
        $errors = [];
        foreach (self::LOCALES as $locale) {
            $t = $entry[$locale] ?? null;
            if (! is_string($t) || trim($t) === '') {
                $errors[] = "{$where} : texte « {$locale} » manquant";
            } elseif ($this->hasDash($t)) {
                $errors[] = "{$where}.{$locale} : tiret long, tiret moyen ou double trait d’union interdit";
            } elseif (mb_strlen($t) > 1200) {
                $errors[] = "{$where}.{$locale} : 1 200 caractères au plus";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $open  documents lus (clé => fournisseur)
     * @param  array<mixed>  $all
     * @return list<string>
     */
    private function citedErrors(mixed $cited, array $open, array $all, string $where): array
    {
        if (! is_array($cited) || $cited === [] || ! array_is_list($cited)) {
            return ["{$where} : au moins un document cité attendu"];
        }
        $errors = [];
        foreach ($cited as $key) {
            if (! is_string($key) || ! array_key_exists($key, $all)) {
                $errors[] = "{$where} : document « ".(is_scalar($key) ? $key : '?').' » inconnu';
            } elseif (! array_key_exists($key, $open)) {
                $errors[] = "{$where} : document « {$key} » non lu (accès autre que open) : il n'appuie aucun énoncé";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $open
     * @return list<string>
     */
    private function quoteErrors(mixed $quote, array $open, string $where): array
    {
        if (! is_array($quote) || ! is_string($quote['text'] ?? null) || trim($quote['text']) === '') {
            return ["{$where}.quote : texte attendu"];
        }
        $errors = [];
        if (mb_strlen($quote['text']) > self::QUOTE_MAX) {
            $errors[] = "{$where}.quote : ".self::QUOTE_MAX.' caractères au plus';
        }
        if (! in_array($quote['lang'] ?? null, ['fr', 'en', 'de'], true)) {
            $errors[] = "{$where}.quote : langue fr, en ou de attendue";
        }
        $source = (string) ($quote['source'] ?? '');
        if (! array_key_exists($source, $open)) {
            $errors[] = "{$where}.quote : document « {$source} » inconnu ou non lu";
        } elseif (! in_array($open[$source], self::QUOTABLE_PROVIDERS, true)) {
            $errors[] = "{$where}.quote : citation permise seulement d'un document ".implode(', ', self::QUOTABLE_PROVIDERS);
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $open
     * @param  array<mixed>  $all
     * @return list<string>
     */
    private function correctionErrors(mixed $c, array $open, array $all, string $where): array
    {
        if (! is_array($c)) {
            return ["{$where} : tableau attendu"];
        }
        $field = $c['field'] ?? null;
        if (! in_array($field, self::CORRECTABLE, true)) {
            return ["{$where} : champ « ".(is_scalar($field) ? $field : '?').' » non corrigeable ('.implode(', ', self::CORRECTABLE).')'];
        }
        $value = $c['value'] ?? null;
        $ok = match ($field) {
            'occurred_on' => $this->isDay($value),
            'occurred_at_time' => is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1,
            'kind' => is_string($value) && AccidentKind::tryFrom($value) !== null && ! in_array($value, AccidentKind::HIDDEN, true),
            'commune_insee' => is_string($value) && preg_match('/^(\d{5}|2[AB]\d{3})$/', $value) === 1,
            default => is_int($value) && $value >= 0 && $value < 10000,
        };
        $errors = $ok ? [] : ["{$where} : valeur invalide pour {$field}"];
        $errors = [...$errors, ...$this->textErrors($c, $where)];

        return [...$errors, ...$this->citedErrors($c['sources'] ?? null, $open, $all, $where)];
    }

    private function isDay(mixed $v): bool
    {
        if (! is_string($v) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    private function hasDash(string $t): bool
    {
        return preg_match('/[\x{2014}\x{2013}]|--/u', $t) === 1;
    }
}
