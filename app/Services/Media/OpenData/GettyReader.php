<?php

namespace App\Services\Media\OpenData;

/**
 * Lit les vues ferroviaires françaises du Getty Open Content (data.getty.edu, pas de robots.txt : 404 le 22/11/2026).
 *
 * 1. Une requête SPARQL (`https://data.getty.edu/museum/collection/sparql`) liste les photographies dont l'étiquette porte
 *    un numéro d'inventaire d'un album ferroviaire (`albums`, ex. 84.XO.401 : Baldus, Paris à Lyon et à la Méditerranée) ou
 *    un mot français (`keywords`).
 * 2. Une notice Linked Art (JSON-LD) par objet, à 2 s d'écart : titre, date, auteur, lieu représenté, culture, image IIIF,
 *    droits de l'objet (licence CC0 et « Public Domain » exigées : sinon la ligne est écrite sans `rights_uri` et refusée).
 *
 * Les lignes ont des noms de colonnes plats, que lit OpenDataNormalizer. Aucune image n'est téléchargée : l'adresse IIIF
 * (media.getty.edu) est seulement composée.
 */
final class GettyReader
{
    public const SPARQL = 'https://data.getty.edu/museum/collection/sparql';

    public function __construct(private readonly OpenDataFetcher $http) {}

    /**
     * @param  array<string, mixed>  $dataset
     * @return list<array<string, mixed>>
     */
    public function read(array $dataset, ?int $limit = null): array
    {
        $rows = [];
        foreach ($this->candidates($dataset) as $uri => $label) {
            if ($limit !== null && count($rows) >= $limit) {
                break;
            }
            $row = self::row($this->http->getJson($uri, ['accept' => 'application/ld+json']), $label);
            $rows[] = $row + ['country' => self::country($row, $dataset['albums'])];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return array<string, string> adresse de l'objet => étiquette
     */
    public function candidates(array $dataset): array
    {
        $json = $this->http->getJson(self::SPARQL, ['accept' => 'application/sparql-results+json', 'query' => ['query' => self::query($dataset)]]);
        $out = [];
        foreach ((array) ($json['results']['bindings'] ?? []) as $b) {
            $out[(string) $b['o']['value']] = (string) $b['l']['value'];
        }
        ksort($out);

        return $out;
    }

    /** @param  array<string, mixed>  $dataset */
    public static function query(array $dataset): string
    {
        $albums = implode('|', array_map(fn (string $a): string => str_replace('.', '\\\\.', $a), $dataset['albums']));

        return 'PREFIX crm: <http://www.cidoc-crm.org/cidoc-crm/> PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#> '
            .'SELECT ?o ?l WHERE { ?o a crm:E22_Human-Made_Object ; rdfs:label ?l ; crm:P2_has_type <http://vocab.getty.edu/aat/300046300> . '
            .'FILTER(REGEX(STR(?l), "\\\\((?:'.$albums.')\\\\.[0-9]+\\\\)$") || REGEX(STR(?l), "\\\\b('.$dataset['keywords'].')\\\\b", "i")) } ORDER BY ?l';
    }

    /**
     * Pays de la vue : « France » si le lieu représenté finit par « France » ; sans lieu, si l'objet est une planche d'un album
     * de lignes françaises de la liste (les albums de Collard et du Bourbonnais n'ont pas de lieu représenté) ; sinon le dernier
     * élément du lieu, ou rien.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $albums
     */
    public static function country(array $row, array $albums): ?string
    {
        $place = trim((string) ($row['place'] ?? ''));
        if ($place !== '') {
            return trim((string) preg_replace('/^.*,\s*/u', '', $place));
        }
        foreach ($albums as $album) {
            if (str_starts_with((string) ($row['accession'] ?? ''), $album.'.')) {
                return 'France';
            }
        }

        return null;
    }

    /**
     * Ligne plate d'une notice Linked Art.
     *
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    public static function row(array $o, string $label): array
    {
        $accession = null;
        $title = null;
        $group = null;
        foreach ((array) ($o['identified_by'] ?? []) as $n) {
            $classes = array_column((array) ($n['classified_as'] ?? []), '_label');
            if (in_array('Accession Number', $classes, true)) {
                $accession = $n['content'] ?? null;
            }
            if (($n['type'] ?? '') === 'Name' && ($n['_label'] ?? '') === 'Preferred Title') {
                $title = $n['content'] ?? null;
            }
            if (in_array('Group Title', $classes, true)) {
                $group = $n['content'] ?? null;
            }
        }
        $title ??= preg_replace('/\s*\([^()]*\)$/', '', $label);

        $producer = null;
        $date = null;
        $production = (array) ($o['produced_by'] ?? []);
        foreach ((array) ($production['referred_to_by'] ?? []) as $r) {
            if (($r['_label'] ?? '') === 'Artist/Maker (Producer) Name') {
                $producer = $r['content'] ?? null;
            }
        }
        foreach ((array) ($production['timespan']['identified_by'] ?? []) as $n) {
            if (($n['_label'] ?? '') === 'Dates') {
                $date = $n['content'] ?? null;
            }
        }

        $place = null;
        foreach ((array) ($o['shows'] ?? []) as $shown) {
            foreach ((array) ($shown['referred_to_by'] ?? []) as $r) {
                if (($r['_label'] ?? '') === 'Place Depicted') {
                    $place = $r['content'] ?? null;
                }
            }
        }
        $culture = null;
        foreach ((array) ($o['referred_to_by'] ?? []) as $r) {
            if (($r['_label'] ?? '') === 'Culture Statement') {
                $culture = $r['content'] ?? null;
            }
        }

        // Droits de l'objet : « License for Collection Metadata », classée CC0 et affichée « Public Domain ».
        $rights = null;
        $display = null;
        foreach ((array) ($o['subject_to'] ?? []) as $right) {
            foreach ((array) ($right['classified_as'] ?? []) as $c) {
                if (str_starts_with((string) ($c['id'] ?? ''), 'http://creativecommons.org/')) {
                    $rights = $c['id'];
                }
            }
            foreach ((array) ($right['identified_by'] ?? []) as $n) {
                if (($n['_label'] ?? '') === 'License Display Name') {
                    $display = $n['content'] ?? null;
                }
            }
        }

        $imageUuid = null;
        foreach ((array) ($o['representation'] ?? []) as $rep) {
            if (preg_match('#media\.getty\.edu/iiif/image/([0-9a-f-]{36})/#', (string) ($rep['id'] ?? ''), $m) === 1) {
                $imageUuid = $m[1];
                break;
            }
        }
        $homepage = null;
        foreach ((array) ($o['subject_of'] ?? []) as $s) {
            if (str_starts_with((string) ($s['id'] ?? ''), 'https://www.getty.edu/art/collection/')) {
                $homepage = $s['id'];
            }
        }
        $album = null;
        foreach ((array) ($o['part_of'] ?? []) as $p) {
            $album = preg_replace('/\s*\([^()]*\)$/', '', (string) ($p['_label'] ?? ''));
        }

        return [
            'accession' => $accession,
            'uuid' => preg_match('#/object/([0-9a-f-]{36})$#', (string) ($o['id'] ?? ''), $m) === 1 ? $m[1] : null,
            'title' => $title,
            'group_title' => $group,
            'album' => $album,
            'date' => $date,
            'author' => $producer,
            'place' => $place,
            'culture' => $culture,
            'rights_uri' => $rights,
            'rights_display' => $display,
            'image' => $imageUuid === null ? null : 'https://media.getty.edu/iiif/image/'.$imageUuid.'/full/1200,/0/default.jpg',
            'thumb' => $imageUuid === null ? null : 'https://media.getty.edu/iiif/image/'.$imageUuid.'/full/400,/0/default.jpg',
            'homepage' => $homepage,
        ];
    }
}
