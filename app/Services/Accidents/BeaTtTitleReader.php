<?php

namespace App\Services\Accidents;

use App\Enums\AccidentKind;

/**
 * Faits d'un intitulé d'enquête du BEA-TT : « Collision entre un TER et un autocar scolaire sur
 * le PN 25 à Millas (66) », « Déraillement d'une rame TGV en entrée de gare de Marseille
 * St-Charles (13) ». Lieu (commune, département), passage à niveau, gare, trains cités, nature.
 */
final class BeaTtTitleReader
{
    /** Trains cités : motif → [libellé (nom de service, invariable), catégorie]. */
    private const TRAINS = [
        '/\bTGV\b(?:\s+(\d{4,5})\b)?/u' => ['TGV', 'passenger'],
        '/\bTER\b(?:\s+(\d{3}\s?\d{3}|\d{5,6})\b)?/u' => ['TER', 'passenger'],
        '/\bIntercités\b/iu' => ['Intercités', 'passenger'],
        '/\bcorail\b/iu' => ['Corail', 'passenger'],
        '/\bRER\s+([A-E])\b/u' => ['RER', 'passenger'],
        '/\btram-train\b/iu' => ['Tram-train', 'passenger'],
        '/\btrain jaune\b/iu' => ['Train jaune', 'passenger'],
        '/\btrain des Pignes\b/iu' => ['Train des Pignes', 'passenger'],
        '/\bEurotunnel\b/u' => ['Eurotunnel', null],
        '/\b(?:trains? (?:de )?voyageurs|trains? de banlieue|train touristique|train (?:couchettes|express|rapide|omnibus|de permissionnaires|de pèlerins|militaire)|rapide|express|omnibus)\b/iu' => [null, 'passenger'],
        '/\b(?:trains? de (?:fret|marchandises|charbon|transport combiné)|train (?:fret|MD)|wagons?|MD)\b/iu' => [null, 'freight'],
        '/\b(?:train|engin) de travaux\b/iu' => [null, 'works'],
        '/\b(?:rame|circulation) d[\'’]essai\b/iu' => [null, 'test'],
    ];

    public function __construct(private readonly AccidentKindClassifier $classifier) {}

    /**
     * @return array{
     *   place: ?string, department: ?string, level_crossing: ?int, station: ?string,
     *   kind: AccidentKind, trains: list<array{label: ?string, category: ?string, number: ?string, material: ?string, operator: ?string}>
     * }
     */
    public function read(string $title, string $mode): array
    {
        $t = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
        $department = preg_match('/\((\d{2}|2[AB])\)\s*(?:e\s*)?$/u', $t, $m) ? $m[1] : null;
        $kind = $mode === 'PN' ? AccidentKind::LevelCrossing : ($this->classifier->fromFrenchText($t) ?? AccidentKind::Other);

        return [
            'place' => $this->place($t),
            'department' => $department,
            'level_crossing' => preg_match('/\bPN\s*(?:n\s*°\s*|no\s*)?(\d{1,4})\b/u', $t, $m) ? (int) $m[1] : null,
            'station' => $this->station($t),
            'kind' => $kind,
            'trains' => $this->trains($t),
        ];
    }

    /**
     * Commune citée, dans cet ordre : ce qui suit le dernier « à » (« sur le PN 25 à Millas »,
     * « en Gare de Lyon à Paris »), « au » / « aux » avec l'article rendu (« au Breuil » → « Le
     * Breuil »), le dernier « de » / « d' » (« Passage à niveau de Neufchâteau »), enfin le
     * dernier nom propre (« entre Romilly-sur-Seine et Troyes »). Toujours un nom propre : la
     * commune est ensuite vérifiée dans la table des communes (AccidentLocator).
     */
    public function place(string $title): ?string
    {
        $t = preg_replace('/\s*\((?:\d{2}|2[AB]|Belgique)\)\s*(?:e\s*)?$/u', '', $title) ?? $title;
        $rules = [
            "/^.*\\sà\\s+((?:la |le |les |l['’])?\\p{Lu}.*)$/u" => '',
            '/^.*\sau\s+(\p{Lu}.*)$/u' => 'Le ',
            '/^.*\saux\s+(\p{Lu}.*)$/u' => 'Les ',
            "/^.*\\s(?:de|d['’]|du)\\s*(\\p{Lu}.*)$/u" => '',
            "/(\\p{Lu}[\\p{L}'’-]+(?:[\\s-]+(?:sur|sous|en|de|des|du|la|le|les|l['’]|\\p{Lu}[\\p{L}'’-]*)+)*)\\s*$/u" => '',
        ];
        foreach ($rules as $pattern => $article) {
            if (preg_match($pattern, $t, $m) && ($place = $this->cleanPlace($article.$m[1])) !== null) {
                return $place;
            }
        }

        return null;
    }

    /** Gare citée (« en gare de X », « gare d'X ») sans la commune qui suit (« à Paris »). */
    public function station(string $title): ?string
    {
        if (! preg_match("/\\bgare (?:de |d['’]|du )\\s*([^()]+?)(?:\\s+à\\s+.*)?\\s*(?:\\(|$)/iu", $title, $m)) {
            return null;
        }
        $s = trim($m[1]);

        return $s !== '' ? $s : null;
    }

    /**
     * @return list<array{label: ?string, category: ?string, number: ?string, material: ?string, operator: ?string}>
     */
    public function trains(string $title): array
    {
        $out = [];
        $seen = [];
        foreach (self::TRAINS as $pattern => [$label, $category]) {
            if (! preg_match_all($pattern, $title, $all, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($all as $m) {
                $number = null;
                $name = $label;
                if ($label === 'RER') {
                    $name = 'RER '.$m[1];
                } elseif (isset($m[1])) {
                    $number = trim($m[1]) ?: null;
                }
                $key = ($name ?? '').'|'.$category.'|'.$number;
                // Un train nommé (TER) rend inutile sa catégorie seule (« train de voyageurs »).
                if (isset($seen[$key]) || ($name === null && array_any($out, fn ($o) => $o['category'] === $category))) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = ['label' => $name, 'category' => $category, 'number' => $number, 'material' => null, 'operator' => null];
            }
        }

        return $out;
    }

    private function cleanPlace(string $place): ?string
    {
        $p = trim($place, " \t\n\r\0\x0B.,;");
        // Mots de train collés au lieu (« Collision de 2 TER Longueville », « train Fret La Ferté »).
        $p = preg_replace('/^(?:(?:TER|TGV|RER(?:\s+[A-E])?|MD|TC|PL|VL|SNCF|Fret|train|Train)\s+)+/u', '', $p) ?? $p;
        if (! preg_match("/^(?:(?:la|le|les) |l['’])?\\p{Lu}/u", $p)) {
            return null;
        }
        $p = preg_replace('/^(?:la|le|les)\s+commune\s+de\s+/iu', '', $p) ?? $p;
        $p = preg_replace("/^L['’]\\s*/u", "L'", $p) ?? $p;

        return $p !== '' ? $p : null;
    }
}
