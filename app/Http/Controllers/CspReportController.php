<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Rapports de violation de la politique de sécurité du contenu (ContentSecurityPolicy), envoyés
 * par le navigateur sans action du visiteur : ancien format `application/csp-report` (report-uri)
 * et API Reporting `application/reports+json` (report-to, liste de rapports).
 *
 * Journal `csp` (config/logging.php, 30 jours) sans donnée personnelle : ni adresse IP, ni
 * navigateur, ni compte, ni cookie ; adresse de la page et du script réduites à l'origine et au
 * chemin (la requête d'une adresse peut porter un jeton ou une adresse électronique), ressource
 * bloquée réduite à son origine ; extrait de code (`sample`) jamais gardé. Débit limité par adresse
 * IP (limiteur `csp-reports`), corps de 16 Ko au plus, dix rapports au plus par envoi.
 */
class CspReportController extends Controller
{
    private const MAX_BYTES = 16384;

    private const MAX_REPORTS = 10;

    public function __invoke(Request $request): Response
    {
        $raw = $request->getContent();
        if ($raw === '' || strlen($raw) > self::MAX_BYTES) {
            return response()->noContent();
        }
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return response()->noContent();
        }

        foreach (array_slice(self::reports($data), 0, self::MAX_REPORTS) as $report) {
            Log::channel('csp')->warning('csp-violation', $report);
        }

        return response()->noContent();
    }

    /**
     * Rapports lus dans l'un ou l'autre format, réduits aux champs utiles.
     *
     * @param  array<mixed>  $data
     * @return list<array{document: ?string, directive: ?string, blocked: ?string, source: ?string, line: ?int, column: ?int, disposition: ?string}>
     */
    public static function reports(array $data): array
    {
        $bodies = [];
        if (isset($data['csp-report']) && is_array($data['csp-report'])) {
            $r = $data['csp-report'];
            $bodies[] = [
                'document' => $r['document-uri'] ?? null,
                'directive' => $r['effective-directive'] ?? $r['violated-directive'] ?? null,
                'blocked' => $r['blocked-uri'] ?? null,
                'source' => $r['source-file'] ?? null,
                'line' => $r['line-number'] ?? null,
                'column' => $r['column-number'] ?? null,
                'disposition' => $r['disposition'] ?? null,
            ];
        } elseif (array_is_list($data)) {
            foreach ($data as $item) {
                if (! is_array($item) || ($item['type'] ?? null) !== 'csp-violation' || ! is_array($item['body'] ?? null)) {
                    continue;
                }
                $b = $item['body'];
                $bodies[] = [
                    'document' => $b['documentURL'] ?? $item['url'] ?? null,
                    'directive' => $b['effectiveDirective'] ?? null,
                    'blocked' => $b['blockedURL'] ?? null,
                    'source' => $b['sourceFile'] ?? null,
                    'line' => $b['lineNumber'] ?? null,
                    'column' => $b['columnNumber'] ?? null,
                    'disposition' => $b['disposition'] ?? null,
                ];
            }
        }

        return array_map(fn (array $b): array => [
            'document' => self::page($b['document']),
            'directive' => self::word($b['directive']),
            'blocked' => self::blocked($b['blocked']),
            'source' => self::page($b['source']),
            'line' => is_numeric($b['line']) ? (int) $b['line'] : null,
            'column' => is_numeric($b['column']) ? (int) $b['column'] : null,
            'disposition' => in_array($b['disposition'], ['enforce', 'report'], true) ? $b['disposition'] : null,
        ], $bodies);
    }

    /** Origine et chemin d'une adresse, sans requête ni fragment. */
    private static function page(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }
        $p = parse_url($url);
        if (! isset($p['scheme'], $p['host'])) {
            return self::word($url);
        }

        return mb_substr($p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '').($p['path'] ?? ''), 0, 300);
    }

    /** Ressource bloquée : mot-clé (`inline`, `eval`, `data`, `blob`…) ou origine seule. */
    private static function blocked(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }
        $p = parse_url($url);

        return isset($p['scheme'], $p['host'])
            ? $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '')
            : self::word(explode(':', $url)[0]);
    }

    /** Valeur courte faite de lettres, chiffres et tirets (nom de directive, mot-clé), null sinon. */
    private static function word(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[a-z0-9-]{1,40}$/i', $value) ? strtolower($value) : null;
    }
}
