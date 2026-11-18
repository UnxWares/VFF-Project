<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use Carbon\CarbonInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-lignes-oubliees : fiches de lignes-oubliees.com → événements datés
 * PAR SECTION (PK, bornes nommées, précision de date), gares avec PK, lignes
 * fermées hors RFN créées (lo-<code>), PDF fermetures / déclassements.
 * Fiches de test réduites à la structure (dates, sections, PK) : aucun texte
 * rédigé de la source. Cache local (--from-cache) ou réponses simulées.
 */
class LignesOublieesCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const BASE = 'https://www.lignes-oubliees.com/';

    private Line $concarneau;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Sleep::fake();
        $this->concarneau = $this->rfnLine('476000', 'Ligne de Rosporden à Concarneau');
    }

    private function inventory(): string
    {
        return <<<'HTML'
            <table>
            <tr><th>Ligne</th><th>Numéro</th><th>Longueur</th><th>Région</th></tr>
            <tr><td class="texteg texteb"><a href="index.php?page=concarneau">Rosporden&nbsp;–&nbsp;Concarneau</a></td><td>476&nbsp;000</td><td>15,1</td><td>Bretagne</td></tr>
            <tr><td class="texteg texteb"><a href="index.php?page=volvic">Lapeyrouse&nbsp;–&nbsp;Volvic</a></td><td>709&nbsp;000</td><td>37,4</td><td>Auvergne-Rhône-Alpes</td></tr>
            <tr><td class="texteg">Laval&nbsp;–&nbsp;Pouancé</td><td>462&nbsp;000</td><td>60,4</td><td>Pays de la Loire / Bretagne</td></tr>
            <tr><td class="texteg texteb"><a href="index.php?page=orpheline">Embranchement de la Scierie</a></td><td>-</td><td>?</td><td>Bretagne</td></tr>
            </table>
            HTML;
    }

    /** @param  array<string, string>  $replace */
    private function concarneauFiche(array $replace = []): string
    {
        $html = <<<'HTML'
            <html><body><div id="contenu">
            <h1>Ligne de Rosporden à Concarneau</h1>
            <h2>Caractéristiques de la ligne</h2>
            <dl class="caracteristiques">
              <dt>Écartement :</dt><dd>Voie normale</dd>
              <dt>Longueur :</dt><dd>15,120 km</dd>
              <dt>Nombre de voies :</dt><dd>Voie unique</dd>
              <dt>Numéro de la ligne :</dt><dd>476 000</dd>
            </dl>
            <h2>Historique de la ligne</h2>
            <dl class="historique">
              <dt>Ouverture :</dt><dd><span>Rosporden - Concarneau :</span>30/06/1883</dd>
              <dt>Électrification  :</dt><dd><span>Rosporden - Concarneau :</span>non électrifiée</dd>
              <dt>Fermeture aux voyageurs :</dt><dd><span>Rosporden - Concarneau :</span>04/10/1959</dd>
              <dt>Fermeture aux marchandises :</dt>
                <dd><span>La Boissière (671+700) - Concarneau :</span>14/09/1998 (officielle : 06/06/2013)</dd>
                <dd><span>Rosporden - La Boissière (671+700) :</span>24/10/2021</dd>
              <dt>Dépose des voies :</dt>
                <dd><span>La Boissière (671,700) - Concarneau :</span>automne 2015</dd>
                <dd><span>Gare de Concarneau :</span>2016</dd>
              <dt>Déclassement :</dt><dd><span>Gare de Concarneau :</span>15/04/2016 et 12/09/2017 (voir ci-dessous)</dd>
              <dt>Rubrique inventée :</dt><dd><span>Rosporden - Concarneau :</span>1990</dd>
              <dt>État actuel de la ligne :</dt><dd><span>Rosporden - La Boissière (671,700) :</span>inexploitée</dd>
            </dl>
            <h2>Les gares</h2>
            <p class="textej">Rosporden (664,604) &gt; La Boissière (673,424) &gt; Concarneau (679,724)</p>
            <h2>Photographies</h2>
            <p>Rosporden (1,000) &gt; hors liste</p>
            </div></body></html>
            HTML;

        return strtr($html, $replace);
    }

    private function volvicFiche(): string
    {
        return <<<'HTML'
            <html><body><div id="contenu">
            <h1>Ligne de Lapeyrouse à Volvic</h1>
            <dl class="caracteristiques">
              <dt>Écartement :</dt><dd>Voie normale</dd>
              <dt>Longueur :</dt><dd>37,5 km</dd>
              <dt>Nombre de voies :</dt><dd>Voie unique</dd>
              <dt>Numéro de la ligne :</dt><dd>709 000</dd>
            </dl>
            <dl class="historique">
              <dt>Ouverture :</dt><dd><span>Lapeyrouse à Volvic :</span>1er juillet 1909</dd>
              <dt>Fermeture aux voyageurs :</dt><dd><span>Totalité de la ligne :</span>mars 1939</dd>
              <dt>Fermeture aux marchandises :</dt><dd><span>Lapeyrouse - Volvic (0,000 - 37,512) :</span>vers 1970</dd>
            </dl>
            </div></body></html>
            HTML;
    }

    private function orphelineFiche(): string
    {
        return <<<'HTML'
            <html><body><div id="contenu"><h1>Embranchement de la Scierie</h1>
            <dl class="historique"><dt>Ouverture :</dt><dd><span>Scierie - Moulin :</span>1900</dd></dl>
            </div></body></html>
            HTML;
    }

    private function cache(string $rel, string $content): void
    {
        $path = storage_path('app/sources/lignes-oubliees/'.$rel);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);
    }

    private function writeCache(): void
    {
        $this->cache('inventaire.html', $this->inventory());
        $this->cache('fiches/concarneau.html', $this->concarneauFiche());
        $this->cache('fiches/volvic.html', $this->volvicFiche());
        $this->cache('fiches/orpheline.html', $this->orphelineFiche());
    }

    private function writePdfText(): void
    {
        $this->cache('fermetures.txt', implode("\n", [
            '          FERMETURES DE LIGNES OU DE SECTIONS DE LIGNES',
            '  Rosporden – Concarneau                 Melgven – Concarneau             671,700      679,974     8,274     IG    476 000   06/06/2013   Section maintenue dans le domaine public ferroviaire',
            '  Voie du port de Saint-Malo             Section à Saint-Malo             454,505      454,914     0,409     IG    442 500   10/07/2024   Régularisation',
            '  Voie du port de Saint-Malo             Section à Saint-Malo             453,940      454,505     0,565     IG    422 500   10/07/2024   Section maintenue dans le domaine public ferroviaire',
            '  Morlaix – Roscoff                      Section à Roscoff                10,000       12,000      2,000     IG    999 999   01/02/2005   Décision annulée',
        ]));
        $this->cache('declassements.txt', implode("\n", [
            '                  RETRANCHEMENTS ET DECLASSEMENTS',
            'Rosporden – Concarneau      Section à Concarneau          678,900    679,974    1,074    IG    DECL   15/04/2016',
            'Laval – Pouancé             Section à Cossé-le-Vivien     20,100     21,000     0,900    IG    RETR   03/03/1995',
        ]));
    }

    /** @return list<array<int, mixed>> */
    private function events(int $lineId): array
    {
        return DB::table('line_events')->where('line_id', $lineId)->where('source', 'lignes-oubliees')
            ->orderBy('event_date')->orderBy('type')->orderBy('section_pk_start_m')
            ->get()->map(fn ($e) => [$e->type, $e->event_date, $e->date_precision, $e->confidence,
                $e->section_from, $e->section_to, $e->section_pk_start_m, $e->section_pk_end_m])->all();
    }

    private function lineBySlug(string $slug): ?object
    {
        return DB::table('lines')->where('slug', $slug)->first();
    }

    public function test_fiches_become_dated_section_events_with_pk_stations_and_lines(): void
    {
        $this->writeCache();

        $this->artisan('vff:import-lignes-oubliees', ['--from-cache' => true, '--no-pdf' => true, '--verbose' => true])
            ->expectsOutputToContain('4 ligne(s) à l\'inventaire, 3 avec fiche détaillée')
            ->expectsOutputToContain('3 fiche(s) analysée(s) : 13 événement(s), 3 gare(s)')
            ->expectsOutputToContain('Lignes : 1 rattachée(s) par code à une ligne existante, 2 créée(s) par cet import (slug lo-*, dont 0 déjà présente(s)')
            ->expectsOutputToContain('Rubriques non reconnues (importées en « other ») : {"Rubrique inventée":1}')
            ->expectsOutputToContain('Entrées ignorées : 2')
            ->expectsOutputToContain('· concarneau - Électrification : Rosporden - Concarneau : non électrifiée')
            ->expectsOutputToContain('· orpheline - sans code de ligne, fiche ignorée')
            ->assertSuccessful();

        // Ligne existante rattachée par code, jamais modifiée.
        $this->assertSame('Ligne de Rosporden à Concarneau', $this->concarneau->fresh()->name);
        $this->assertSame([
            ['opening', '1883-06-30', 'day', 'high', 'Rosporden', 'Concarneau', 664604, 679724],
            ['closure_passenger', '1959-10-04', 'day', 'high', 'Rosporden', 'Concarneau', 664604, 679724],
            ['other', '1990-01-01', 'year', 'medium', 'Rosporden', 'Concarneau', 664604, 679724],
            ['closure_freight', '1998-09-14', 'day', 'high', 'La Boissière', 'Concarneau', 671700, 679724],
            ['dismantling', '2015-01-01', 'year', 'low', 'La Boissière', 'Concarneau', 671700, 679724],
            ['dismantling', '2016-01-01', 'year', 'medium', 'Concarneau', 'Concarneau', null, null],
            ['declassification', '2016-04-15', 'day', 'high', 'Concarneau', 'Concarneau', null, null],
            ['declassification', '2017-09-12', 'day', 'high', 'Concarneau', 'Concarneau', null, null],
            ['closure_freight', '2021-10-24', 'day', 'high', 'Rosporden', 'La Boissière', 664604, 671700],
        ], $this->events($this->concarneau->id),
            'première date seulement (« officielle : … » ignorée), énumération « A et B » = deux événements, saison = année peu sûre');

        $events = DB::table('line_events')->where('source', 'lignes-oubliees')->get();
        $this->assertSame(['pending'], $events->pluck('section_resolution')->unique()->values()->all(), 'sections à projeter par le résolveur');
        $this->assertSame($events->count(), $events->pluck('fingerprint')->filter()->unique()->count(), 'empreinte unique par événement');
        $opening = $events->firstWhere('event_date', '1883-06-30');
        $this->assertSame([self::BASE.'index.php?page=concarneau', 'Ouverture : Rosporden - Concarneau : 30/06/1883',
            'Ouverture (Rosporden - Concarneau) : 30/06/1883 - PK déduits de la liste des gares de la fiche'],
            [$opening->source_url, $opening->source_quote, $opening->description]);
        $this->assertStringNotContainsString('—', $events->pluck('description')->implode('|'));

        // Ligne fermée hors RFN : créée sous lo-<code>, caractéristiques de la fiche.
        $volvic = $this->lineBySlug('lo-709000');
        $this->assertSame(['Ligne de Lapeyrouse à Volvic', '709000', 'principal', 37500, 1435, 1],
            [$volvic->name, $volvic->code, $volvic->network, $volvic->length_official_m, $volvic->gauge_mm_default, $volvic->nb_voies_default],
            'longueur de la fiche (37,5 km) plutôt que celle de l\'inventaire (37,4)');
        $this->assertEquals(['name_source' => 'lignes-oubliees', 'lignes_oubliees' => ['name' => 'Lapeyrouse – Volvic', 'url' => self::BASE.'index.php?page=volvic']],
            json_decode($volvic->alt_names, true));
        $this->assertSame(['Auvergne-Rhône-Alpes'], json_decode($volvic->regions, true));
        $this->assertSame([
            ['opening', '1909-07-01', 'day', 'high', 'Lapeyrouse', 'Volvic', 0, 37512],
            ['closure_passenger', '1939-03-01', 'month', 'medium', null, null, null, null],
            ['closure_freight', '1970-01-01', 'year', 'low', 'Lapeyrouse', 'Volvic', 0, 37512],
        ], $this->events($volvic->id), '« Lapeyrouse à Volvic » sans PK : plage citée ailleurs sur la fiche ; « Totalité de la ligne » : pas de borne');
        $this->assertStringEndsWith('- PK déduits des autres mentions de la fiche',
            DB::table('line_events')->where('line_id', $volvic->id)->where('type', 'opening')->value('description'));

        // Ligne de l'inventaire sans fiche : nom reconstitué, longueur de l'inventaire.
        $laval = $this->lineBySlug('lo-462000');
        $this->assertSame(['Ligne de Laval à Pouancé', 60400, ['Pays de la Loire', 'Bretagne']],
            [$laval->name, $laval->length_official_m, json_decode($laval->regions, true)]);
        $this->assertSame(self::BASE.'index.php?page=inventaire&debut=0&limite=376', json_decode($laval->alt_names, true)['lignes_oubliees']['url']);
        $this->assertSame(['lo-462000', 'lo-709000'], DB::table('lines')->where('slug', 'like', 'lo-%')->orderBy('slug')->pluck('slug')->all(),
            'fiche sans numéro : aucune ligne créée');

        // Gares de la fiche avec leur PK (le paragraphe hors section « Les gares » est ignoré).
        $stations = DB::table('source_stations')->where('source', 'lignes-oubliees')->orderBy('pk_m')->get();
        $this->assertSame([
            ['concarneau:rosporden', 'Rosporden', 664604, $this->concarneau->id],
            ['concarneau:la-boissiere', 'La Boissière', 673424, $this->concarneau->id],
            ['concarneau:concarneau', 'Concarneau', 679724, $this->concarneau->id],
        ], $stations->map(fn ($s) => [$s->source_ref, $s->name, $s->pk_m, $s->line_id])->all());
        $this->assertEquals(['line_code' => '476000', 'page' => 'concarneau', 'order' => 2, 'branch' => 1, 'pk_raw' => '673,424',
            'pk_approx' => false, 'source_url' => self::BASE.'index.php?page=concarneau'], json_decode($stations[1]->properties, true));
    }

    public function test_reimport_is_stable_keeps_resolved_sections_and_drops_what_the_fiche_no_longer_says(): void
    {
        $this->writeCache();
        DB::table('line_events')->insert(['line_id' => $this->concarneau->id, 'type' => 'opening', 'event_date' => '1883-01-01',
            'source' => 'wikipedia', 'description' => 'Ouverture (Wikipédia)', 'created_at' => now(), 'updated_at' => now()]);
        $this->artisan('vff:import-lignes-oubliees', ['--from-cache' => true, '--no-pdf' => true])->assertSuccessful();
        DB::table('source_stations')->update(['created_at' => '2026-01-01 00:00:00']);
        $before = $this->snapshot(['lines', 'line_events', 'source_stations'], ['id']);

        // Même processus, même instance de commande : compteurs et listes repartent de zéro.
        $this->artisan('vff:import-lignes-oubliees', ['--from-cache' => true, '--no-pdf' => true])
            ->expectsOutputToContain('Lignes : 1 rattachée(s) par code à une ligne existante, 2 créée(s) par cet import (slug lo-*, dont 2 déjà présente(s)')
            ->expectsOutputToContain('Entrées ignorées : 2 ')
            ->expectsOutputToContain('Gares (source_stations) : 3')
            ->expectsOutputToContain('nettoyage : 0 événement(s) et 0 gare(s) obsolètes supprimés')
            ->assertSuccessful();
        $this->assertSame($before, $this->snapshot(['lines', 'line_events', 'source_stations'], ['id']), 'second passage : rien ne change');
        $this->assertSame(['2026-01-01 00:00:00'], DB::table('source_stations')->distinct()->pluck('created_at')->all(),
            'date de création des gares conservée');

        // Le résolveur a traité deux sections.
        $q = fn (string $date, string $type) => DB::table('line_events')->where('line_id', $this->concarneau->id)
            ->where('source', 'lignes-oubliees')->where('event_date', $date)->where('type', $type);
        $q('1883-06-30', 'opening')->update(['section_resolution' => 'pk']);
        $q('2016-01-01', 'dismantling')->update(['section_resolution' => 'places']);
        $openingId = $q('1883-06-30', 'opening')->value('id');

        // La fiche change : PK de Rosporden corrigé, rubrique et gare retirées.
        $this->cache('fiches/concarneau.html', $this->concarneauFiche([
            'Rosporden (664,604)' => 'Rosporden (664,900)',
            '<dt>Rubrique inventée :</dt><dd><span>Rosporden - Concarneau :</span>1990</dd>' => '',
            ' &gt; La Boissière (673,424)' => '',
        ]));
        $this->artisan('vff:import-lignes-oubliees', ['--from-cache' => true, '--no-pdf' => true])
            ->expectsOutputToContain('nettoyage : 1 événement(s) et 1 gare(s) obsolètes supprimés')
            ->assertSuccessful();

        $opening = DB::table('line_events')->find($openingId);
        $this->assertSame([664900, 'pending'], [$opening->section_pk_start_m, $opening->section_resolution],
            'même empreinte, portion modifiée : section à recalculer');
        $this->assertSame('places', $q('2016-01-01', 'dismantling')->value('section_resolution'), 'portion inchangée : résolution conservée');
        $this->assertFalse($q('1990-01-01', 'other')->exists());
        $this->assertTrue(DB::table('line_events')->where('source', 'wikipedia')->exists(), 'les autres sources ne sont jamais touchées');
        $this->assertSame(['concarneau:concarneau', 'concarneau:rosporden'],
            DB::table('source_stations')->where('source', 'lignes-oubliees')->orderBy('source_ref')->pluck('source_ref')->all());
    }

    public function test_cleanup_only_concerns_documents_actually_reread(): void
    {
        $this->writeCache();
        $this->artisan('vff:import-lignes-oubliees', ['--from-cache' => true, '--no-pdf' => true])->assertSuccessful();
        $volvic = $this->lineBySlug('lo-709000');
        $count = fn (int $lineId) => DB::table('line_events')->where('line_id', $lineId)->where('source', 'lignes-oubliees')->count();

        // Fiche perdue du cache : ses événements restent.
        File::delete(storage_path('app/sources/lignes-oubliees/fiches/volvic.html'));
        $this->artisan('vff:import-lignes-oubliees', ['--from-cache' => true, '--no-pdf' => true])
            ->expectsOutputToContain('absent du cache : fiches/volvic.html')
            ->expectsOutputToContain('1 fiche(s) absente(s) du cache : relancer sans --from-cache pour les récupérer.')
            ->assertSuccessful();
        $this->assertSame(3, $count($volvic->id));

        // --page : une seule fiche relue, rien d'autre n'est touché.
        $this->cache('fiches/volvic.html', $this->volvicFiche());
        $this->cache('fiches/concarneau.html', $this->concarneauFiche(['<dt>Rubrique inventée :</dt><dd><span>Rosporden - Concarneau :</span>1990</dd>' => '']));
        $this->artisan('vff:import-lignes-oubliees', ['--from-cache' => true, '--page' => ['volvic']])
            ->expectsOutputToContain('1 fiche(s) analysée(s) : 3 événement(s), 0 gare(s)')
            ->expectsOutputToContain('Lignes : 0 rattachée(s) par code à une ligne existante, 1 créée(s)')
            ->assertSuccessful();
        $this->assertSame(9, $count($this->concarneau->id), 'fiche concarneau non relue : son événement « 1990 » reste');
        $this->assertSame(3, $count($volvic->id));
    }

    public function test_pdf_tables_add_decisions_fix_a_miscopied_line_number_and_skip_fiche_duplicates(): void
    {
        $this->writeCache();
        $this->writePdfText();
        // Référentiel SNCF : 442500 = voie du port de Saint-Malo ; 422500 n'existe nulle part.
        DB::table('sncf_line_types')->insert(['code_ligne' => '442500', 'rg_troncon' => 1, 'pkd_m' => 453324, 'pkf_m' => 454914, 'type_ligne' => 'Vport']);
        // Ligne créée par un import antérieur sous le code erroné, avec un événement d'une autre source.
        $wrongId = DB::table('lines')->insertGetId(['name' => 'Voie du port de Saint-Malo', 'slug' => 'lo-422500', 'code' => '422500',
            'network' => 'principal', 'created_at' => now(), 'updated_at' => now()]);
        $movedId = DB::table('line_events')->insertGetId(['line_id' => $wrongId, 'type' => 'opening', 'event_date' => '1890-01-01',
            'source' => 'wikipedia', 'description' => 'Ouverture', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('vff:import-lignes-oubliees', ['--from-cache' => true])
            ->expectsOutputToContain('absent du cache : fermetures.pdf')
            ->expectsOutputToContain('6 ligne(s) de tableau extraites des PDF')
            ->expectsOutputToContain('code corrigé : 422500 → 442500 (fermetures.pdf, Voie du port de Saint-Malo, Section à Saint-Malo)')
            ->expectsOutputToContain('(lo-422500) → ')
            ->expectsOutputToContain('PDF fermetures importées : 3')
            ->expectsOutputToContain('PDF fermetures ignorées (annulées / non construites) : 1')
            ->expectsOutputToContain('PDF declassements doublons de fiche : 1')
            ->expectsOutputToContain('PDF declassements importées : 1')
            ->assertSuccessful();

        // Coquille corrigée : une seule voie du port, preuve gardée, ligne erronée fusionnée.
        $port = $this->lineBySlug('lo-442500');
        $this->assertNull($this->lineBySlug('lo-422500'));
        $alt = json_decode($port->alt_names, true);
        $this->assertSame(['422500', self::BASE.'fichiers/fermetures.pdf', 'Section à Saint-Malo'],
            [$alt['code_corrections'][0]['cited'], $alt['code_corrections'][0]['source'], $alt['code_corrections'][0]['section']]);
        $this->assertStringContainsString('coquille d\'un chiffre', $alt['code_corrections'][0]['proof']);
        $this->assertSame('lo-422500', $alt['merged'][0]['slug']);
        $this->assertSame($port->id, DB::table('line_events')->where('id', $movedId)->value('line_id'), 'événement de la ligne fusionnée déplacé');
        $this->assertSame([
            ['closure', '2024-07-10', 'day', 'high', 'Saint-Malo', 'Saint-Malo', 453940, 454505],
            ['closure', '2024-07-10', 'day', 'high', 'Saint-Malo', 'Saint-Malo', 454505, 454914],
        ], $this->events($port->id));

        // Décision de fermeture sur une ligne à fiche : ajoutée (type et date différents de la fiche).
        $closure = DB::table('line_events')->where('line_id', $this->concarneau->id)->where('type', 'closure')->first();
        $this->assertSame(['2013-06-06', 671700, 679974, 'Melgven', 'Concarneau', 'high', self::BASE.'fichiers/fermetures.pdf',
            'Fermeture de ligne (décision RFF / SNCF Réseau) (Melgven – Concarneau) - Section maintenue dans le domaine public ferroviaire'],
            [$closure->event_date, $closure->section_pk_start_m, $closure->section_pk_end_m, $closure->section_from, $closure->section_to,
                $closure->confidence, $closure->source_url, $closure->description]);
        $this->assertSame(2, DB::table('line_events')->where('line_id', $this->concarneau->id)->where('type', 'declassification')->count(),
            'déclassement du 15/04/2016 déjà donné par la fiche : pas de doublon');

        // Ligne sans numéro dans le tableau : rattachée par son nom ; confiance moyenne.
        $laval = $this->lineBySlug('lo-462000');
        $retr = DB::table('line_events')->where('line_id', $laval->id)->first();
        $this->assertSame(['declassification', '1995-03-03', 'medium', 'Retranchement (Section à Cossé-le-Vivien)', 'Cossé-le-Vivien'],
            [$retr->type, $retr->event_date, $retr->confidence, $retr->description, $retr->section_from]);
        $this->assertNull($this->lineBySlug('lo-999999'), 'décision annulée : ni événement ni ligne');

        // Le référentiel ne confirme plus la correction : preuve retirée, code cité repris tel quel.
        DB::table('sncf_line_types')->delete();
        $this->artisan('vff:import-lignes-oubliees', ['--from-cache' => true])->assertSuccessful();
        $this->assertArrayNotHasKey('code_corrections', json_decode($this->lineBySlug('lo-442500')->alt_names, true));
        $this->assertNotNull($this->lineBySlug('lo-422500'));
    }

    public function test_dry_run_prints_the_analysis_and_writes_nothing(): void
    {
        $this->writeCache();
        $before = $this->snapshot(['lines', 'line_events', 'source_stations']);

        $this->artisan('vff:import-lignes-oubliees', ['--from-cache' => true, '--no-pdf' => true, '--dry-run' => true])
            ->expectsOutputToContain('concarneau [476000] Ligne de Rosporden à Concarneau')
            ->expectsOutputToContain('opening            1883-06-30 day   high   Rosporden → Concarneau  [664604 → 679724] (stations)')
            ->expectsOutputToContain('closure_passenger  1939-03-01 month medium ∅ → ∅  [∅ → ∅] (-)')
            ->expectsOutputToContain('gare 2/1 La Boissière 673424')
            ->expectsOutputToContain('· ignoré : concarneau - Électrification')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot(['lines', 'line_events', 'source_stations']));
    }

    public function test_missing_inventory_fails(): void
    {
        Http::fake();
        $this->artisan('vff:import-lignes-oubliees', ['--from-cache' => true])
            ->expectsOutputToContain('absent du cache : inventaire.html')
            ->expectsOutputToContain('Inventaire indisponible.')
            ->assertFailed();
        Http::assertNothingSent();
    }

    public function test_crawl_is_polite_cached_atomically_and_resumable(): void
    {
        $status = ['concarneau' => [503, 200], 'volvic' => [404], 'orpheline' => [200]];
        Http::fake(function (Request $r) use (&$status) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
            $page = $q['page'] ?? '';
            if ($page === 'inventaire') {
                return Http::response($this->inventory());
            }
            $code = count($status[$page]) > 1 ? array_shift($status[$page]) : $status[$page][0];

            return Http::response($code === 200 ? $this->{$page.'Fiche'}() : '', $code);
        });

        $this->artisan('vff:import-lignes-oubliees', ['--fetch-only' => true, '--no-pdf' => true])
            ->expectsOutputToContain('HTTP 503 (1) '.self::BASE.'index.php?page=concarneau')
            ->expectsOutputToContain('HTTP 404 (1) '.self::BASE.'index.php?page=volvic')
            ->expectsOutputToContain('Cache : 2 fiche(s) disponibles, 1 manquante(s) (relancer pour reprendre).')
            ->assertFailed();

        Http::assertSentCount(5);
        Http::assertSent(fn (Request $r) => $r->header('User-Agent') === ['VFF-Bot/1.0 (+https://vff-project.org)']);
        // ≥ 5 s entre deux requêtes (10 s avant la 2e tentative), jamais avant la première.
        Sleep::assertSleptTimes(4);
        Sleep::assertSlept(fn (CarbonInterval $d) => $d->totalSeconds > 4.5 && $d->totalSeconds <= 5, 3);
        Sleep::assertSlept(fn (CarbonInterval $d) => $d->totalSeconds > 9.5 && $d->totalSeconds <= 10, 1);
        $dir = storage_path('app/sources/lignes-oubliees');
        $this->assertSame(['concarneau.html', 'orpheline.html'], array_map('basename', File::files($dir.'/fiches')), 'erreur jamais mise en cache');
        $this->assertSame([], [...File::glob($dir.'/*.part'), ...File::glob($dir.'/fiches/*.part')], 'écriture atomique');
        $this->assertSame(0, DB::table('line_events')->count(), '--fetch-only n\'importe rien');

        // Reprise : seule la fiche manquante est demandée.
        $status['volvic'] = [200];
        $this->artisan('vff:import-lignes-oubliees', ['--fetch-only' => true, '--no-pdf' => true])
            ->expectsOutputToContain('Cache : 3 fiche(s) disponibles, 0 manquante(s)')
            ->assertSuccessful();
        Http::assertSentCount(6);
    }
}
