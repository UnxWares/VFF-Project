<?php

namespace App\Http\Controllers\Api;

/**
 * Exemples de réponses de la documentation (Scramble) des points d'accès du matériel roulant,
 * tirés de la base (vff:import-materials du 19/11/2026) : BB 15000 et ligne de Lunéville à
 * Saint-Dié.
 */
final class MaterialExamples
{
    /** Une série telle que la renvoie la liste. */
    public const BB_15000 = [
        'slug' => 'bb-15000',
        'name' => 'BB 15000',
        'type' => 'locomotive_electrique',
        'type_label' => 'Locomotive électrique',
        'designations' => [
            'SNCF BB 15000',
            'SNCF Class BB 15000',
        ],
        'description' => 'série de 65 locomotives électriques de la SNCF',
        'builder' => 'Alstom, Matériel de Traction Électrique',
        'builders' => [
            [
                'name' => 'Alstom',
                'wikidata_id' => 'Q309084',
                'units' => null,
            ],
            [
                'name' => 'Matériel de Traction Électrique',
                'wikidata_id' => 'Q3300038',
                'units' => null,
            ],
        ],
        'operators' => [
            [
                'name' => 'SNCF',
                'wikidata_id' => 'Q13646',
                'from_year' => null,
                'to_year' => null,
                'units' => null,
            ],
        ],
        'in_service_from' => 1971,
        'in_service_to' => null,
        'units_built' => 65,
        'max_speed_kmh' => 160,
        'power_kw' => null,
        'electrification' => [
            [
                'label' => 'électrification ferroviaire 25 kV 50 Hz',
                'wikidata_id' => 'Q106530439',
            ],
        ],
        'gauge_mm' => 1435,
        'gauges' => [
            [
                'label' => 'écartement standard',
                'mm' => 1435,
                'wikidata_id' => 'Q1999572',
            ],
        ],
        'wheel_arrangement' => 'B\'B\'',
        'fleet_numbers' => '15001 - 15065',
        'image' => [
            'provider' => 'commons',
            'file' => 'File:BB 15025 + Corail en gare de Frénouville Cagny.JPG',
            'thumb_url' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/f/f6/BB_15025_%2B_Corail_en_gare_de_Fr%C3%A9nouville_Cagny.JPG/500px-BB_15025_%2B_Corail_en_gare_de_Fr%C3%A9nouville_Cagny.JPG',
            'display_url' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/f/f6/BB_15025_%2B_Corail_en_gare_de_Fr%C3%A9nouville_Cagny.JPG/1280px-BB_15025_%2B_Corail_en_gare_de_Fr%C3%A9nouville_Cagny.JPG',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:BB_15025_%2B_Corail_en_gare_de_Fr%C3%A9nouville_Cagny.JPG',
            'width' => 4928,
            'height' => 3264,
            'author' => 'Florian Fèvre',
            'license' => 'CC BY-SA 4.0',
            'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
            'rights_status' => 'open_license',
            'commercial_ok' => true,
            'share_alike' => true,
            'attribution_required' => true,
            'attribution' => 'Florian Fèvre, CC BY-SA 4.0, via Wikimedia Commons',
        ],
        'commons_category' => 'SNCF Class BB 15000',
        'commons_category_url' => 'https://commons.wikimedia.org/wiki/Category:SNCF_Class_BB_15000',
        'wikidata_id' => 'Q746572',
        'wikidata_url' => 'https://www.wikidata.org/wiki/Q746572',
        'wikipedia' => [
            'fr' => [
                'title' => 'BB 15000',
                'url' => 'https://fr.wikipedia.org/wiki/BB_15000',
            ],
            'en' => [
                'title' => 'SNCF Class BB 15000',
                'url' => 'https://en.wikipedia.org/wiki/SNCF_Class_BB_15000',
            ],
        ],
        'lines_count' => 4,
        'source' => 'wikidata',
        'provenance' => [
            'provider' => 'wikidata',
            'name' => 'Wikidata',
            'license' => 'CC0 1.0',
            'license_status' => 'verified',
        ],
        'links' => [
            'api' => 'https://vff-project.org/api/v1/materials/bb-15000',
            'page' => 'https://vff-project.org/materials/bb-15000',
        ],
    ];

    /** Extrait de `field_sources` (la fiche donne tous les champs renseignés). */
    public const BB_15000_FIELD_SOURCES = [
        [
            'field' => 'name',
            'source' => 'wikidata',
            'reference' => 'Q746572#sitelink:frwiki',
            'url' => 'https://www.wikidata.org/wiki/Q746572',
            'original' => null,
            'provider' => 'wikidata',
            'name' => 'Wikidata',
            'license' => 'CC0 1.0',
            'license_status' => 'verified',
        ],
        [
            'field' => 'type',
            'source' => 'wikidata',
            'reference' => 'Q746572#P279:Q625151',
            'url' => 'https://www.wikidata.org/wiki/Q746572',
            'original' => null,
            'provider' => 'wikidata',
            'name' => 'Wikidata',
            'license' => 'CC0 1.0',
            'license_status' => 'verified',
        ],
        [
            'field' => 'image',
            'source' => 'commons',
            'reference' => 'File:BB 15025 + Corail en gare de Frénouville Cagny.JPG',
            'url' => 'https://commons.wikimedia.org/wiki/File%3ABB_15025_%2B_Corail_en_gare_de_Fr%C3%A9nouville_Cagny.JPG',
            'original' => null,
            'provider' => 'commons',
            'name' => 'Wikimedia Commons',
            'license' => 'Licence propre à chaque fichier (CC BY, CC BY-SA, GFDL, domaine public…), enregistrée dans media.license',
            'license_status' => 'verified',
        ],
        [
            'field' => 'max_speed_kmh',
            'source' => 'wikidata',
            'reference' => 'Q746572#P2052',
            'url' => 'https://www.wikidata.org/wiki/Q746572',
            'original' => null,
            'provider' => 'wikidata',
            'name' => 'Wikidata',
            'license' => 'CC0 1.0',
            'license_status' => 'verified',
        ],
        [
            'field' => 'in_service_from',
            'source' => 'wikidata',
            'reference' => 'Q746572#P729',
            'url' => 'https://www.wikidata.org/wiki/Q746572',
            'original' => null,
            'provider' => 'wikidata',
            'name' => 'Wikidata',
            'license' => 'CC0 1.0',
            'license_status' => 'verified',
        ],
    ];

    /** Une ligne parcourue, dans la fiche de la série. */
    public const BB_15000_LINE = [
        'line' => [
            'slug' => 'rfn-67000',
            'code' => '067000',
            'name' => 'Ligne de Lunéville à St-Dié',
        ],
        'source' => 'wikipedia',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Lun%C3%A9ville_%C3%A0_Saint-Di%C3%A9&oldid=239088070',
        'confidence' => 'high',
        'era_year' => null,
        'from_date' => null,
        'to_date' => null,
        'notes' => null,
        'evidence' => [
            [
                'kind' => 'wikipedia_line_article',
                'article' => 'Ligne de Lunéville à Saint-Dié',
                'revid' => 239088070,
                'section' => 'Trafic > Matériel roulant',
                'quote' => 'Rames Corail tractées par des BB 15000, les trains Corail ne circulent plus depuis la circulation des TGV ;',
                'url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Lun%C3%A9ville_%C3%A0_Saint-Di%C3%A9&oldid=239088070',
            ],
        ],
        'provenance' => [
            'provider' => 'wikipedia',
            'name' => 'Wikipédia (FR)',
            'license' => 'CC BY-SA 4.0 pour les textes ; les faits (dates) ne sont pas protégés',
            'license_status' => 'verified',
        ],
    ];

    /** Une série, dans la liste du matériel d'une ligne. */
    public const LINE_LINK = [
        'material' => [
            'slug' => 'bb-15000',
            'name' => 'BB 15000',
            'type' => 'locomotive_electrique',
            'type_label' => 'Locomotive électrique',
            'builder' => 'Alstom, Matériel de Traction Électrique',
            'in_service_from' => 1971,
            'in_service_to' => null,
            'thumb_url' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/f/f6/BB_15025_%2B_Corail_en_gare_de_Fr%C3%A9nouville_Cagny.JPG/500px-BB_15025_%2B_Corail_en_gare_de_Fr%C3%A9nouville_Cagny.JPG',
        ],
        'source' => 'wikipedia',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Lun%C3%A9ville_%C3%A0_Saint-Di%C3%A9&oldid=239088070',
        'confidence' => 'high',
        'era_year' => null,
        'from_date' => null,
        'to_date' => null,
        'notes' => null,
        'evidence' => [
            [
                'kind' => 'wikipedia_line_article',
                'article' => 'Ligne de Lunéville à Saint-Dié',
                'revid' => 239088070,
                'section' => 'Trafic > Matériel roulant',
                'quote' => 'Rames Corail tractées par des BB 15000, les trains Corail ne circulent plus depuis la circulation des TGV ;',
                'url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Lun%C3%A9ville_%C3%A0_Saint-Di%C3%A9&oldid=239088070',
            ],
        ],
        'provenance' => [
            'provider' => 'wikipedia',
            'name' => 'Wikipédia (FR)',
            'license' => 'CC BY-SA 4.0 pour les textes ; les faits (dates) ne sont pas protégés',
            'license_status' => 'verified',
        ],
    ];

    /** 404 : série inconnue. */
    public const MATERIAL_NOT_FOUND = ['message' => 'No query results for model [App\\Models\\Material] bb-99999'];

    /** 422 : filtres invalides de la liste (messages de validation en anglais). */
    public const MATERIALS_INVALID = [
        'message' => 'The selected type is invalid. (and 1 more error)',
        'errors' => [
            'type' => ['The selected type is invalid.'],
            'to_year' => ['The to year field must be greater than or equal to 1990.'],
        ],
    ];

    /** 422 : filtre invalide du matériel d'une ligne. */
    public const LINE_MATERIALS_INVALID = [
        'message' => 'The selected min confidence is invalid.',
        'errors' => ['min_confidence' => ['The selected min confidence is invalid.']],
    ];
}
