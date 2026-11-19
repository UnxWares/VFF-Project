<?php

namespace App\Services\Accidents;

use App\Enums\AccidentDatePrecision;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Accidents ferroviaires du « Bilan général des enquêtes » du BEA-TT (Bureau d'enquêtes sur les
 * accidents de transport terrestre), tableau PDF publié sous Licence Ouverte 2.0 (« Sauf mention
 * contraire, tous les contenus de ce site sont sous licence etalab-2.0 »).
 *
 * Le lien du PDF change à chaque mise à jour : il est relu sur la page « Historique des
 * enquêtes ». Le PDF est converti en XML positionné par `pdftohtml` (poppler-utils) ; sans cet
 * outil (conteneur PHP de développement), la conversion déjà en cache est relue
 * (storage/app/data/accidents/bea-tt/<nom du PDF>.xml).
 *
 * Rangées gardées : enquêtes ferroviaires (F) et passages à niveau (PN) ; parmi les transports
 * guidés (TG), celles qui concernent un train (train touristique, à crémaillère, RER, tram-train),
 * pas les métros ni les tramways urbains. Accidents survenus hors de France écartés.
 */
final class BeaTtAccidentCollector
{
    public const PAGE = 'https://www.bea-tt.developpement-durable.gouv.fr/historique-des-enquetes-a1187.html';

    public const BASE = 'https://www.bea-tt.developpement-durable.gouv.fr/';

    /** Dernier PDF connu (09/11/2026), si la page ne donne plus de lien. */
    public const FALLBACK_PDF = 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/20261109_bilan-general-enquetes-beatt.pdf';

    public const LICENCE = 'Licence Ouverte 2.0 (Etalab)';

    public const LICENCE_URL = 'https://www.etalab.gouv.fr/licence-ouverte-open-licence/';

    /**
     * Intitulés erronés du bilan, corrigés à la main (date|lieu lu → [commune, département]),
     * chacun vérifié dans la table des communes et signalé sur la fiche (place_corrected).
     */
    public const PLACE_CORRECTIONS = [
        // Le bilan indique « (64) » ; Crisenoy est en Seine-et-Marne, sur la LGV Sud-Est.
        '2024-12-24|Crisenoy' => ['Crisenoy', '77'],
        // Tunnel ferroviaire du Fréjus : tête française sur la commune de Modane (73), pas Fréjus (83).
        '2022-10-09|Fréjus' => ['Modane', '73'],
        // Faute de frappe du bilan (« Toucques »).
        '2017-11-02|Bonneville-sur-Toucques' => ['Bonneville-sur-Touques', '14'],
    ];

    /** @var array<string, int> rangées écartées, par motif */
    public array $rejected = [];

    public ?string $pdfUrl = null;

    public ?string $warning = null;

    public function __construct(
        private readonly BeaTtInventoryParser $parser,
        private readonly BeaTtTitleReader $titles,
    ) {}

    /** @return list<AccidentCandidate> */
    public function collect(AccidentSourceClient $client): array
    {
        $xml = $this->inventoryXml($client);
        if ($xml === null) {
            return [];
        }
        $out = [];
        foreach ($this->parser->parse($xml) as $row) {
            $reason = $this->rejection($row);
            if ($reason !== null) {
                $this->rejected[$reason] = ($this->rejected[$reason] ?? 0) + 1;

                continue;
            }
            $out[] = $this->candidate($row);
        }

        return $out;
    }

    /**
     * Motif d'écart d'une rangée, null si elle est gardée.
     *
     * @param  array{date: string, title: string, mode: string, deaths: ?int, url: ?string}  $row
     */
    public function rejection(array $row): ?string
    {
        if (str_contains($row['title'], '(Belgique)')) {
            return 'outside_france';
        }
        if (in_array($row['mode'], ['F', 'PN'], true)) {
            return null;
        }
        if ($row['mode'] !== 'TG') {
            return 'other_mode';
        }
        $rail = preg_match('/\b(?:train|RER|tram-train|crémaillère|gare)\b/iu', $row['title'])
            && ! preg_match('/\b(?:métro|tramway|tramways)\b/iu', $row['title']);

        return $rail ? null : 'urban_transit';
    }

    /** @param array{date: string, title: string, mode: string, deaths: ?int, url: ?string} $row */
    public function candidate(array $row): AccidentCandidate
    {
        $facts = $this->titles->read($row['title'], $row['mode']);
        $place = $facts['place'] ?? $row['title'];
        $key = 'bea-tt:'.$row['date'].'-'.Str::slug(Str::limit($place, 60, '')).'-'.strtolower($row['mode']);
        $c = new AccidentCandidate($key);
        $c->date = $row['date'];
        $c->precision = AccidentDatePrecision::Day;
        $c->dateSource = 'bea-tt';
        $c->kind = $facts['kind'];
        $c->kindSource = 'bea-tt';
        $c->communeName = $facts['place'];
        $c->departmentCode = $facts['department'];
        if (($fix = self::PLACE_CORRECTIONS[$row['date'].'|'.$facts['place']] ?? null) !== null) {
            [$c->communeName, $c->departmentCode] = $fix;
            $c->note('place_corrected', "{$facts['place']} ({$facts['department']}) → {$fix[0]} ({$fix[1]})");
        }
        if (($fromUrl = self::placeFromUrl($row['url'])) !== null) {
            $c->placeHints[] = $fromUrl;
        }
        $c->levelCrossing = $facts['level_crossing'];
        $c->stationHint = $facts['station'];
        foreach ($facts['trains'] as $t) {
            $c->addTrain($t, 'bea-tt');
        }
        $url = $row['url'] ?? $this->pdfUrl ?? self::FALLBACK_PDF;
        if ($row['deaths'] !== null) {
            $c->deaths['bea-tt'] = $row['deaths'];
            $c->tollUrls['bea-tt'] = $url;
        }
        $c->addSource(new AccidentSourceRef(
            provider: 'bea-tt',
            externalId: substr($key, strlen('bea-tt:')),
            role: $row['url'] !== null ? 'report' : 'inventory',
            title: $row['title'],
            url: $url,
            licence: self::LICENCE,
            licenceUrl: self::LICENCE_URL,
            facts: array_filter([
                'date' => $row['date'], 'deaths' => $row['deaths'], 'mode' => $row['mode'],
                'inventory' => $this->pdfUrl ?? self::FALLBACK_PDF,
            ], fn ($v) => $v !== null),
        ));

        return $c;
    }

    /**
     * Commune et département tirés du lien d'un rapport, quand il suit la forme
     * « <commune>-<département>-<jj>-<mm>-<aaaa>-… » (« solaize-69-13-03-2017-deraillement… »).
     *
     * @return array{0: string, 1: string}|null
     */
    public static function placeFromUrl(?string $url): ?array
    {
        $path = basename((string) parse_url((string) $url, PHP_URL_PATH));
        if (! preg_match('/^([a-z][a-z-]*?)-(\d{2}|2[ab])-\d{2}-\d{2}-\d{4}-/', $path, $m)) {
            return null;
        }

        return [str_replace('-', ' ', $m[1]), strtoupper($m[2])];
    }

    /** XML du bilan : PDF du jour converti par pdftohtml, sinon conversion en cache. */
    private function inventoryXml(AccidentSourceClient $client): ?string
    {
        $this->pdfUrl = $this->latestPdfUrl($client);
        $name = 'bea-tt/'.basename((string) parse_url($this->pdfUrl, PHP_URL_PATH));
        $xmlPath = $client->cacheDir().'/'.preg_replace('/\.pdf$/i', '', $name).'.xml';
        $pdf = $client->file($this->pdfUrl, $name);
        if ($pdf !== null && (! is_file($xmlPath) || filemtime($xmlPath) < filemtime($pdf))) {
            $base = preg_replace('/\.xml$/', '', $xmlPath);
            $result = Process::timeout(120)->run(['pdftohtml', '-xml', '-i', '-q', '-nodrm', $pdf, $base]);
            if (! $result->successful()) {
                $this->warning = 'pdftohtml indisponible : conversion en cache utilisée si elle existe ('.$xmlPath.')';
            }
        }
        if (! is_file($xmlPath)) {
            $this->warning = "Bilan du BEA-TT illisible : ni pdftohtml ni conversion en cache ({$xmlPath}).";

            return null;
        }

        return (string) file_get_contents($xmlPath);
    }

    /** Lien du PDF le plus récent sur la page « Historique des enquêtes ». */
    private function latestPdfUrl(AccidentSourceClient $client): string
    {
        $page = $client->file(self::PAGE, 'bea-tt/historique-des-enquetes.html');
        $html = $page !== null ? (string) file_get_contents($page) : '';
        if (preg_match('#href="((?:https?://[^"]+/)?IMG/pdf/[^"]*bilan[^"]*\.pdf)"#i', $html, $m)) {
            return str_starts_with($m[1], 'http') ? $m[1] : self::BASE.ltrim($m[1], '/');
        }

        return self::FALLBACK_PDF;
    }
}
