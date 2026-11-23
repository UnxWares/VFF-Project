<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Api\SourceRegistry;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Sources et licences', "Le registre des fournisseurs de données de VFF et de leurs licences : ce que désigne chaque `provenance.provider` des autres réponses et ce qu'il faut citer.", weight: 8)]
class SourceController extends Controller
{
    /**
     * Registre des sources et licences.
     *
     * Chaque fournisseur de données de VFF, tel que VFF a vérifié ses
     * conditions de réutilisation. C'est à ce registre que renvoie le bloc
     * `provenance` des événements, des tronçons, des gares et des sources
     * (`provenance.provider` = `provider` ici).
     *
     * - `licence` : licence ou conditions de réutilisation, en français ;
     * - `license_status` : `verified` (licence ouverte écrite, prouvée par
     *   les pages de `proof`), `uncertain` (autorisation particulière, pas de
     *   licence ouverte ou conditions à lire : reprenez ces données seulement
     *   avec la mention et le lien demandés), `forbidden` (réutilisation
     *   exclue) ; `open_licence` : `true` pour `verified` ;
     * - `mention` : la mention à reproduire en réutilisant les données ;
     * - `citation` : phrase exacte de la licence ou des conditions, telle que
     *   lue sur les pages de `proof` le `checked_on` (`AAAA-MM-JJ`) ;
     * - `domains` : domaines des liens `source_url` qui désignent ce
     *   fournisseur ;
     * - `source_values` : par table, les valeurs du champ `source` qui
     *   renvoient à ce fournisseur (`line_events` : événements,
     *   `line_segments` : tracés, `station_sources`, `station_events`,
     *   `media`…).
     *
     * `meta.database_licence` : licence de la base VFF elle-même (ODbL 1.0,
     * attribution « VFF - Voies Ferrées de France » avec un lien).
     */
    #[ApiResponse(200, 'Le registre (extrait : deux fournisseurs).', examples: [[
        'data' => [[
            'provider' => 'osm', 'name' => 'OpenStreetMap', 'licence' => 'ODbL 1.0 (Open Database License)', 'license_status' => 'verified', 'open_licence' => true,
            'mention' => '© contributeurs OpenStreetMap, ODbL 1.0 (https://www.openstreetmap.org/copyright)',
            'citation' => 'licensed under the Open Data Commons Open Database License (ODbL).',
            'proof' => ['https://www.openstreetmap.org/copyright', 'https://opendatacommons.org/licenses/odbl/1-0/', 'https://osmfoundation.org/wiki/Licence/Community_Guidelines/Collective_Database_Guideline_Guideline'],
            'checked_on' => '2026-10-20', 'domains' => ['openstreetmap.org'],
            'source_values' => ['line_events' => ['osm'], 'line_segments' => ['osm', 'declared'], 'station_events' => ['osm', 'osm:note'], 'station_sources' => ['osm']],
        ], [
            'provider' => 'archeoferroviaire', 'name' => 'archeoferroviaire.free.fr (J. Randé, F. Librini, K. Moreau ; tracés aussi de F. Hespert et Y. Zwartjes)',
            'licence' => 'Tous droits réservés (mentions légales) ; usage par VFF non commercial, avec crédit et lien', 'license_status' => 'uncertain', 'open_licence' => false,
            'mention' => 'archeoferroviaire.free.fr (J. Randé, F. Librini, K. Moreau), usage non commercial, avec lien vers l’article (pas de licence ouverte : reprise avec cette mention et le lien de la source seulement)',
            'citation' => 'Aucune reproduction, même partielle, en dehors de celles prévues à l\'article L 122-5 du code de la propriété intellectuelle, ne peut être faite de ce site sans l\'autorisation expresse de l\'auteur. [...] Toute diffusion ou utilisation à des fins commerciales ou publicitaires est exclue.',
            'proof' => ['http://archeoferroviaire.free.fr/v31/spip.php?page=rubrique&id_rubrique=3', 'https://macarte.ign.fr/cgu'],
            'checked_on' => '2026-10-20', 'domains' => ['archeoferroviaire.free.fr'],
            'source_values' => ['line_events' => ['archeoferroviaire'], 'line_reference_parts' => ['archeoferroviaire'], 'line_segments' => ['archeoferroviaire']],
        ]],
        'meta' => ['database_licence' => SourceRegistry::DATABASE_LICENCE],
    ]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(SourceRegistry $registry): JsonResponse
    {
        return response()->json([
            'data' => $registry->all(),
            'meta' => ['database_licence' => SourceRegistry::DATABASE_LICENCE],
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Un fournisseur.
     *
     * Même fiche que dans le registre, pour un seul fournisseur : la clé est
     * celle de `provenance.provider` dans les autres réponses (événements,
     * tronçons, sources de gare). On y lit la licence, sa solidité
     * (`license_status`), la mention à reproduire, la phrase exacte des
     * conditions et les pages qui les établissent, avec leur date de lecture.
     */
    #[PathParameter('provider', 'Clé du fournisseur (`osm`, `sncf`, `ign`, `ohm`, `wikidata`, `wikipedia`, `commons`, `jorf`, `lignes-oubliees`…).', example: 'sncf')]
    #[ApiResponse(200, 'Le fournisseur `ign`.', examples: [[
        'data' => [
            'provider' => 'ign', 'name' => 'IGN (BD TOPO, BD ORTHO historique)',
            'licence' => "Licence Ouverte 2.0 (Etalab), données publiques de l'IGN libres depuis le 1er janvier 2021",
            'license_status' => 'verified', 'open_licence' => true,
            'mention' => 'IGN (BD TOPO, BD ORTHO historique), Licence Ouverte 2.0',
            'citation' => "Les Contenus IGN sont soumis à la licence libre Etalab 2.0. [...] sous réserve de mentionner la paternité de l'IGN : sa source (au moins le nom de l'IGN) et la date de dernière mise à jour des Contenu IGN réutilisés.",
            'proof' => ['https://www.data.gouv.fr/datasets/bd-topo-r', 'https://www.data.gouv.fr/datasets/bd-ortho-r-historique', 'https://macarte.ign.fr/cgu'],
            'checked_on' => '2026-10-20', 'domains' => ['ign.fr', 'geopf.fr', 'cartes.gouv.fr'],
            'source_values' => ['line_reference_parts' => ['bdtopo', 'ign-historique'], 'line_segments' => ['bdtopo', 'ign-historique'], 'source_traces' => ['bdtopo', 'ign-historique']],
        ],
    ]])]
    #[ApiResponse(404, 'Fournisseur inconnu.', examples: [ErrorExamples::SOURCE_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(string $provider, SourceRegistry $registry): JsonResponse
    {
        $entry = $registry->find($provider);
        if ($entry === null) {
            return response()->json(ErrorExamples::SOURCE_NOT_FOUND, 404);
        }

        return response()->json(['data' => $entry])->header('Cache-Control', 'public, max-age=3600');
    }
}
