<?php

namespace App\Mcp\Support;

use App\Support\SourceRights;

/**
 * Bloc d'attribution joint à chaque réponse du serveur MCP : la mention exigée par la licence de
 * la base (VFF sous ODbL 1.0) et celle de chaque fournisseur dont proviennent les données
 * renvoyées (registre database/data/source_rights.php, lu par SourceRights).
 *
 * VFF, OpenStreetMap et SNCF Open Data figurent toujours : la base est sous ODbL et dérive en
 * partie des tracés OSM et des référentiels SNCF (mentions exigées par l'ODbL, art. 4.3).
 * Les fournisseurs sans licence ouverte écrite (statut autre que « sur ») sont signalés
 * (`open_licence` = false) avec la condition de réutilisation à respecter.
 */
final class Attribution
{
    /** Fournisseurs cités dans toute réponse (base ODbL dérivée d'OSM et des données SNCF). */
    public const ALWAYS = ['vff', 'osm', 'sncf'];

    public function __construct(private readonly SourceRights $rights) {}

    /**
     * Fournisseur d'une donnée (valeur de la colonne de provenance d'une table, lien cité).
     */
    public function providerFor(string $table, ?string $source, ?string $url = null): ?string
    {
        return $this->rights->providerFor($table, $source, $url);
    }

    /** Fournisseur dont un domaine déclaré héberge ce lien (source citée par son adresse seule), null sinon. */
    public function providerForUrl(?string $url): ?string
    {
        return $this->rights->providerForUrl($url);
    }

    /**
     * @param  iterable<?string>  $providers  fournisseurs des données renvoyées (doublons et null ignorés)
     * @return array{notice: string, sources: list<array{provider: string, mention: string, open_licence: bool}>}
     */
    public function block(iterable $providers, string $lang): array
    {
        $keys = self::ALWAYS;
        foreach ($providers as $p) {
            if ($p !== null && $p !== '' && ! in_array($p, $keys, true)) {
                $keys[] = $p;
            }
        }

        return [
            'notice' => (string) trans('mcp.attribution.notice', [], $lang),
            'sources' => array_map(fn (string $k) => $this->source($k, $lang), $keys),
        ];
    }

    /**
     * @return array{provider: string, mention: string, open_licence: bool}
     */
    public function source(string $key, string $lang): array
    {
        $p = $this->rights->provider($key);
        $open = ($p['statut'] ?? null) === SourceRights::SAFE;
        $mentionKey = 'mcp.attribution.providers.'.$key;
        $mention = trans()->has($mentionKey, $lang)
            ? (string) trans($mentionKey, [], $lang)
            : trim(($p['nom'] ?? $key).(isset($p['licence']) ? ' ('.$p['licence'].')' : ''));
        if (! $open) {
            $mention .= ' '.trans('mcp.attribution.restricted', [], $lang);
        }

        return ['provider' => $key, 'mention' => $mention, 'open_licence' => $open];
    }

    /**
     * Fiche publique d'un fournisseur (outil des sources et ressource des licences) : nom, licence,
     * pages qui l'établissent, date de lecture. Les notes internes du registre (risques, accords,
     * remarques de travail) ne sont pas publiées.
     *
     * @return array{provider: string, name: string, licence: string, open_licence: bool, mention: string, proof: list<string>, checked_on: ?string}|null
     */
    public function publicProvider(string $key, string $lang): ?array
    {
        $p = $this->rights->provider($key);
        if ($p === null) {
            return null;
        }
        $source = $this->source($key, $lang);

        return [
            'provider' => $key,
            'name' => (string) ($p['nom'] ?? $key),
            'licence' => (string) ($p['licence'] ?? ''),
            'open_licence' => $source['open_licence'],
            'mention' => $source['mention'],
            'proof' => array_values(array_map('strval', (array) ($p['preuve'] ?? []))),
            'checked_on' => isset($p['lu_le']) ? (string) $p['lu_le'] : null,
        ];
    }

    /** @return list<string> */
    public function providerKeys(): array
    {
        return array_keys($this->rights->providers());
    }
}
