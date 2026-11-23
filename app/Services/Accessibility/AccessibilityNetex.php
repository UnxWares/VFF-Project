<?php

namespace App\Services\Accessibility;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use XMLReader;

/**
 * Jeu « Accessibilité des gares » de SNCF Gares & Connexions (ODbL), au format réglementaire NeTEx : un `StopPlace` par gare,
 * dont l'identifiant porte celui de la gare dans le référentiel « Gares de voyageurs » (`FR:<INSEE>:LMO:<uuid>:LOC`).
 *
 * Téléchargement : les métadonnées du jeu sont lues d'abord (licence ODbL et éditeur vérifiés, rien n'est gardé sinon), puis le
 * fichier XML est écrit dans `<dossier>/netex-accessibilite-gares.xml` avec un `.nobackup` (cache régénérable).
 * Lecture : en flux (le fichier fait une quinzaine de Mo), un enregistrement par gare.
 */
final class AccessibilityNetex
{
    public const DATASET = 'accessibilite_gares';

    public const PUBLISHER = 'Gares & Connexions';

    private const API = 'https://data.sncf.com/api/explore/v2.1/catalog/datasets/';

    private const NS = 'http://www.netex.org.uk/netex';

    private const FILE = 'netex-accessibilite-gares.xml';

    /**
     * @return array{path: string, license: string, modified: ?string, title: string}
     *
     * @throws RuntimeException jeu injoignable, licence ou éditeur inattendus
     */
    public function fetch(string $dir, bool $refresh): array
    {
        $dir = rtrim($dir, '/');
        $path = $dir.'/'.self::FILE;
        $metaPath = $dir.'/netex-accessibilite-gares.json';
        if (! $refresh && is_file($path) && is_file($metaPath)) {
            $meta = json_decode((string) file_get_contents($metaPath), true);
            if (is_array($meta) && filesize($path) > 0) {
                return ['path' => $path, 'license' => (string) ($meta['license'] ?? '?'), 'modified' => $meta['modified'] ?? null, 'title' => (string) ($meta['title'] ?? self::DATASET)];
            }
        }

        $http = Http::withUserAgent(AccessibilityDatasets::USER_AGENT)->timeout(180);
        try {
            $meta = $http->get(self::API.self::DATASET);
            $link = $meta->ok() ? $http->get(self::API.self::DATASET.'/records', ['limit' => 1]) : null;
        } catch (ConnectionException) {
            throw new RuntimeException('SNCF Open Data injoignable ('.self::DATASET.') : rien lu.');
        }
        if (! $meta->ok() || ! $link?->ok()) {
            throw new RuntimeException('SNCF Open Data a répondu une erreur ('.self::DATASET.') : rien lu.');
        }
        $default = (array) ($meta->json('metas.default') ?? []);
        $license = (string) ($default['license'] ?? '');
        if (! str_contains($license, 'ODbL') || ($default['publisher'] ?? '') !== self::PUBLISHER) {
            throw new RuntimeException('Licence ou éditeur inattendus pour '.self::DATASET." (« {$license} », « ".($default['publisher'] ?? '').' ») : rien lu.');
        }
        $url = (string) ($link->json('results.0.download') ?? '');
        if (! str_starts_with($url, 'https://eu.ftp.opendatasoft.com/sncf/')) {
            throw new RuntimeException('Adresse du fichier NeTEx inattendue : rien lu.');
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (! is_file($dir.'/.nobackup')) {
            touch($dir.'/.nobackup');
        }
        try {
            $response = Http::withUserAgent(AccessibilityDatasets::USER_AGENT)->timeout(300)->sink($path.'.part')->get($url);
        } catch (ConnectionException) {
            throw new RuntimeException('Fichier NeTEx injoignable : rien lu.');
        }
        if (! $response->ok() || ! is_file($path.'.part') || filesize($path.'.part') < 1000) {
            throw new RuntimeException('Fichier NeTEx vide ou en erreur : rien lu.');
        }
        rename($path.'.part', $path);
        $modified = isset($default['modified']) ? substr((string) $default['modified'], 0, 10) : null;
        $title = (string) ($default['title'] ?? self::DATASET);
        file_put_contents($metaPath, json_encode(['license' => $license, 'modified' => $modified, 'title' => $title], JSON_UNESCAPED_UNICODE));

        return ['path' => $path, 'license' => $license, 'modified' => $modified, 'title' => $title];
    }

    /**
     * Les gares du fichier, par identifiant de gare (uuid du référentiel « Gares de voyageurs »).
     *
     * @return array<string, array{name: string, mobility: ?string, step_free: ?bool, wheelchair: ?bool, tactile: ?bool, comment: ?string, quays_total: int, quays_step_free: int, lifts: list<array{name: string, description: ?string}>, escalators: int, travelators: int}>
     */
    public function stations(string $path): array
    {
        $reader = new XMLReader;
        if (! $reader->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException("Fichier NeTEx illisible : {$path}");
        }
        $out = [];
        $more = $reader->read();
        while ($more) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'StopPlace') {
                $node = $reader->expand();
                $found = $node instanceof DOMElement ? self::stopPlace($node) : null;
                if ($found !== null) {
                    $out[$found[0]] = $found[1];
                }
                $more = $reader->next();

                continue;
            }
            $more = $reader->read();
        }
        $reader->close();

        return $out;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    public static function stopPlace(DOMNode $node): ?array
    {
        $doc = new DOMDocument;
        $doc->appendChild($doc->importNode($node, true));
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('n', self::NS);

        $id = (string) $xp->evaluate('string(/n:StopPlace/@id)');
        if (! preg_match('/^FR:[^:]*:LMO:([0-9a-f-]{36}):LOC$/i', $id, $m)) {
            return null;
        }
        $base = '/n:StopPlace/n:AccessibilityAssessment';
        $flag = fn (string $path, string $name): ?bool => match (trim((string) $xp->evaluate("string({$path}//n:{$name})"))) {
            'true' => true,
            'false' => false,
            default => null,
        };
        $mobility = match (trim((string) $xp->evaluate("string({$base}/n:MobilityImpairedAccess)"))) {
            'partial' => 'partial',
            'false' => 'none',
            default => null,
        };

        $quaysTotal = 0;
        $quaysStepFree = 0;
        foreach ($xp->query('/n:StopPlace/n:quays/n:Quay') ?: [] as $quay) {
            $quaysTotal++;
            if (trim((string) $xp->evaluate('string(n:AccessibilityAssessment//n:StepFreeAccess)', $quay)) === 'true') {
                $quaysStepFree++;
            }
        }
        $lifts = [];
        foreach ($xp->query('//n:LiftEquipment') ?: [] as $lift) {
            $description = trim((string) $xp->evaluate('string(n:Description)', $lift));
            $lifts[] = ['name' => trim((string) $xp->evaluate('string(n:Name)', $lift)), 'description' => $description !== '' ? $description : null];
        }
        $comment = trim((string) $xp->evaluate("string({$base}/n:Comment)"));

        return [strtolower($m[1]), [
            'name' => trim((string) $xp->evaluate('string(/n:StopPlace/n:Name)')),
            'mobility' => $mobility,
            'step_free' => $flag($base, 'StepFreeAccess'),
            'wheelchair' => $flag($base, 'WheelchairAccess'),
            'tactile' => $flag($base, 'TactileGuidanceAvailable'),
            'comment' => $comment !== '' ? $comment : null,
            'quays_total' => $quaysTotal,
            'quays_step_free' => $quaysStepFree,
            'lifts' => $lifts,
            'escalators' => (int) $xp->evaluate('count(//n:EscalatorEquipment)'),
            'travelators' => (int) $xp->evaluate('count(//n:TravelatorEquipment)'),
        ]];
    }
}
