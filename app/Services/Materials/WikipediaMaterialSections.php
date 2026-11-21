<?php

namespace App\Services\Materials;

/**
 * Lecture du wikitexte des articles Wikipédia pour relier matériel et lignes.
 *
 * Seuls comptent les LIENS internes ([[Cible|texte]]) posés dans des sections qui parlent du
 * matériel engagé : « Matériel roulant », « Exploitation », « Desserte »… pour un article de
 * ligne ; « Utilisation », « Services », « Affectations », « Carrière »… pour un article de
 * matériel. Un lien désigne un article précis (donc une série ou une ligne précise) : aucun
 * rapprochement de noms approximatif. La phrase qui porte le lien est gardée comme preuve.
 *
 * Prudence : une phrase au futur ou au conditionnel (« devraient remplacer », « prévu »), ou qui
 * nie la circulation (« n'a jamais circulé », « interdit »), ne crée pas de lien.
 *
 * Une phrase qui reprend un sujet déjà posé pour dire qu'il a été remplacé (« Ce train a été remplacé
 * par le TGV ») parle d'un AUTRE matériel que celui qui est cité : le lien est gardé mais marqué
 * `refers_back`, la phrase précédente est ajoutée à la citation pour qu'elle se lise seule, et le
 * lien ne peut pas être rangé en « source directe ».
 *
 * Une série citée comme terme de comparaison (« un confort similaire aux autorails X 73500 »,
 * « comparable aux », « à l'image des », « plus confortable que les », « dérivé des »), ou un
 * article cité pour ce qui en dérive (« les locomotives [[Ceinture]] n° 21 à 35 qui en sont
 * dérivées »), n'a pas roulé pour autant sur la ligne : ce lien-là n'est pas gardé (isCompared()).
 *
 * Doute (doubt()) : une phrase qui parle d'une commande, d'un essai, d'une circulation
 * exceptionnelle, d'une présentation, d'une préservation, d'une simple possibilité, d'un
 * acheminement vers un atelier, d'un détournement, d'une restriction à une autre portion ou
 * d'une pièce reprise d'une série ne prouve pas que la série a roulé en service sur la ligne,
 * sans prouver le contraire : le lien est gardé avec sa famille de doute (`doubt`), et sa
 * fiabilité tombe à `low` si aucune autre preuve ne le confirme. Un lien posé dans une note
 * ({{Note|…}}) ou dans un passage marqué sans source ({{Refnec|…}}) est traité de même
 * (`footnote`) : la phrase citée ne le montre pas.
 */
final class WikipediaMaterialSections
{
    /** Sections d'un article de LIGNE qui décrivent le matériel engagé ou l'exploitation. */
    public const LINE_SECTIONS = '/^(?:mat[ée]riels?(?:\s+(?:roulants?|utilis[ée]s?|moteurs?|remorqu[ée]s?|ferroviaires?|voyageurs))?|exploitation|desserte|dessertes|trafic|services?\s+ferroviaires?|circulations?)\b/iu';

    /** Sections d'un article de MATÉRIEL qui décrivent ses services et ses lignes. */
    public const MATERIAL_SECTIONS = '/^(?:utilisations?|services?|service\s+commercial|affectations?|lignes?\s+(?:parcourues|desservies|exploit[ée]es)|dessertes?|exploitation|carri[èe]res?|parcours|missions?|engagements?|en\s+service)\b/iu';

    /**
     * Sections d'un article de MATÉRIEL qui listent ses relations (« Relations assurées », « Relations
     * effectuées ») : lues en plus, pour les seuls articles dont aucune section ne répond à MATERIAL_SECTIONS.
     */
    public const RELATION_SECTIONS = '/^(?:relations?|liaisons?)\b/iu';

    /** Longueur maximale d'une phrase citée comme preuve. */
    public const QUOTE_WIDTH = 300;

    /**
     * Futur, conditionnel, projet ou négation de la circulation : la phrase ne crée aucun lien.
     * Alternatives d'expression régulière, reprises par MaterialPeriodExtractor (aucune période non plus).
     */
    public const DOUBT_WORDS = 'jamais|interdite?s?|incompatibles?|ne\s+(?:peu[tv]ent|pouvai(?:en)?t|pourr\w*)|devr(?:a|ont|ait|aient)|pourr(?:a|ont|ait|aient)|prévue?s?|envisag\w*|projet\w*|à\s+terme|seront|sera|serai(?:en)?t';

    private const DOUBTFUL = '/\b(?:'.self::DOUBT_WORDS.')\b/iu';

    /** Futur simple de récit (« année où elles seront remplacées ») : seul mot de DOUBT_WORDS qu'admet isProspective(). */
    private const NARRATIVE_FUTURE = '/^(?:sera|seront)$/iu';

    /** Écart minimal, en années, entre la plus récente année citée et aujourd'hui pour lire un futur de récit. */
    public const NARRATIVE_FUTURE_GAP = 15;

    /**
     * Familles de doute sur la nature de la circulation, lues dans la partie de phrase qui porte le lien
     * (doubt()) : le lien est gardé, en fiabilité `low` sans autre preuve. Les mêmes mots écartent la
     * phrase entière des périodes (MaterialPeriodExtractor, UNCERTAIN_WORDS).
     *
     *   - `order` : commande, contrat, acquisition, rames « destinées à » (pas encore livrées) ; pas la
     *     « commande centralisée » ni le « poste de commande » (exploitation de la ligne) ;
     *   - `trial` : essais, prototype, record ;
     *   - `one_off` : circulation exceptionnelle, renfort, train affrété ou spécial, festivités, exposition,
     *     présentation au public ;
     *   - `preserved` : musée, préservation, restauration ; « conservée » seulement avec un musée, une
     *     association ou « en état de marche » (seule, elle veut souvent dire « gardée en service ») ;
     *   - `possible` : possibilité étudiée ou considérée, infrastructure qui « permet la circulation » ;
     *     pas « considérable » ni « considérablement ».
     * S'y ajoute `component` (COMPONENT_BEFORE), propre aux liens.
     */
    public const UNCERTAIN = [
        'order' => '(?<!poste\sde\s)(?<!postes\sde\s)command(?:e|es|é|ée|és|ées|ait|aient)(?!\s+centralisée?s?\b)|contrat|acquisition|destinée?s?\s+(?:à|au|aux)',
        'trial' => 'essais?|prototypes?|tester|testée?s?|records?',
        'one_off' => 'exceptionnel(?:le)?s?(?:ment)?|renforts?|affrét\w*|festivit\w+|(?:trains?|circulations?|marches?|voyages?|convois?)\s+spécia(?:l|le|les|ux)|expositions?|exposée?s?|présent(?:é|ée|és|ées|ation|er)',
        'preserved' => 'au\s+musée|musée\s+(?:du|de\s+la|des)\s+(?:chemin|rail|train|transport)\w*|préserv\w*|conserv(?:é|ée|és|ées)\s+(?:au\s+musée|par\s+(?:une?|l[’\']|les?|des)\s*(?:association|amicale|musée|cité|club)\w*|en\s+état\s+de\s+marche|à\s+titre\s+historique)|monument\s+historique|cité\s+du\s+train|en\s+cours\s+de\s+(?:remise\s+en\s+état|restauration)|restaur(?:é|ée|és|ées|ation)',
        'possible' => 'possibilit\w+|permett\w*\s+(?:ainsi\s+)?(?:la\s+)?circulation|étudi(?:é|ée|és|ées)|consid[èé]r(?:e|ent|é|ée|és|ées|ait|aient|ation)',
    ];

    /** Toutes les familles de UNCERTAIN en une alternative (filtre des périodes). */
    public const UNCERTAIN_WORDS = self::UNCERTAIN['order'].'|'.self::UNCERTAIN['trial'].'|'.self::UNCERTAIN['one_off']
        .'|'.self::UNCERTAIN['preserved'].'|'.self::UNCERTAIN['possible'];

    /**
     * Familles de doute propres aux liens (contexte de la mention, sans effet sur les dates) :
     *   - `positioning` : acheminement de rames vers un atelier, reprofilage, atelier « situé le long de » la ligne ;
     *   - `diverted` : coupure de la ligne, train détourné ou dévié ;
     *   - `restricted` : matériel « réservé à la ligne » ou « restreint à la portion » d'une autre ligne.
     */
    private const CONTEXT = [
        'positioning' => 'acheminements?\s+(?:de|des|du|d[’\'])\s*(?:rames?|matériels?|engins?|autorails?|locomotives?)|haut[- ]le[- ]pied|reprofilage|située?s?\s+(?:le\s+long|à\s+proximité|en\s+bordure)',
        'diverted' => 'coupure|détourn\w*|déviée?s?|déviation',
        'restricted' => 'restreinte?s?\s+à|réservée?s?\s+(?:à|aux)\s+(?:la\s+)?(?:ligne|portion|section)',
    ];

    /** Pièce reprise d'une série juste avant le lien (« entraînées par un moteur de » + BB 8100). */
    private const COMPONENT_BEFORE = '/\b(?:moteurs?|bogies?|caisses?|pièces?|organes?|transmissions?|turbines?)\s+(?:\p{L}+\s+)?(?:de|d[’\']|des|du)\s*(?:(?:la|une|un|l[’\'])\s*)?(?:(?:locomotives?|automotrices?|autorails?|séries?)\s+)?$/iu';

    /** Essais, lus dans toute la proposition qui porte la mention (doubt()). */
    private const TRIAL_CLAUSE = '/\b(?:essais?|records?)\b/iu';

    /** Phrase entière placée sous une coupure de la ligne (« Par suite de la coupure totale de la ligne, les trains… »). */
    private const DIVERTED_FRAME = '/^(?:par\s+suite|à\s+la\s+suite|lors|pendant|durant)\s+(?:de\s+la|d[’\']une)\s+(?:coupure|fermeture|interruption)\b/iu';

    /** Bornes d'une partie de phrase : ponctuation, fin de phrase, « tandis que », « alors que », « mais ». */
    private const PART_BREAK = '[,;:]|\.(?=\s*\p{Lu})|\b(?i:tandis|alors)\s+(?i:que)\b|\b(?i:mais)\b';

    /** Titre de section consacrée aux essais, aux records, aux festivités, à l'entretien ou à la préservation. */
    private const UNCERTAIN_SECTION = '/\b(?:essais?|records?|festivit\w*|entretien|maintenance|préserv\w*|musées?)\b/iu';

    /** Phrase qui reprend un sujet antérieur (« Ce train », « Ces rames », « Ils », « Elles ») pour dire par quoi il est remplacé. */
    private const REFERS_BACK_REPLACEMENT = '/^(?:ce|cet|cette|ces|celui|celle|ceux|celles|il|ils|elle|elles)\b(?:-(?:ci|là))?[^.;]*\bremplac(?:é|ée|és|ées)\b[^.;]{0,40}?\bpar\b/iu';

    /**
     * Comparaison ou filiation juste avant le lien, dans la même partie de phrase (sans virgule ni
     * point entre les deux) : la série citée sert de référence, elle n'est pas engagée sur la ligne.
     * « comme les » seul n'en fait pas partie : « de nouveaux matériels comme les X 73500 » cite un
     * exemple de ce qui circule. « aussi bien que » non plus (énumération), ni « ne … plus … que »
     * (restriction, RESTRICTION_BEFORE : la série est la seule à rouler), ni « issus des familles des
     * X 2200 et XR 6000 » (appartenance : l'X 92201 et la XR 96000 de Fécamp sont de ces séries).
     */
    private const COMPARISON_BEFORE = '/(?:\b(?:similaires?|semblables?|comparables?|identiques?|analogues?|équivalent(?:e|s|es)?|pareil(?:le)?s?|dérivé(?:e|s|es)?|inspiré(?:e|s|es)?)|\bà\s+l[’\']image|\bà\s+l[’\']instar|\bsur\s+le\s+modèle)\b[^.;,:()\[\]]{0,50}$/iu';

    /** Comparatif juste avant le lien, même partie de phrase : « plus confortables que les », « moins rapides que les ». */
    private const COMPARATIVE_BEFORE = '/\b(?:plus|moins)\s+(?!(?:guère|rien|que)\b)(?:\p{L}+\s+){1,2}que\b[^.;,:()\[\]]{0,50}$/iu';

    /**
     * Restriction juste avant le lien : « n'est plus desservie que par des », « ne circule plus désormais
     * que des », « n'y a plus que » (revue des PR #19 et #20). La négation porte sur « plus », la série
     * citée est la seule à rouler ; « ne sont pas plus confortables que » reste une comparaison.
     */
    private const RESTRICTION_BEFORE = '/\bn(?:e\s+|[’\']\s?)(?:(?!pas\b)\p{L}+\s+){0,2}plus\s+(?:\p{L}+\s+){0,2}que\b[^.;,:()\[\]]{0,50}$/iu';

    /**
     * Juste après le lien, dans la même partie de phrase : « qui en sont dérivées ». Ce qui est décrit
     * dérive de la série de l'article (« les locomotives [[Ceinture]] n° 21 à 35 qui en sont dérivées ») :
     * l'article cité n'est pas un lieu de circulation de la série.
     */
    private const DERIVED_AFTER = '/^[^.;,:()]{0,40}?\bqui\s+en\s+(?:sont|est|furent|fut|étaient|était)\s+(?:dérivée?s?|issue?s?|inspirée?s?|tirée?s?)\b/iu';

    /** Titre d'une section consacrée à des projets ou à l'avenir. */
    private const DOUBTFUL_SECTION = '/\b(?:prévue?s?|projets?|futur\w*|envisag\w*|perspectives?)\b/iu';

    /**
     * Modèles en ligne dont on garde le texte (dates, unités, nombres, siècles, incises). Un lien posé dans
     * un autre modèle ({{Note|…}}, {{Refnec|…}}) n'est pas dans la phrase lue : c'est une note.
     */
    private const INLINE_TEMPLATES = ['date', 'date-', 'unité', 'nobr', 'nombre', 'nb', 's', 's-', 'sp', 'siècle', 'x', 'citation', 'japonais', 'formatnum', 'num', 'lang', 'abréviation', 'abr', 'm', 'n°', 'numéro',
        'incise', 'pas clair', 'passage à actualiser'];

    /**
     * Sections dont le titre correspond au motif, avec leurs sous-sections.
     *
     * @return list<array{title: string, level: int, text: string}>
     */
    public static function sections(string $wikitext, string $pattern): array
    {
        $parts = preg_split('/^(={2,6})\s*(.+?)\s*\1\s*$/mu', $wikitext, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $out = [];
        $keepUntil = null;
        $current = null;
        for ($i = 1; $i + 1 < count($parts); $i += 3) {
            $level = strlen($parts[$i]);
            $title = self::plainTitle($parts[$i + 1]);
            $body = $parts[$i + 2] ?? '';
            if ($keepUntil !== null && $level <= $keepUntil) {
                $keepUntil = null;
                $current = null;
            }
            if ($keepUntil === null && preg_match($pattern, $title)) {
                $keepUntil = $level;
                $current = $title;
            }
            if ($keepUntil !== null) {
                $out[] = ['title' => $level > $keepUntil ? "{$current} > {$title}" : $title, 'level' => $level, 'text' => $body];
            }
        }

        return $out;
    }

    /**
     * Une phrase de l'article `$finalTitle`, demandé sous `$requested` pour la série `$name`, vaut-elle pour
     * cette série ? Toujours si l'article est le sien ; sinon (titre redirigé vers l'article d'une autre série,
     * qui le partage), seulement si la phrase nomme la série, espaces entre lettres et chiffres facultatifs
     * (« Z 56500 », « Z56500 »).
     */
    public static function speaksOf(string $requested, string $finalTitle, string $name, string $quote): bool
    {
        if (self::normalizeTitle($requested) === self::normalizeTitle($finalTitle)) {
            return true;
        }
        $parts = preg_split('/\s+|(?<=\p{L})(?=\d)/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $pattern = implode('\s*', array_map(fn (string $p): string => preg_quote($p, '/'), $parts));

        return preg_match('/(?<![\p{L}\d])'.$pattern.'(?![\p{L}\d])/iu', $quote) === 1;
    }

    /**
     * Section consacrée à des projets ou à l'avenir (« Dessertes réalisées ou prévues »,
     * « Projets ») : ses liens ne prouvent pas une circulation. Le titre inclut celui de la
     * section parente (« Parent > Sous-section »).
     */
    public static function isDoubtfulSection(string $title): bool
    {
        return preg_match(self::DOUBTFUL_SECTION, $title) === 1;
    }

    /**
     * Liens internes d'un texte, avec la phrase (ou la ligne de liste ou de tableau) qui les porte.
     * Les liens vers des fichiers, catégories ou autres espaces de noms sont ignorés.
     *
     * `doubt` : famille de doute de la mention (doubt(), `footnote` pour un lien posé dans une note), ou
     * null. Un article cité deux fois dans la même phrase garde sa mention la plus sûre.
     *
     * @return list<array{target: string, quote: string, refers_back: bool, doubt: ?string}>
     */
    public static function links(string $text): array
    {
        $text = self::stripNoise($text);
        $out = [];
        foreach (self::units($text) as $unit) {
            if (! preg_match_all('/\[\[([^\[\]|#]+)(?:#[^\[\]|]*)?(?:\|[^\[\]]*)?\]\]/u', $unit['sentence'], $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $quote = self::plain($unit['sentence']);
            // Phrase vide une fois les modèles retirés (« . ») : rien à citer.
            if (! preg_match('/\p{L}/u', $quote) || self::isProspective($quote)) {
                continue;
            }
            $refersBack = preg_match(self::REFERS_BACK_REPLACEMENT, $quote) === 1;
            // La phrase précédente (même ligne) donne son sujet à « Ce train… » : on la cite avec elle.
            if ($refersBack && $unit['previous'] !== null) {
                $room = self::QUOTE_WIDTH - mb_strlen($quote) - 1;
                $quote = ($room >= 40 ? mb_strimwidth(self::plain($unit['previous']), 0, $room, '…').' ' : '').$quote;
            }
            /** @var array<string, ?string> $doubts mention la plus sûre de chaque article de la phrase */
            $doubts = [];
            foreach ($matches as [[$link, $offset], [$raw]]) {
                $before = substr($unit['sentence'], 0, $offset);
                $plainBefore = self::plain($before);
                $plainAfter = self::plain(substr($unit['sentence'], $offset + strlen($link)));
                // Comparaison ou filiation : « un confort similaire aux autorails » + X 73500.
                if (self::isCompared($plainBefore, $plainAfter)) {
                    continue;
                }
                $target = self::normalizeTitle($raw);
                // Fichiers, catégories, autres espaces de noms et liens interwikis (« :en:… ») : pas des articles.
                if ($target === '' || str_starts_with($target, ':')
                    || preg_match('/^(?:fichier|file|image|catégorie|category|modèle|template|portail|projet|aide|wikipédia|wp|wikt|wiktionary|commons|s|d|en|de|it|es|nl)\s*:/iu', $target)) {
                    continue;
                }
                $doubt = self::inNote($before) ?? self::doubt($plainBefore, self::plain($link), $plainAfter);
                if (! array_key_exists($target, $doubts) || $doubts[$target] !== null) {
                    $doubts[$target] = $doubt;
                }
            }
            foreach ($doubts as $target => $doubt) {
                $out[] = ['target' => (string) $target, 'quote' => mb_strimwidth($quote, 0, self::QUOTE_WIDTH, '…'), 'refers_back' => $refersBack, 'doubt' => $doubt];
            }
        }

        return $out;
    }

    /**
     * La phrase (en clair) parle-t-elle d'un projet, d'un futur, d'un conditionnel ou d'une négation de la
     * circulation (DOUBT_WORDS) ? Exception, le futur de récit : « les Pacific État continueront de circuler
     * sur Paris-Granville jusqu'en 1950, année où elles seront remplacées par des 141 P ». Quand les seuls mots
     * de doute sont « sera » ou « seront » et que toutes les années citées ont au moins NARRATIVE_FUTURE_GAP
     * ans, la phrase raconte le passé : elle n'est pas écartée. Sans année, le futur reste un doute.
     */
    public static function isProspective(string $quote, ?int $currentYear = null): bool
    {
        if (preg_match_all(self::DOUBTFUL, $quote, $words) < 1) {
            return false;
        }
        foreach ($words[0] as $word) {
            if (preg_match(self::NARRATIVE_FUTURE, $word) !== 1) {
                return true;
            }
        }
        if (preg_match_all('/(?<![\d\p{L}])(1[89]\d\d|20\d\d)(?![\d\p{L}])/u', $quote, $years) < 1) {
            return true;
        }

        return max(array_map('intval', $years[1])) > ($currentYear ?? (int) date('Y')) - self::NARRATIVE_FUTURE_GAP;
    }

    /**
     * Famille de doute d'une mention (voir UNCERTAIN et CONTEXT), lue dans la partie de phrase qui la
     * porte (part()), ou null : la phrase ne dit rien qui mette en doute la circulation.
     *
     * @param  string  $before  texte en clair avant la mention
     * @param  string  $label  texte de la mention
     * @param  string  $after  texte en clair après la mention
     */
    public static function doubt(string $before, string $label, string $after): ?string
    {
        if (preg_match(self::DIVERTED_FRAME, ltrim($before.$label.$after)) === 1) {
            return 'diverted';
        }
        if (preg_match(self::COMPONENT_BEFORE, $before) === 1) {
            return 'component';
        }
        [$partBefore, $partAfter] = self::part($before, $after);
        $part = $partBefore.' '.$label.' '.$partAfter;
        foreach ([...self::UNCERTAIN, ...self::CONTEXT] as $family => $words) {
            if (preg_match('/\b(?:'.$words.')\b/iu', $part) === 1) {
                return $family;
            }
        }
        // Des essais disent ce que fait toute la proposition, au-delà des virgules (« Autorail X 2101 (de la
        // série X 2100) avec deux remorques XR 6000, venu en essais sur Grenoble - Montmélian »).
        $clause = '/;|\.(?=\s*\p{Lu})|\b(?i:tandis|alors)\s+(?i:que)\b|\b(?i:mais|puis)\b/u';
        $wholeClause = (string) array_last(preg_split($clause, $before) ?: ['']).' '.$label.' '.(string) array_first(preg_split($clause, $after) ?: ['']);
        if (preg_match(self::TRIAL_CLAUSE, $wholeClause) === 1) {
            return 'trial';
        }

        return null;
    }

    /**
     * Section dont le titre met en doute la circulation de tout ce qu'elle cite (« Trains d'essai »,
     * « Records », « Festivités sur la ligne », « Entretien », « Matériels préservés ») : `section`, ou null.
     */
    public static function uncertainSection(string $title): ?string
    {
        return preg_match(self::UNCERTAIN_SECTION, $title) === 1 ? 'section' : null;
    }

    /**
     * Partie de phrase qui porte la mention : entre deux virgules, points-virgules, deux-points ou
     * parenthèses, sans dépasser « tandis que », « alors que » ni « mais ». S'y ajoutent la parenthèse qui
     * suit aussitôt la mention (« X 73500 (en remplacement exceptionnel d'X 72500) ») et, pour une
     * mention entre parenthèses, la partie qui entoure la parenthèse (« les matériels récents (Z 21500 et
     * Régiolis) sont réservés à la ligne Orléans-Tours »).
     *
     * @return array{0: string, 1: string} texte retenu avant et après la mention
     */
    private static function part(string $before, string $after): array
    {
        $last = fn (string $text, string $pattern): string => (string) array_last(preg_split($pattern, $text) ?: ['']);
        $first = fn (string $text, string $pattern): string => (string) array_first(preg_split($pattern, $text) ?: ['']);
        $break = '/'.self::PART_BREAK.'/u';
        $inner = '/'.self::PART_BREAK.'|[()]/u';
        $ownBefore = $last($before, $inner);
        $ownAfter = $first($after, $inner);
        $extra = '';
        if (trim($ownAfter) === '' && preg_match('/^\s*\(([^()]*)\)/u', substr($after, strlen($ownAfter)), $m)) {
            $extra .= ' '.$m[1];
        }
        if (substr_count($before, '(') > substr_count($before, ')')) {
            $open = (int) strrpos($before, '(');
            $close = strpos($after, ')');
            $outerBefore = (string) preg_replace('/\([^()]*\)/u', ' ', substr($before, 0, $open));
            $outerAfter = $close === false ? '' : (string) preg_replace('/\([^()]*\)/u', ' ', substr($after, $close + 1));
            $extra .= ' '.$last($outerBefore, $break).' '.$first($outerAfter, $break);
        }

        return [$ownBefore, $ownAfter.$extra];
    }

    /**
     * Le wikitexte qui précède un lien ouvre-t-il un modèle dont le texte n'est pas lu dans la phrase ? Une
     * note ({{Note|…}}), un passage marqué sans source ({{Refnec|…}}) : `footnote` ; sinon null.
     */
    private static function inNote(string $wikitextBefore): ?string
    {
        $open = [];
        preg_match_all('/\{\{\s*([^|{}]*)|\}\}/u', $wikitextBefore, $tokens, PREG_SET_ORDER);
        foreach ($tokens as $token) {
            if (str_starts_with($token[0], '{{')) {
                $open[] = mb_strtolower(trim($token[1]));
            } else {
                array_pop($open);
            }
        }
        foreach ($open as $name) {
            if ($name !== 'lien' && ! in_array($name, self::INLINE_TEMPLATES, true)) {
                return 'footnote';
            }
        }

        return null;
    }

    /**
     * Phrases d'un texte en clair, avec la position de chaque lien interne dans ce texte : pour lire
     * ce que la phrase dit de l'article cité (une date, une période). Les lignes de tableau et les
     * légendes d'image ne sont pas des phrases suivies et sont laissées de côté.
     *
     * `compared` : la série est citée comme terme de comparaison (isCompared()).
     *
     * @return list<array{text: string, links: list<array{target: string, pos: int, length: int, compared: bool}>}>
     */
    public static function linkedSentences(string $text): array
    {
        $out = [];
        foreach (self::units(self::stripNoise($text)) as $unit) {
            $raw = ltrim($unit['sentence']);
            if (preg_match('/^(?:[|!]|\{\||\[\[\s*(?:fichier|file|image)\s*:)/iu', $raw)) {
                continue;
            }
            $targets = [];
            // Chaque lien d'article devient « \u{E000}n\u{E001}texte\u{E002} » : la marque survit à plain().
            $marked = (string) preg_replace_callback('/\[\[([^\[\]|#]+)(?:#[^\[\]|]*)?(?:\|([^\[\]]*))?\]\]/u', function (array $m) use (&$targets): string {
                $target = self::normalizeTitle($m[1]);
                if ($target === '' || str_starts_with($target, ':') || preg_match('/^[\p{L} ]{1,12}\s*:/u', $target)) {
                    return $m[0];
                }
                $targets[] = $target;
                $label = str_replace('|', ' ', ($m[2] ?? '') !== '' ? $m[2] : $m[1]);

                return "\u{E000}".(count($targets) - 1)."\u{E001}".$label."\u{E002}";
            }, $raw);
            if ($targets === []) {
                continue;
            }
            $plain = self::plain($marked);
            // Marques orphelines (lien coupé par un modèle) : retirées du texte autour des liens.
            $orphans = fn (string $s): string => (string) preg_replace('/\x{E000}\d*\x{E001}?|[\x{E001}\x{E002}]/u', '', $s);
            $links = [];
            $clean = '';
            $offset = 0;
            if (preg_match_all('/\x{E000}(\d+)\x{E001}([^\x{E000}-\x{E002}]*)\x{E002}/u', $plain, $all, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                foreach ($all as $m) {
                    $clean .= $orphans(substr($plain, $offset, $m[0][1] - $offset));
                    $after = $orphans(substr($plain, $m[0][1] + strlen($m[0][0])));
                    $links[] = ['target' => $targets[(int) $m[1][0]], 'pos' => mb_strlen($clean), 'length' => mb_strlen($m[2][0]), 'compared' => self::isCompared($clean, $after)];
                    $clean .= $m[2][0];
                    $offset = $m[0][1] + strlen($m[0][0]);
                }
            }
            $clean .= $orphans(substr($plain, $offset));
            if ($links !== [] && trim($clean) !== '') {
                $out[] = ['text' => $clean, 'links' => $links];
            }
        }

        return $out;
    }

    /**
     * Le lien entre ces deux textes (en clair) est-il le terme d'une comparaison ? « Ce nouveau matériel
     * offre un confort similaire aux autorails » + X 73500 : oui. « …de nouveaux matériels comme les
     * ATER » + X 73500 : non. Ou l'article est-il cité pour ce qui en dérive ? « les locomotives » +
     * Ceinture + « n° 21 à 35 qui en sont dérivées » : oui.
     */
    public static function isCompared(string $plainBefore, string $plainAfter = ''): bool
    {
        return preg_match(self::COMPARISON_BEFORE, $plainBefore) === 1
            || (preg_match(self::COMPARATIVE_BEFORE, $plainBefore) === 1 && preg_match(self::RESTRICTION_BEFORE, $plainBefore) !== 1)
            || preg_match(self::DERIVED_AFTER, $plainAfter) === 1;
    }

    /** Titre d'article normalisé comme MediaWiki : espaces, première lettre en capitale. */
    public static function normalizeTitle(string $title): string
    {
        $title = html_entity_decode(str_replace('_', ' ', $title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = trim((string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $title));
        if ($title === '' || str_starts_with($title, ':')) {
            return $title;
        }

        return mb_strtoupper(mb_substr($title, 0, 1)).mb_substr($title, 1);
    }

    /** Commentaires, références, balises et galeries retirés (ils ne sont pas du texte lu). */
    public static function stripNoise(string $text): string
    {
        $text = (string) preg_replace('/<!--.*?-->/su', ' ', $text);
        $text = (string) preg_replace('/<ref[^>\/]*\/>|<ref[^>]*>.*?<\/ref>/su', ' ', $text);
        $text = (string) preg_replace('/<gallery[^>]*>.*?<\/gallery>/su', ' ', $text);

        return (string) preg_replace('/<\/?[a-z][^>]*>/iu', ' ', $text);
    }

    /**
     * Unités de lecture : chaque ligne (élément de liste, cellule de tableau), découpée en phrases ;
     * `previous` est la phrase qui précède dans la même ligne (null pour la première).
     *
     * @return list<array{sentence: string, previous: ?string}>
     */
    public static function units(string $text): array
    {
        $out = [];
        foreach (preg_split('/\n+/u', $text) ?: [] as $line) {
            // Fin de phrase : point suivi d'un espace et d'une capitale (ou d'un modèle qui ouvre la phrase
            // suivante, « … Chambéry. {{refnec|Cependant… »), hors des liens.
            $previous = null;
            foreach (preg_split('/(?<=[.!?])\s+(?=[A-ZÉÈÀÂÎÔÛÇ«]|\{\{)(?![^\[]*\]\])/u', $line) ?: [] as $sentence) {
                if (trim($sentence) !== '') {
                    $out[] = ['sentence' => $sentence, 'previous' => $previous];
                    $previous = $sentence;
                }
            }
        }

        return $out;
    }

    /** Texte lisible d'un morceau de wikitexte : liens remplacés par leur texte, modèles simplifiés. */
    public static function plain(string $wikitext): string
    {
        $text = $wikitext;
        // Modèles imbriqués : du plus intérieur au plus extérieur.
        for ($i = 0; $i < 6 && str_contains($text, '{{'); $i++) {
            $text = (string) preg_replace_callback('/\{\{([^{}]*)\}\}/u', function (array $m): string {
                $params = array_map('trim', explode('|', $m[1]));
                $name = mb_strtolower((string) array_shift($params));
                $positional = array_values(array_filter($params, fn (string $p): bool => ! str_contains($p, '=') && $p !== ''));
                if ($name === 'lien') {
                    foreach ($params as $p) {
                        if (preg_match('/^(?:fr|texte)\s*=\s*(.+)$/u', $p, $mm)) {
                            return $mm[1];
                        }
                    }

                    return $positional[0] ?? '';
                }

                return in_array($name, self::INLINE_TEMPLATES, true) ? implode(' ', $positional) : '';
            }, $text);
        }
        // Liens du plus intérieur au plus extérieur ; d'un fichier, seule la légende (dernier paramètre) est du texte.
        for ($i = 0; $i < 4 && str_contains($text, '[['); $i++) {
            $text = (string) preg_replace_callback('/\[\[([^\[\]]*)\]\]/u', function (array $m): string {
                $params = explode('|', $m[1]);
                if (preg_match('/^\s*(?:fichier|file|image)\s*:/iu', $params[0])) {
                    $caption = trim((string) end($params));

                    return count($params) > 1 && ! preg_match('/^(?:thumb|vignette|left|right|gauche|droite|centre|center|upright|redresse|\d+px)\b/iu', $caption) ? $caption : '';
                }

                return (string) end($params);
            }, $text);
        }
        $text = (string) preg_replace('/\[https?:\/\/\S+\s*([^\]]*)\]/u', '$1', $text);
        $text = str_replace(["'''", "''"], '', $text);
        $text = (string) preg_replace('/^[\s*#:;|!{}-]+/u', '', $text);
        $text = str_replace(['||', '!!'], ' ; ', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/[\x{2014}\x{2013}]/u', '-', $text);
        // Espace laissée devant un point ou une virgule par une référence retirée.
        $text = (string) preg_replace('/\s+([.,)])/u', '$1', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private static function plainTitle(string $title): string
    {
        return trim(self::plain($title), " =\t");
    }
}
