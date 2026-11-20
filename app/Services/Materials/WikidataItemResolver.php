<?php

namespace App\Services\Materials;

use App\Services\Media\CommonsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Vérification d'un identifiant Wikidata proposé par un contributeur (formulaire « Proposer une
 * série », mutation GraphQL) : l'élément existe-t-il, sous quel identifiant (un élément fusionné
 * redirige vers un autre), avec quel libellé ?
 *
 * Une seule requête à l'API publique de Wikidata (wbgetentities, libellés et nature P31), faite par
 * le serveur pendant la requête du site : pas de reprise longue, délai de 10 s, User-Agent générique
 * de VFF (CommonsClient::USER_AGENT). Le résultat reste une heure en cache, un élément absent aussi :
 * vérifier puis envoyer le formulaire ne coûte qu'un appel.
 */
final class WikidataItemResolver
{
    public const API = 'https://www.wikidata.org/w/api.php';

    private const CACHE_SECONDS = 3600;

    /** Identifiant d'un élément Wikidata (« Q3574398 »), sans espace ni préfixe d'adresse. */
    public const QID_PATTERN = '/^Q[1-9]\d{0,11}$/';

    /**
     * Identifiant « Q123 » d'une saisie : identifiant seul (casse indifférente) ou adresse d'un élément
     * (`https://www.wikidata.org/wiki/Q123`, `…/entity/Q123`, `…/wiki/Special:EntityPage/Q123`).
     * Null si la saisie n'en désigne pas.
     */
    public static function normalize(string $input): ?string
    {
        $input = trim($input);
        if (preg_match('#^https?://(?:www\.|m\.)?wikidata\.org/(?:wiki/(?:Special:EntityPage/)?|entity/)(Q\d+)(?:[\#?].*)?$#i', $input, $m)) {
            $input = $m[1];
        }
        $qid = strtoupper($input);

        return preg_match(self::QID_PATTERN, $qid) === 1 ? $qid : null;
    }

    /**
     * L'élément, ou null s'il n'existe pas (jamais créé ou supprimé). `id` est l'identifiant final,
     * différent de celui demandé quand l'élément a été fusionné ; `label` le libellé français, sinon
     * anglais ; `instance_of` les valeurs de « nature de l'élément » (P31).
     *
     * @return array{id: string, label: ?string, description: ?string, instance_of: list<string>}|null
     *
     * @throws RuntimeException Wikidata injoignable ou en erreur
     */
    public function lookup(string $qid): ?array
    {
        $key = 'wikidata-item:'.$qid;
        $cached = Cache::get($key);
        if (is_array($cached) || $cached === false) {
            return $cached ?: null;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => CommonsClient::USER_AGENT])
                ->timeout(10)
                ->get(self::API, [
                    'action' => 'wbgetentities',
                    'ids' => $qid,
                    'props' => 'labels|descriptions|claims',
                    'languages' => 'fr|en',
                    'redirects' => 'yes',
                    'format' => 'json',
                    'formatversion' => 2,
                ])
                ->throw();
        } catch (ConnectionException|RequestException $e) {
            throw new RuntimeException('Wikidata injoignable : '.$e->getMessage(), previous: $e);
        }

        $json = $response->json() ?? [];
        $code = (string) ($json['error']['code'] ?? '');
        if ($code === 'no-such-entity') {
            Cache::put($key, false, self::CACHE_SECONDS);

            return null;
        }
        if ($code !== '' || ! is_array($json['entities'] ?? null)) {
            throw new RuntimeException('API Wikidata : '.($json['error']['info'] ?? 'réponse illisible'));
        }

        $entity = reset($json['entities']);
        if (! is_array($entity) || isset($entity['missing']) || ! is_string($entity['id'] ?? null)) {
            Cache::put($key, false, self::CACHE_SECONDS);

            return null;
        }

        $item = [
            'id' => $entity['id'],
            'label' => $entity['labels']['fr']['value'] ?? $entity['labels']['en']['value'] ?? null,
            'description' => $entity['descriptions']['fr']['value'] ?? $entity['descriptions']['en']['value'] ?? null,
            'instance_of' => array_values(array_filter(array_map(
                fn (mixed $claim): ?string => is_array($claim) ? ($claim['mainsnak']['datavalue']['value']['id'] ?? null) : null,
                $entity['claims']['P31'] ?? [],
            ))),
        ];
        Cache::put($key, $item, self::CACHE_SECONDS);

        return $item;
    }
}
