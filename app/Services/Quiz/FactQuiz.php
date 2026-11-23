<?php

namespace App\Services\Quiz;

use App\Enums\QuizLevel;
use App\Enums\QuizTopic;
use App\Services\LineFacts;
use App\Services\NetworkChronology;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

/**
 * Jeu « Culture du rail » : questions de texte sur des faits datés ou chiffrés (année d'ouverture d'une ligne,
 * compagnie, plus long tunnel, architecte d'une gare, gare la plus fréquentée, première série en service…).
 * Le vivier (FactPool) a un sujet par question possible ; les propositions sont tirées ainsi :
 *
 *   - sujets dont la réponse est une année : années voisines, jamais l'année juste (facile : 6 à 25 ans d'écart ;
 *     difficile : 1 à 4 ans), dans un ordre fixé par hachage pour que la bonne année ne soit pas toujours au milieu ;
 *   - compagnie : compagnies qui existaient cette année-là et n'ont jamais été rattachées à la ligne ;
 *   - architecte : architectes d'autres gares ; plus long, plus fréquentée, première en service : autres sujets du
 *     vivier qui ont une marge sur la bonne réponse (facile : grande ; difficile : faible, mais jamais nulle), pour
 *     que les trois mauvaises réponses soient vraiment fausses d'après les données.
 *
 * Le niveau facile ne pose que les questions de degré 1 ou 2 (QuizTopic::difficulty).
 */
final class FactQuiz implements GroupedQuizSource, PickAwareQuizSource, QuizSource
{
    public const POOL_KEY = 'quiz:facts-pool:v1';

    /** Écarts d'années des mauvaises réponses : difficile (proches) et facile (éloignées). */
    public const HARD_OFFSETS = [1, 2, 3, 4];

    public const EASY_OFFSETS = [6, 7, 8, 9, 10, 12, 14, 16, 18, 20, 22, 25];

    /** Rapport minimal entre la bonne réponse et chaque mauvaise (plus long, plus fréquentée) : difficile, facile. */
    public const HARD_RATIO = 1.25;

    public const EASY_RATIO = 2.5;

    /** Écart minimal de mise en service entre la première série et les trois autres : difficile, facile (années). */
    public const HARD_GAP = 2;

    public const EASY_GAP = 10;

    /** Degré maximal de difficulté posé au niveau facile. */
    public const EASY_MAX_DIFFICULTY = 2;

    /** Licences courtes des fournisseurs cités : [libellé, adresse]. */
    private const LICENSES = [
        'wikipedia' => ['CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0/'],
        'wikidata' => ['CC0', 'https://creativecommons.org/publicdomain/zero/1.0/'],
        'ohm' => ['CC0', 'https://creativecommons.org/publicdomain/zero/1.0/'],
        'ign' => ['Licence Ouverte 2.0', 'https://www.etalab.gouv.fr/licence-ouverte-open-licence/'],
        'pop' => ['Licence Ouverte 2.0', 'https://www.etalab.gouv.fr/licence-ouverte-open-licence/'],
        'sncf' => ['ODbL 1.0', 'https://opendatacommons.org/licenses/odbl/1-0/'],
    ];

    public function __construct(private readonly FactPool $facts, private readonly NetworkChronology $chronology) {}

    /** Clé d'une proposition : opaque, la même pour une même réponse à une même question. */
    public static function optionKey(string $questionId, string $label): string
    {
        return substr(hash('sha256', $questionId.'|'.$label), 0, 16);
    }

    public function pool(): array
    {
        $cached = Cache::get(self::POOL_KEY);
        if (is_array($cached)) {
            return $cached;
        }
        $built = $this->facts->build();
        // Un cache source absent (chronologie, compagnies) donne un vivier incomplet : on le recalcule bientôt.
        Cache::put(self::POOL_KEY, $built['items'], $built['complete'] ? now()->addHour() : now()->addMinute());

        return $built['items'];
    }

    public function group(array $subject): string
    {
        return (string) $subject['group'];
    }

    public function candidates(array $subject, array $pool, QuizLevel $level): array
    {
        $topic = QuizTopic::from((string) $subject['topic']);
        if ($level === QuizLevel::Easy && $topic->difficulty() > self::EASY_MAX_DIFFICULTY) {
            return [];
        }
        $easy = $level === QuizLevel::Easy;
        $same = array_values(array_filter($pool, fn (array $o): bool => $o['topic'] === $subject['topic'] && $o['id'] !== $subject['id']));

        if ($topic->isYear()) {
            return $this->yearOptions($subject, $easy);
        }

        return match ($topic) {
            QuizTopic::Company => $this->companyOptions($subject),
            QuizTopic::Architect => $this->byGap($same, fn (array $o): int => $o['data']['built'] === null || $subject['data']['built'] === null ? PHP_INT_MAX : abs($o['data']['built'] - $subject['data']['built'])),
            QuizTopic::LongestTunnel, QuizTopic::LongestViaduct => $this->belowBy($same, 'length_m', (int) $subject['data']['length_m'], $easy ? self::EASY_RATIO : self::HARD_RATIO),
            QuizTopic::Attendance => $this->belowBy($same, 'passengers', (int) $subject['data']['passengers'], $easy ? self::EASY_RATIO : self::HARD_RATIO),
            QuizTopic::FirstSeries => $this->laterSeries($same, $subject, $easy ? self::EASY_GAP : self::HARD_GAP),
            default => [],
        };
    }

    public function question(QuizPick $pick): array
    {
        $s = $pick->subject;
        $topic = QuizTopic::from((string) $s['topic']);
        $d = $s['data'];
        $subject = match ($topic) {
            QuizTopic::Milestone => __('chronology.milestones.'.$d['milestone'].'.title'),
            QuizTopic::OpeningYear, QuizTopic::ElectrificationYear, QuizTopic::Company, QuizTopic::Architect, QuizTopic::HeritageYear => (string) $s['title'],
            default => null,
        };

        return [
            'topic' => $topic->value,
            'difficulty' => $topic->difficulty(),
            'prompt' => __('quiz.facts.prompt.'.$topic->value, [
                'year' => $d['year'] ?? '',
                'type' => isset($d['type']) ? mb_strtolower((string) __('materials.type.'.$d['type'])) : '',
            ]),
            'subject' => $subject,
            'hints' => $this->hints($topic, $s),
        ];
    }

    public function reveal(array $subject): array
    {
        return $this->revealPick(new QuizPick(0, $subject, [$subject]));
    }

    public function revealPick(QuizPick $pick): array
    {
        $s = $pick->subject;
        $locale = app()->getLocale();
        $topic = QuizTopic::from((string) $s['topic']);
        $d = $s['data'];
        $others = array_values(array_filter($pick->options, fn (array $o): bool => $o['id'] !== $s['id']));
        $num = fn (int|float $n): string => (string) Number::format($n, locale: $locale);
        $list = fn (callable $show, array $items): string => implode(' ; ', array_map($show, $items));

        $text = match ($topic) {
            QuizTopic::OpeningYear, QuizTopic::ElectrificationYear => __('quiz.facts.reveal.'.$topic->value, ['line' => $s['title'], 'year' => $s['label']]),
            QuizTopic::Milestone => __('quiz.facts.reveal.milestone', ['title' => __('chronology.milestones.'.$d['milestone'].'.title'), 'year' => $s['label']]),
            QuizTopic::HeritageYear => __('quiz.facts.reveal.heritage_year', ['station' => $s['title'], 'year' => $s['label'], 'protection' => __('quiz.facts.protection.'.$d['protection'])]),
            QuizTopic::Architect => __('quiz.facts.reveal.architect', ['station' => $s['title'], 'architect' => $s['label']]),
            QuizTopic::Company => __('quiz.facts.reveal.'.($d['to'] === null ? 'company_open' : 'company'), ['line' => $s['title'], 'company' => $s['label'], 'year' => $d['year'], 'from' => $d['from'], 'to' => $d['to']]),
            QuizTopic::LongestTunnel, QuizTopic::LongestViaduct => __('quiz.facts.reveal.'.$topic->value, [
                'name' => $s['label'], 'length' => $num($d['length_m']),
                'others' => $list(fn (array $o): string => $o['label'].' ('.$num($o['data']['length_m']).' m)', $this->sortedBy($others, fn (array $o): int => -$o['data']['length_m'])),
            ]),
            QuizTopic::Attendance => __('quiz.facts.reveal.attendance', [
                'name' => $s['label'], 'count' => $num($d['passengers']), 'year' => $d['year'],
                'others' => $list(fn (array $o): string => $o['label'].' ('.$num($o['data']['passengers']).')', $this->sortedBy($others, fn (array $o): int => -$o['data']['passengers'])),
            ]),
            QuizTopic::FirstSeries => __('quiz.facts.reveal.first_series', [
                'name' => $s['label'], 'year' => $d['from'],
                'others' => $list(fn (array $o): string => $o['label'].' ('.$o['data']['from'].')', $this->sortedBy($others, fn (array $o): int => $o['data']['from'])),
            ]),
        };

        $sources = $this->sources($pick->options, $locale);

        return [
            'answer' => ['key' => (string) $s['key'], 'label' => (string) $s['label'], 'title' => $topic === QuizTopic::Milestone ? (string) __('chronology.milestones.'.$d['milestone'].'.title') : (string) $s['title'], 'url' => $s['url']],
            'fact' => [
                'text' => (string) $text,
                'lang' => $locale,
                'source' => $sources[0] ?? null,
                'sources' => $sources,
            ],
        ];
    }

    /**
     * Années voisines de l'année juste, dans l'ordre d'un hachage du sujet (la bonne année n'a pas de place
     * fixe parmi elles), bornées à l'histoire du rail et à l'année en cours.
     *
     * @param  array<string, mixed>  $subject
     * @return list<array<string, mixed>>
     */
    private function yearOptions(array $subject, bool $easy): array
    {
        $year = (int) $subject['data']['year'];
        $min = max(FactPool::FIRST_YEAR, 1);
        $years = [];
        foreach ($easy ? self::EASY_OFFSETS : self::HARD_OFFSETS as $offset) {
            foreach ([$year - $offset, $year + $offset] as $y) {
                if ($y >= $min && $y <= (int) now()->year) {
                    $years[] = $y;
                }
            }
        }
        usort($years, fn (int $a, int $b): int => strcmp(hash('xxh128', $subject['id']."|{$a}"), hash('xxh128', $subject['id']."|{$b}")));

        return array_map(fn (int $y): array => $this->synthetic($subject, (string) $y), $years);
    }

    /**
     * Compagnies qui existaient cette année-là et n'ont jamais été rattachées à la ligne ; celles de même nature que
     * la bonne réponse d'abord.
     *
     * @param  array<string, mixed>  $subject
     * @return list<array<string, mixed>>
     */
    private function companyOptions(array $subject): array
    {
        $others = $subject['data']['others'];
        usort($others, fn (array $a, array $b): int => [$a['kind'] === $subject['data']['company_kind'] ? 0 : 1, $a['label']] <=> [$b['kind'] === $subject['data']['company_kind'] ? 0 : 1, $b['label']]);

        return array_map(fn (array $o): array => $this->synthetic($subject, $o['label']), $others);
    }

    /**
     * Autres sujets dont la valeur `$field` est inférieure à celle du sujet d'au moins le rapport `$ratio`, des plus
     * proches aux plus éloignés (la bonne réponse est « la plus grande »).
     *
     * @param  list<array<string, mixed>>  $same
     * @return list<array<string, mixed>>
     */
    private function belowBy(array $same, string $field, int $value, float $ratio): array
    {
        $below = array_values(array_filter($same, fn (array $o): bool => $o['data'][$field] * $ratio <= $value));
        usort($below, fn (array $a, array $b): int => [$b['data'][$field], $a['id']] <=> [$a['data'][$field], $b['id']]);

        return $below;
    }

    /**
     * Séries du même type mises en service au moins `$gap` ans après le sujet, de la plus proche à la plus lointaine.
     *
     * @param  list<array<string, mixed>>  $same
     * @param  array<string, mixed>  $subject
     * @return list<array<string, mixed>>
     */
    private function laterSeries(array $same, array $subject, int $gap): array
    {
        $later = array_values(array_filter($same, fn (array $o): bool => $o['data']['type'] === $subject['data']['type'] && $o['data']['from'] >= $subject['data']['from'] + $gap));
        usort($later, fn (array $a, array $b): int => [$a['data']['from'], $a['id']] <=> [$b['data']['from'], $b['id']]);

        return $later;
    }

    /**
     * Sujets triés par écart croissant (`$gap`), à égalité par identifiant.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function byGap(array $items, callable $gap): array
    {
        usort($items, fn (array $a, array $b): int => [$gap($a), $a['id']] <=> [$gap($b), $b['id']]);

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function sortedBy(array $items, callable $key): array
    {
        usort($items, fn (array $a, array $b): int => $key($a) <=> $key($b));

        return $items;
    }

    /**
     * Mauvaise réponse propre à une question (année, compagnie) : ni sujet du vivier ni source.
     *
     * @param  array<string, mixed>  $subject
     * @return array<string, mixed>
     */
    private function synthetic(array $subject, string $label): array
    {
        return ['id' => $subject['id'].'#'.$label, 'key' => self::optionKey((string) $subject['id'], $label), 'label' => $label, 'topic' => $subject['topic']];
    }

    /**
     * Indices en texte : rangées `{label, value}` déjà dans la langue de la page.
     *
     * @param  array<string, mixed>  $s
     * @return list<array{label: string, value: string}>
     */
    private function hints(QuizTopic $topic, array $s): array
    {
        $rows = [];
        if (in_array($topic, [QuizTopic::OpeningYear, QuizTopic::ElectrificationYear, QuizTopic::Company], true)) {
            $id = (int) ($s['data']['line_id'] ?? DB::table('lines')->where('slug', $s['data']['line'])->value('id'));
            $names = $id > 0 ? array_slice(array_column(LineFacts::departments($id), 'name'), 0, 4) : [];
            if ($names !== []) {
                $rows[] = ['label' => __('quiz.hints.departments'), 'value' => implode(', ', $names)];
            }
        }
        if (in_array($topic, [QuizTopic::Architect, QuizTopic::HeritageYear], true)) {
            if (($s['data']['city'] ?? null) !== null) {
                $rows[] = ['label' => __('quiz.facts.hint.city'), 'value' => (string) $s['data']['city']];
            }
            if (($s['data']['built'] ?? null) !== null) {
                $rows[] = ['label' => __('quiz.facts.hint.built'), 'value' => (string) $s['data']['built']];
            }
        }

        return $rows;
    }

    /**
     * Sources de la réponse : celles du sujet et des autres propositions qui en ont, sans doublon, avec nom, lien et
     * licence courte.
     *
     * @param  list<array<string, mixed>>  $options
     * @return list<array{name: string, title: ?string, url: ?string, license: ?string, license_url: ?string}>
     */
    private function sources(array $options, string $locale): array
    {
        $out = [];
        foreach ($options as $o) {
            foreach ($o['sources'] ?? [] as $src) {
                $key = $src['provider'].'|'.($src['url'] ?? '');
                if (isset($out[$key]) || count($out) >= 5) {
                    continue;
                }
                [$license, $licenseUrl] = self::LICENSES[$src['provider']] ?? [null, null];
                $name = trans()->has('quiz.facts.sources.'.$src['provider'], $locale)
                    ? (string) trans('quiz.facts.sources.'.$src['provider'], [], $locale)
                    : $this->chronology->sourceName((string) $src['provider'], $locale);
                $out[$key] = ['name' => $name, 'title' => null, 'url' => $src['url'] ?? null, 'license' => $license, 'license_url' => $licenseUrl];
            }
        }

        return array_values($out);
    }
}
