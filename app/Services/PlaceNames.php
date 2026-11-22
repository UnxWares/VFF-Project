<?php

namespace App\Services;

/**
 * Normalisation et analyse des noms de lieux / libellés de tracés ferroviaires.
 *
 * Pourquoi : les sources nomment la même ligne de façons très différentes -
 * « Carhaix Morlaix » (archeoferroviaire), « Ligne de Carhaix à Morlaix »
 * (VFF), « Ligne de Lunéville à St-Dié » (SNCF), « Bully/Grenay » pour la gare
 * de Bully-Grenay… Pour rattacher les tracés aux lignes, il faut comparer des
 * toponymes, pas des chaînes.
 *
 *   - normalize()  : minuscules, sans accents ni ponctuation, St → saint ;
 *   - tokens()     : mots utiles (sans articles/prépositions) ;
 *   - significant(): sans les mots trop banals pour identifier un lieu
 *                    (saint, pont, port, ville, gare…) ;
 *   - parseLineName(): « Ligne de X à Y par Z » → [X, Y, Z] ;
 *   - parseLabel() : libellé brut d'un tracé → toponymes, annotations
 *                    (écartement, période, projet, tracé initial…) ;
 *   - lineName()   : construit « Ligne de X à Y » avec les élisions et
 *                    contractions françaises (d'Amagne, du Mans, aux Ifs).
 */
final class PlaceNames
{
    /** Mots vides (articles, prépositions) ignorés à la comparaison. */
    private const STOP = [
        'de', 'du', 'des', 'd', 'la', 'le', 'les', 'l', 'a', 'au', 'aux', 'en', 'sur', 'sous',
        'et', 'par', 'lez', 'les', 'via', 'entre', 'lès', 'y',
    ];

    /** Mots qui désignent un TYPE d'ouvrage, pas un lieu. */
    private const KIND = [
        'ligne', 'lignes', 'raccordement', 'raccordements', 'embranchement', 'embranchements',
        'voie', 'voies', 'mere', 'tramway', 'tramways', 'tram', 'antenne', 'bifurcation', 'bif',
        'trace', 'troncon', 'section', 'desserte', 'evitement', 'ceinture', 'boucle', 'deviation',
        'chemin', 'fer', 'cf', 'cfd', 'militaire', 'militaires', 'strategique', 'industrielle',
        'industriel', 'miniere', 'minier', 'minieres', 'reseau', 'service', 'initial', 'ancien',
        'liaison', 'saut', 'mouton', 'contournement', 'prolongement', 'pres', 'secteur',
    ];

    /** Mots trop banals pour identifier un lieu à eux seuls. */
    private const GENERIC = [
        'saint', 'sainte', 'notre', 'dame', 'port', 'pont', 'mont', 'bois', 'grand', 'grande',
        'petit', 'petite', 'haut', 'haute', 'bas', 'basse', 'neuf', 'neuve', 'vieux', 'vieil',
        'vieille', 'nord', 'sud', 'est', 'ouest', 'centre', 'central', 'centrale', 'ville', 'gare',
        'station', 'halte', 'quai', 'quais', 'plage', 'bains', 'chateau', 'moulin', 'val', 'fort',
        'camp', 'champ', 'mines', 'mine', 'usine', 'crb', 'triage', 'frontiere', 'local', 'locale',
        'etat', 'midi', 'plm', 'voyageurs', 'marchandises', 'maritime', 'rive', 'droite', 'gauche',
        'faisceau', 'garage', 'depot', 'zi', 'za', 'zone', 'puits', 'carriere', 'carrieres',
        'sucrerie', 'ouvrage', 'transbordement', 'aeroport', 'aerodrome', 'base', 'marche',
        'canal', 'ile', 'riviere', 'cote', 'forges', 'forge', 'usines', 'france', 'place',
    ];

    /**
     * Mots qui ouvrent un toponyme descriptif (« Dépôt de Reinange »,
     * « Ouvrage du Hackenberg ») et leur article pour les contractions.
     */
    private const PLACE_TYPES = [
        'dépôt' => 'le', 'dépot' => 'le', 'ouvrage' => 'l', 'transbordement' => 'le',
        'sucrerie' => 'la', 'usine' => 'l', 'carrière' => 'la', 'carrières' => 'les',
        'mine' => 'la', 'mines' => 'les', 'puits' => 'le', 'camp' => 'le', 'fort' => 'le',
        'base' => 'la', 'champ' => 'le', 'forêt' => 'la', 'château' => 'le', 'aérodrome' => 'l',
        'aéroport' => 'l', 'râperie' => 'la', 'centrale' => 'la', 'forges' => 'les',
        'scierie' => 'la', 'cimenterie' => 'la', 'distillerie' => 'la', 'laverie' => 'la',
        'raffinerie' => 'la', 'briqueterie' => 'la', 'briquetterie' => 'la', 'papeterie' => 'la',
        'papeteries' => 'les', 'port' => 'le', 'gare' => 'la', 'pointe' => 'la', 'col' => 'le',
        'clairière' => 'la', 'parc' => 'le', 'hôpital' => 'l', 'abbaye' => 'l', 'lac' => 'le',
        'frontière' => 'la', 'bois' => 'le', 'tunnel' => 'le', 'viaduc' => 'le', 'barrage' => 'le', 'zi' => 'la', 'za' => 'la',
    ];

    /** Libellés génériques : ne nomment pas une ligne précise. */
    private const GENERIC_LABELS = '/^(?:(?:le |la |l )?(?:trace|ancien trace|traces?) (?:initial|initiaux|primitif|provisoire|ancien)|ancien trace|traces? initi\w*|raccordements?|embranchements?|bifurcation|saut de mouton|evitement|ligne militaire|lignes militaires|ligne strategique|ligne industrielle|lignes industrielles|ligne agricole|ligne miniere|lignes minieres|voies? (?:industrielles?|portuaires?|minieres?|de service)|plan incline(?: n ?\d+)?|funiculaire|telepherique|projet(?: de trace)?(?: jamais realise)?|trace|voie|ligne|deviation|contournement|raccordement \w+|embranchement (?:militaire|industriel|minier|portuaire|carriere|graviere|papeteries|abattoirs|kaolins|zi|aeroport|aerodrome|marchandises)|raccordement (?:port|militaire|aerodrome)|embranchement gare \w+|trace (?:entre|avant|apres) .*\d{4}.*|troncon modifie .*|raccordement(?:s)? et embranchements?|garage|gares? et traces? initi\w*|modification .*|trace provisoire .*|traces? (?:de|du) .*(?:provisoire|initial))$/';

    public static function normalize(?string $s): string
    {
        if ($s === null) {
            return '';
        }
        $s = mb_strtolower(trim(self::nfc($s)));
        $s = strtr($s, ['œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss', '’' => ' ', "'" => ' ', '‘' => ' ', '`' => ' ']);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        $s = $ascii !== false ? $ascii : $s;
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        $s = preg_replace('/\b(st)\b/', 'saint', $s);
        $s = preg_replace('/\b(ste)\b/', 'sainte', $s);

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /** @return list<string> mots utiles (sans articles ni prépositions) */
    public static function tokens(?string $s): array
    {
        $out = [];
        foreach (explode(' ', self::normalize($s)) as $w) {
            if ($w !== '' && ! in_array($w, self::STOP, true)) {
                $out[] = $w;
            }
        }

        return $out;
    }

    /** @return list<string> mots qui identifient un lieu (sans type d'ouvrage ni mots banals) */
    public static function significant(?string $s): array
    {
        return array_values(array_filter(
            self::tokens($s),
            fn (string $w) => ! in_array($w, self::GENERIC, true) && ! in_array($w, self::KIND, true)
                && ! preg_match('/^\d+$/', $w) && strlen($w) > 1,
        ));
    }

    /**
     * Décompose un nom de ligne VFF/SNCF : « Ligne d'Amagne-Lucquy à Revigny »,
     * « Ligne de Paris-St-Lazare à Mantes-Station par Conflans-Ste-Honorine »,
     * « Raccordement de La Chapelle-St-Denis », « Voie-mère de Pont-à-Vendin à Violaines ».
     *
     * @return array{kind: string, places: list<string>}|null places = [X, Y, via…] ou [X]
     */
    public static function parseLineName(string $name): ?array
    {
        $n = trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $name));
        if (preg_match('/\bprès de\b/u', $n)) {
            return null; // nom dérivé (« Ligne près de X ») : pas un vrai nom de ligne
        }
        if (! preg_match("/^(.*?)\\s+(?:de\\s+|d['’]\\s*|du\\s+|des\\s+)(.+)$/u", $n, $m)) {
            return null;
        }
        $kind = mb_strtolower($m[1]);
        $rest = $m[2];
        $via = [];
        if (preg_match('/^(.+?)\s+par\s+(.+)$/u', $rest, $v)) {
            $rest = $v[1];
            $via = preg_split('/\s*(?:,|\set\s)\s*/u', $v[2]);
        }
        $parts = preg_split('/\s+(à|au|aux)\s+/u', $rest, 2, PREG_SPLIT_DELIM_CAPTURE);
        $places = array_map('trim', [$parts[0], $parts[2] ?? '']);
        // Les contractions ont mangé l'article : « du Puy » → Le Puy, « des Arcs » → Les Arcs,
        // et à l'arrivée « au Mans » → Le Mans, « aux Ifs » → Les Ifs (inverse de lineName()).
        $lower = mb_strtolower($n);
        if (str_starts_with($lower, $kind.' du ')) {
            $places[0] = 'Le '.$places[0];
        } elseif (str_starts_with($lower, $kind.' des ')) {
            $places[0] = 'Les '.$places[0];
        }
        if ($places[1] !== '' && in_array($parts[1] ?? '', ['au', 'aux'], true)) {
            $places[1] = ($parts[1] === 'au' ? 'Le ' : 'Les ').$places[1];
        }

        return ['kind' => $kind, 'places' => array_values(array_filter(array_merge($places, $via)))];
    }

    /**
     * Analyse le libellé brut d'un tracé (archeoferroviaire, OHM…).
     *
     * @return array{
     *   raw: string, clean: string, places: list<string>, pair: bool, junk: bool, generic: bool,
     *   network_label: bool, gauge: ?int, period: ?string, kind: ?string, kind_phrase?: ?string, flags: list<string>
     * }
     */
    public static function parseLabel(?string $raw): array
    {
        $raw = trim(self::nfc((string) $raw));
        $out = [
            'raw' => $raw, 'clean' => '', 'places' => [], 'pair' => false, 'junk' => false,
            'generic' => false, 'network_label' => false, 'gauge' => null, 'period' => null,
            'kind' => null, 'kind_phrase' => null, 'flags' => [],
        ];
        $s = $raw;
        // Libellés techniques sans valeur : horodatages, identifiants d'objets, saisies parasites.
        $s = preg_replace('/\s*\+\s*\d{14}-\w{5}/u', '', $s);
        if ($s === '' || preg_match('/^(\d{4}-\d{2}-\d{2}T[\d:]+Z?|\d{14}-\w{5})$/u', $s) || in_array($s, ['cdecde'], true)) {
            $out['junk'] = true;

            return $out;
        }

        $norm = self::normalize($raw);

        // Écartement explicite (ne jamais l'inventer : plusieurs valeurs ou « initialement » → inconnu).
        $gauges = [];
        if (preg_match('/\b(v60|0m60|0 60 m|0 60|60 cm|voie de 60|militaire 0m60)\b/', $norm) || preg_match('/V60\b/u', $raw)) {
            $gauges[600] = true;
        }
        if (preg_match('/\b(0m70|0 70 m|0 70|70 cm)\b/', $norm)) {
            $gauges[700] = true;
        }
        if (preg_match('/\b(voie metrique|a voie metrique|metrique|1 m)\b/', $norm) || preg_match('/\bVM\b/u', $raw)) {
            $gauges[1000] = true;
        }
        if (preg_match('/\b(voie normale|vn)\b/', $norm)) {
            $gauges[1435] = true;
        }
        if (count($gauges) === 1 && ! preg_match('/\b(initialement|puis|a l origine|origine)\b/', $norm)) {
            $out['gauge'] = array_key_first($gauges);
        }

        // Période (lignes militaires).
        if (preg_match('/\b(14 18|14 18|15 18|1914|1915|1916|1917|1918)\b/', $norm) || preg_match('/14[-\/—–]18|15\/18/u', $raw)) {
            $out['period'] = '1914-1918';
        } elseif (preg_match('/\b(39 45|1939|1940|1943|1944)\b/', $norm)) {
            $out['period'] = '1939-1945';
        } elseif (preg_match('/\bmil 30 40\b/', $norm)) {
            $out['period'] = '1930-1940';
        }

        // Nature particulière de l'installation.
        foreach ([
            'telepherique' => 'téléphérique', 'aerien' => 'téléphérique', 'funiculaire' => 'funiculaire',
            'plan incline' => 'plan incliné', 'voie miniere' => 'minière', 'ligne miniere' => 'minière',
            'mine' => 'minière', 'mines' => 'minière', 'puits' => 'minière',
            'lignes minieres' => 'minière', 'forestiere' => 'forestière', 'waldeisenbahn' => 'forestière',
            'waldbahn' => 'forestière', 'betteravier' => 'betteravière', 'betteraviere' => 'betteravière',
            'voie industrielle' => 'industrielle', 'tramway' => 'tramway', 'elrt' => 'tramway', 'telb' => 'tramway',
        ] as $needle => $kind) {
            if (preg_match('/\\b'.$needle.'\\b/', $norm)) {
                $out['kind'] = $kind;
                break;
            }
        }

        // Statut du tracé.
        foreach ([
            'projet' => 'projet', 'jamais realise' => 'projet', 'inacheve' => 'inacheve',
            'trace initial' => 'trace_initial', 'ancien trace' => 'trace_initial', 'trace jusqu' => 'trace_initial',
            'avant 1898' => 'trace_initial', 'existant' => 'existant', 'trace incertain' => 'incertain',
            'trace approximatif' => 'incertain', 'trace putatif' => 'incertain', 'trace inconnu' => 'incertain',
            'tracé incertain' => 'incertain',
        ] as $needle => $flag) {
            if (str_contains($norm, $needle) && ! in_array($flag, $out['flags'], true)) {
                $out['flags'][] = $flag;
            }
        }

        $s = preg_replace('/(?<=\S):(?=\S)/u', '/', $s); // « Buzancy:Bar » = « Buzancy/Bar »

        // Nettoyage : notes entre parenthèses, descriptions après « ; », annotations de période/écartement.
        $s = preg_replace('/\s*\([^)]*\)?/u', ' ', $s);
        $s = preg_replace('/\s*;.*$/u', '', $s);
        $s = preg_replace('/\b(Mil\s*30-40|V60|VN|vn|VM|ve|vei)\b/u', ' ', $s);
        $s = preg_replace('/\b(\d\d\s*[-\/—–]\s*\d\d)\b/u', ' ', $s);
        $s = preg_replace('/\b(ligne|lignes)\s+(anglaise|allemande|belge|américaine|militaire\s*\d*)\b/iu', ' ', $s);
        $s = preg_replace('/(?<=\S)\s+(ligne militaire anglaise|réseau militaire|ligne militaire)\s*$/iu', ' ', $s);
        $s = preg_replace('/\s+-\s+Embranchement\s*$/u', '', $s);
        $s = preg_replace("/\\s+(?:jusqu['’]\\S*|à partir|depuis|avant|après|vers)\\b[^\\d]*\\d{4}.*$/u", '', $s);
        $s = preg_replace('/\s+(?:n°\s*)?\d+\s*$/u', '', $s); // « Champ de tir de Kergroix 6 », « plan incliné n°2 »
        $s = preg_replace('/\s*\b\d{4}\s*[\/-]\s*\d{4}\b/u', '', $s); // « Condé Guignicourt 1905/1914 »
        $s = preg_replace('/^[A-Z]{2,6}\s*\[[^\]]*\]\s*-\s*/u', '', $s); // « CFSO [TB]-… », « EC [SJ]-… »
        $s = preg_replace('/^[A-Z]{2,6}\s+-\s+/u', '', $s); // « CB - Peyrehorade - Sauveterre »
        $s = preg_replace('/\s+[-—]\s+/u', ' ', $s); // « Brest - port militaire » (jamais de tiret long affiché)
        $s = preg_replace('/\s*-\s*$/u', '', $s);

        // Préfixes de réseaux / numérotations : « EC [SJ]-Tracé de… », « CFD [SC]-… », « TVT : … », « MIDI [N°41]-… ».
        $s = preg_replace('/^(?:EC|CFD|MIDI|PO|PLM|EST|NORD|OUEST)\s*\[[^\]]*\]\s*-\s*/u', '', $s);
        $s = preg_replace('/^[A-Z]{2,5}\s*:\s*/u', '', $s);
        $network = null;
        if (preg_match('/^(ELRT|TELB)\s+(\w+)\s+(.*)$/u', $s, $m)) {
            $network = $m[1].' '.$m[2];
            $s = $m[3];
            $out['kind'] = 'tramway';
        }
        if (preg_match('/^(.+?)\s+:\s+(.+)$/u', $s, $m)) {
            // « Nantes Pornic : tracé initial », « Dechy : raccordement… », « Couvron : embranchement aérodrome militaire »
            $right = self::normalize($m[2]);
            if (preg_match('/^(embranchement|raccordement|antenne|desserte)/', $right) && ! preg_match('/^(embranchement|raccordement)/', self::normalize($m[1]))) {
                $right = trim(preg_split('/\s+(?:probablement|construit|construits|ouvert|ouverte|existence|attest|exploit|fermé|fermée)\b/u', $m[2])[0]);
                $s = self::ucfirst($right).' '.self::de(trim($m[1]));
            } else {
                $s = $m[1];
            }
        }
        $s = preg_replace('/\s+(tracé initial|tracé jusqu.*|tracé.*\d{4}.*)$/iu', '', $s);
        $s = preg_replace('/\s*:\s*$/u', '', $s);
        // « Tracé entre X et Y », « Tracé de X à Y », « Tracé de l'embranchement de X »
        if (preg_match('/^Tracé\s+entre\s+(.+?)\s+et\s+(.+)$/iu', $s, $m)) {
            $out['places'] = [trim($m[1]), trim($m[2])];
        } elseif (preg_match('/^Tracé\s+de\s+(.+?)\s+à\s+(.+)$/iu', $s, $m)) {
            $out['places'] = [trim($m[1]), self::titleSaint(trim($m[2]))];
        } elseif (preg_match("/^Tracé\\s+(?:de\\s+l['’]|du\\s+|de\\s+la\\s+)(.+)$/iu", $s, $m)) {
            $s = self::ucfirst(trim($m[1]));
        }
        $s = trim(preg_replace('/\s+/u', ' ', $s));
        if ($network) {
            $out['flags'][] = 'reseau:'.$network;
        }

        // Listes « A + B + C » : réseau de plusieurs embranchements sous un seul libellé.
        if (str_contains($s, '+')) {
            $items = array_values(array_filter(array_map('trim', explode('+', $s))));
            $out['flags'][] = 'liste';
            $s = $items[0] ?? $s;
        }

        if (mb_strlen($s) > 70) {
            $s = trim(preg_split('/\s+(?:par lequel|par laquelle|qui|dont|où|abandonné|abandonnée|pendant|suite)\b|,\s/u', $s)[0]);
        }
        if (mb_strlen($s) > 90) {
            $s = trim(mb_substr($s, 0, mb_strrpos(mb_substr($s, 0, 90), ' ') ?: 90));
        }
        $cleanNorm = self::normalize($s);
        if ($cleanNorm === '') {
            $out['junk'] = true;

            return $out;
        }
        if (preg_match(self::GENERIC_LABELS, $cleanNorm)) {
            $out['generic'] = true;
        }
        // Libellés de réseau (« Bassin Minier Nord - Lignes minières », « Réseau du front belge »).
        if (preg_match('/\b(bassin minier|reseau du front|embranchements militaires|lignes minieres)\b/', $cleanNorm)) {
            $out['network_label'] = true;
        }

        // « Anor frontière », « frontière Chimay » : la frontière est un lieu.
        $s = preg_replace('/(^|\s)frontière(\s|$)/u', '$1Frontière$2', $s);

        // Mot de type en tête (« Embranchement Harbouey », « Antenne Hessange ») ou en queue
        // (« Arras raccordement », « Taye Urbès téléphérique ») : « Embranchement de Harbouey ».
        $s = self::ucfirst($s);
        $rest = $s;
        // Libellé déjà au format « Ligne de X à Y » (OSM, OHM, Wikidata).
        if (! $out['places'] && preg_match('/^(Ligne|Chemin de fer|Tramway|Raccordement|Voie-mère|Voie|Embranchement)\b.*\s(à|au|aux)\s/u', $s)
            && ($pl = self::parseLineName($s)) && count($pl['places']) >= 2) {
            $out['places'] = $pl['places'];
        }
        if (! $out['places'] && preg_match(self::KIND_HEAD, $s, $m)) {
            $out['kind_phrase'] = $m[1];
            // « du Cap-Ferret » = de + Le Cap-Ferret, « des Arcs » = de + Les Arcs.
            $rest = ['du' => 'Le ', 'des' => 'Les '][$m[2]] ?? '';
            $rest .= $m[3];
        } elseif (! $out['places'] && preg_match(self::KIND_TAIL, $s, $m) && self::chunks($m[1])
            && ! preg_match(self::KIND_HEAD_WORD, $m[1])) {
            $out['kind_phrase'] = $m[2];
            $rest = $m[1];
        }
        if (! $out['places']) {
            $out['places'] = self::chunks($rest);
        }
        // « Morhange-Gare Ville » : la seconde gare est Morhange-Ville.
        if (count($out['places']) === 2 && in_array($out['places'][1], ['Ville', 'Gare', 'Port', 'Quai', 'Centre'], true)
            && preg_match('/^(.+)-(Gare|Ville|Port|Quai|Centre)$/u', $out['places'][0], $m)) {
            $out['places'][1] = $m[1].'-'.$out['places'][1];
        }
        $out['pair'] = count($out['places']) >= 2 && count($out['places']) <= 4;
        $out['clean'] = self::ucfirst($s);

        return $out;
    }

    /**
     * Mot de type d'ouvrage en tête de libellé, suivi de toponymes, avec ou sans
     * préposition : « Embranchement Harbouey », « Embranchement de Harbouey »
     * (forme produite par « Tracé de l'embranchement de Harbouey »).
     */
    private const KIND_HEAD = '/^(Waldeisenbahn|Transbordeur|Déviation|Embranchements?|Raccordements?|Antenne|Evitement|Évitement|Contournement|Transbordement|Bifurcation|Prolongement|Funiculaire|Téléphérique|Plan incliné|Tramway|Ligne|Lignes)\s+(?:(de|du|des)\s+|d[\'’]\s*)?(\p{Lu}.*)$/u';

    private const KIND_HEAD_WORD = '/^(Embranchements?|Raccordements?|Antenne|Evitement|Évitement|Contournement|Transbordement|Bifurcation|Prolongement|Funiculaire|Téléphérique|Plan|Tramway|Ligne|Lignes)\b/u';

    /** Mot de type d'ouvrage en queue de libellé, précédé de toponymes. */
    private const KIND_TAIL = '/^(.+?)\s+((?:raccordements?|embranchements?|transbordement|téléphérique|funiculaire|port|dépôt|laverie|garage|puits|carrière|carrières|usine|sucrerie)(?:\s.*)?)$/u';

    /**
     * Nom propre d'une ligne à partir d'un libellé analysé, selon la catégorie
     * (lines.network) : « Ligne de X à Y », « Ligne militaire de X à Y (1914-1918) »,
     * « Embranchement de X », « Téléphérique de X à Y », sinon le libellé nettoyé.
     *
     * @param  array<string, mixed>  $p  résultat de parseLabel()
     */
    public static function buildName(string $network, array $p): string
    {
        $places = $p['places'];
        $kindPhrase = $p['kind_phrase'] ?? null;
        $isProper = count($places) === 1 && preg_match('/bahn$/iu', $places[0]);
        if ($kindPhrase && $places && in_array(mb_strtolower($kindPhrase), ['ligne', 'lignes'], true)) {
            $kindPhrase = null;
        }

        if ($kindPhrase && $places) {
            $kp = $kindPhrase === 'Waldeisenbahn' ? 'Chemin de fer forestier' : self::ucfirst($kindPhrase);
            if ($network === 'militaire' && preg_match('/^(Embranchement|Raccordement|Antenne|Evitement|Évitement)s?$/u', $kp)) {
                $kp .= str_ends_with($kp, 's') ? ' militaires' : ' militaire';
            }
            $name = count($places) === 1
                ? $kp.' '.self::de(self::placeName($places[0]))
                : $kp.' '.self::de(self::placeName($places[0])).' '.self::a(self::placeName(end($places)));
        } elseif ($p['pair']) {
            $name = self::lineName(self::prefix($network, $p['kind'] ?? null), $places);
        } elseif (count($places) === 1 && ! $isProper && self::normalize($places[0]) === self::normalize($p['clean'])) {
            $name = self::prefix($network, $p['kind'] ?? null, true).' '.self::de(self::placeName($places[0]));
        } else {
            $name = self::ucfirst($p['clean']);
        }

        foreach ($p['flags'] as $flag) {
            if (str_starts_with($flag, 'reseau:')) {
                $name .= ' ('.substr($flag, 7).')';
            }
        }
        if ($network === 'militaire' && $p['period'] && ! str_contains($name, substr($p['period'], 0, 4))) {
            $name .= ' ('.$p['period'].')';
        }

        return $name;
    }

    /**
     * Nom dérivé pour un tracé sans nom ou au nom générique, d'après la commune
     * la plus proche : « Embranchement militaire de Metzervisse », « Voie
     * industrielle près de Hautmont », « Lignes minières du Bassin minier du
     * Nord (secteur de Lens) ».
     *
     * @param  array<string, mixed>|null  $p  libellé analysé (null = sans nom)
     */
    public static function derivedName(string $network, ?array $p, string $place, int $lengthM = 0): string
    {
        $place = self::placeName($place);
        $period = $p['period'] ?? null;
        if ($p && ($p['network_label'] ?? false)) {
            $label = $p['clean'];
            if (preg_match('/bassin minier/iu', $label)) {
                $label = 'Lignes minières du Bassin minier du Nord';
            } elseif (preg_match('/^réseau du front belge/iu', $label)) {
                $label = 'Réseau militaire du front belge';
            } elseif (preg_match('/^embranchements/iu', $label)) {
                return $label.' '.self::de($place).($period ? ' ('.$period.')' : '');
            } elseif (count($p['places']) === 1 && self::normalize($p['places'][0]) === self::normalize($label)) {
                $label = 'Réseau '.self::de(self::placeName($p['places'][0]));
            } elseif ($p['pair']) {
                $label = self::buildName($network, array_merge($p, ['flags' => []]));
            }
            $name = $label.' (secteur '.self::de($place).')';
        } elseif ($p && ! ($p['junk'] ?? false)) {
            $label = self::ucfirst($p['clean']);
            if (mb_strlen($label) > 40) {
                $label = 'Ancien tracé'; // « Modification suite à la destruction du tunnel de Perthes »
            }
            $near = preg_match('/^(lignes?|voies?|trac[ée]s?|ancien|plan|funiculaire|t[ée]l[ée]ph[ée]rique)\b/iu', $label) ? ' près '.self::de($place) : ' '.self::de($place);
            $name = $label.$near;
        } else {
            $name = match ($network) {
                'militaire' => 'Ligne militaire près '.self::de($place),
                'particulier' => 'Voie industrielle près '.self::de($place),
                'secondaire' => 'Ligne secondaire près '.self::de($place),
                default => ($lengthM && $lengthM < 3000 ? 'Embranchement' : 'Ligne').' près '.self::de($place),
            };
        }
        if ($network === 'militaire' && $period && ! str_contains($name, substr($period, 0, 4))) {
            $name .= ' ('.$period.')';
        }

        return $name;
    }

    /** Préfixe de nom selon la catégorie et la nature de l'installation. */
    private static function prefix(string $network, ?string $kind, bool $single = false): string
    {
        $byKind = [
            'téléphérique' => 'Téléphérique', 'funiculaire' => 'Funiculaire', 'plan incliné' => 'Plan incliné',
            'tramway' => 'Tramway', 'forestière' => 'Chemin de fer forestier', 'minière' => 'Ligne minière',
            'betteravière' => 'Ligne betteravière', 'industrielle' => 'Voie industrielle',
        ];
        if ($kind && isset($byKind[$kind]) && ($network !== 'principal' || $kind === 'tramway')) {
            return $byKind[$kind];
        }

        return match ($network) {
            'militaire' => 'Ligne militaire',
            'particulier' => $single ? 'Voie industrielle' : 'Ligne',
            default => 'Ligne',
        };
    }

    /**
     * Découpe « Saint-Malo Cancale/La Houle » en toponymes : les espaces
     * séparent les lieux, sauf après un article (« La Houle ») ou à l'intérieur
     * d'un toponyme descriptif (« Dépôt de Reinange », « Ouvrage du Hackenberg »).
     * Un mot en minuscules hors de ces cas signale un libellé descriptif (→ []).
     *
     * @return list<string>
     */
    public static function chunks(string $s): array
    {
        $s = preg_replace('/^(?:Ligne|Lignes)\s+/u', '', trim($s));
        $words = preg_split('/\s+/u', $s);
        $chunks = [];
        $i = 0;
        $n = count($words);
        while ($i < $n) {
            $w = $words[$i];
            if ($w === '' || $w === '-' || $w === '-' || $w === '-') {
                $i++;

                continue;
            }
            $first = mb_substr($w, 0, 1);
            if (mb_strtoupper($first) !== $first || is_numeric($first)) {
                return []; // mot en minuscules isolé : libellé descriptif
            }
            $chunk = $w;
            $lw = mb_strtolower($w);
            // Article capitalisé : « La Houle », « Le Huga », « Les Ifs », « L’Hôpital-du-Gros-Bois » (déjà collé),
            // y compris après « / » ou « - » (« Cancale/La Houle », « Doyet-La Presle »), et coquilles
            // « Vic-sur Aisne », « Berry-au Bac ».
            if ((in_array($lw, ['le', 'la', 'les'], true) || preg_match('/[-\/](le|la|les|sur|au|aux|en|de|du|lès|sous)$/u', $lw)) && $i + 1 < $n) {
                $chunk .= ' '.$words[++$i];
            } elseif (isset(self::PLACE_TYPES[$lw]) && $lw !== 'frontière' && $i + 1 < $n) {
                // Toponyme descriptif : absorbe « de/du/des/d’ X » et les compléments en minuscules,
                // ou un nom propre accolé (« Carrière Lamarque », « Transbordement Boulange »).
                $nf = mb_substr($words[$i + 1], 0, 1);
                if (mb_strtoupper($nf) === $nf && ! is_numeric($nf)) {
                    $chunk .= ' '.$words[++$i];
                } else {
                    while ($i + 1 < $n) {
                        $next = $words[$i + 1];
                        $nf = mb_substr($next, 0, 1);
                        if (mb_strtolower($nf) !== $nf || is_numeric($nf)) {
                            break;
                        }
                        $chunk .= ' '.$next;
                        $i++;
                        // Après une préposition / un article, le mot suivant appartient au toponyme.
                        while (preg_match('/^(de|du|des|sur|en|la|le|les)$/u', $words[$i]) && $i + 1 < $n) {
                            $chunk .= ' '.$words[++$i];
                        }
                    }
                }
            }
            $chunks[] = $chunk;
            $i++;
        }

        return $chunks;
    }

    /** « Bully/Grenay » → « Bully-Grenay » (gare desservant deux lieux, forme officielle). */
    public static function placeName(string $place): string
    {
        $place = trim(preg_replace('/\s*\/\s*/u', '-', $place));
        $place = preg_replace('/-(sur|au|aux|en|de|du|lès|sous) (?=\p{Lu})/u', '-$1-', $place); // « Vic-sur Aisne »

        return self::titleSaint($place);
    }

    /** « de » + lieu avec élision et contraction : d'Amagne, du Mans, des Arcs, de La Ferté, du dépôt… */
    public static function de(string $place): string
    {
        return self::preposition('de', $place);
    }

    /** « à » + lieu : au Mans, aux Ifs, à La Rochelle, à l'ouvrage… */
    public static function a(string $place): string
    {
        return self::preposition('à', $place);
    }

    private static function preposition(string $prep, string $place): string
    {
        $place = trim($place);
        $words = preg_split('/\s+/u', $place, 2);
        $lw = mb_strtolower($words[0]);
        if ($lw === 'frontière' && ! isset($words[1])) {
            return $prep.' la frontière';
        }
        // Toponyme descriptif : « Dépôt de Reinange » → du dépôt de Reinange.
        if (isset(self::PLACE_TYPES[$lw]) && isset($words[1])) {
            $article = self::PLACE_TYPES[$lw];
            $lower = preg_match('/^\p{Lu}{2,}\b/u', $place) ? $place : mb_strtolower(mb_substr($place, 0, 1)).mb_substr($place, 1);

            return match ($article) {
                'le' => ($prep === 'de' ? 'du ' : 'au ').$lower,
                'les' => ($prep === 'de' ? 'des ' : 'aux ').$lower,
                'la' => $prep.' la '.$lower,
                // Article élidé : « de l'ouvrage », « à l'ouvrage » (et non « d'ouvrage »).
                default => ($prep === 'de' ? "de l'" : "à l'").$lower,
            };
        }
        if (preg_match('/^Le\s+(.+)$/u', $place, $m)) {
            return ($prep === 'de' ? 'du ' : 'au ').$m[1];
        }
        if (preg_match('/^Les\s+(.+)$/u', $place, $m)) {
            return ($prep === 'de' ? 'des ' : 'aux ').$m[1];
        }
        if ($prep === 'de' && preg_match('/^[AEIOUYÉÈÊÎÔÂÀaeiouyéèêîôâ]/u', $place)) {
            return "d'".$place;
        }

        return $prep.' '.$place;
    }

    /**
     * Nom propre d'une ligne entre toponymes : « Ligne de X à Y », « … par Z ».
     *
     * @param  list<string>  $places
     */
    public static function lineName(string $prefix, array $places): string
    {
        $places = array_map([self::class, 'placeName'], $places);
        $from = array_shift($places);
        $to = array_pop($places);
        if ($from === null) {
            return $prefix;
        }
        if ($to === null) {
            return $prefix.' '.self::de($from); // un seul lieu : « Raccordement de La Chapelle »
        }
        $name = $prefix.' '.self::de($from).' '.self::a($to);
        if ($places) {
            $last = array_pop($places);
            $name .= ' par '.($places ? implode(', ', $places).' et ' : '').$last;
        }

        return $name;
    }

    /** Forme composée Unicode (certains libellés sources sont en NFD : « e » + accent combinant). */
    public static function nfc(string $s): string
    {
        return class_exists(\Normalizer::class) ? (\Normalizer::normalize($s, \Normalizer::FORM_C) ?: $s) : $s;
    }

    public static function ucfirst(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
    }

    /** « Saint Sulpice » → « Saint-Sulpice » (forme toponymique). */
    private static function titleSaint(string $s): string
    {
        return preg_replace('/\b(Saint|Sainte)\s+(?=\p{Lu})/u', '$1-', $s);
    }
}
