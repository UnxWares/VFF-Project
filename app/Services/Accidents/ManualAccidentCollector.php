<?php

namespace App\Services\Accidents;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentKind;

/**
 * Fiches d'accident rédigées à la main (database/data/accident_records_manual.php) : un accident qu'aucune
 * source de l'import ne décrit (Wikidata, BEA-TT, listes de Wikipédia) mais qu'un document lu appuie (presse
 * d'époque, ouvrage). Lues par l'import comme une source de plus : le candidat passe par la localisation, le
 * rattachement aux lignes et la fusion avec les autres sources comme n'importe quel autre. Si une source
 * automatique décrit un jour le même accident, la fiche se fond dedans et celle-ci ne crée plus rien.
 *
 * Un enregistrement :
 *   - id          : identifiant stable (devient accident_sources.external_id), AAAA-MM-JJ-commune ;
 *   - date        : AAAA-MM-JJ, AAAA-MM ou AAAA (précision déduite) ;
 *   - commune     : nom de la commune dans le référentiel de VFF, avec `department` (code) ;
 *   - station     : gare citée, facultatif ;
 *   - kind        : nature (valeur de App\Enums\AccidentKind) ;
 *   - deaths, injuries : bilan, facultatifs (null : inconnu) ;
 *   - line_articles : titres des articles de Wikipédia des lignes concernées, facultatif ;
 *   - source      : le document qui appuie la fiche (provider = clé de database/data/source_rights.php, title,
 *                   url, licence, licence_url facultatif, published, note). Un lien et des faits, jamais le texte.
 *
 * La fiche reste « à vérifier » (note `place_from_manual`) tant qu'une seconde source indépendante ne la confirme pas.
 */
final class ManualAccidentCollector
{
    /** Nom du fournisseur pour le bilan retenu (accidents.toll_source) et la provenance. */
    public const PROVIDER = 'manual';

    /** @param ?list<array<string, mixed>> $records enregistrements (par défaut database/data/accident_records_manual.php) */
    public function __construct(private readonly ?array $records = null) {}

    /** @return list<AccidentCandidate> */
    public function collect(): array
    {
        $records = $this->records ?? require dirname(__DIR__, 3).'/database/data/accident_records_manual.php';

        return array_map($this->candidate(...), $records);
    }

    /** @param array<string, mixed> $r */
    private function candidate(array $r): AccidentCandidate
    {
        $c = new AccidentCandidate('manual:'.$r['id']);
        $c->date = match (strlen((string) $r['date'])) {
            4 => $r['date'].'-01-01',
            7 => $r['date'].'-01',
            default => (string) $r['date'],
        };
        $c->precision = match (strlen((string) $r['date'])) {
            4 => AccidentDatePrecision::Year,
            7 => AccidentDatePrecision::Month,
            default => AccidentDatePrecision::Day,
        };
        $c->dateSource = 'vff';
        $c->kind = AccidentKind::from((string) $r['kind']);
        $c->kindSource = 'vff';
        $c->communeName = (string) $r['commune'];
        $c->departmentCode = (string) $r['department'];
        $c->stationHint = $r['station'] ?? null;
        $c->lineArticles = $r['line_articles'] ?? [];
        $c->declaredInFrance = true;
        $c->stopAtAmbiguousPlace = true;
        $c->note('place_from_manual');
        $source = $r['source'];
        if (isset($r['deaths'])) {
            $c->deaths[self::PROVIDER] = (int) $r['deaths'];
            $c->tollUrls[self::PROVIDER] = (string) $source['url'];
        }
        if (isset($r['injuries'])) {
            $c->injuries[self::PROVIDER] = (int) $r['injuries'];
        }
        $c->addSource(new AccidentSourceRef(
            provider: (string) $source['provider'],
            externalId: (string) $r['id'],
            role: 'press',
            title: (string) $source['title'],
            url: (string) $source['url'],
            licence: (string) $source['licence'],
            licenceUrl: $source['licence_url'] ?? null,
            facts: array_filter([
                'date' => $c->date, 'deaths' => $r['deaths'] ?? null, 'injuries' => $r['injuries'] ?? null,
                'place' => $c->communeName, 'department' => $c->departmentCode, 'published' => $source['published'] ?? null,
            ], fn ($v) => $v !== null),
        ));

        return $c;
    }
}
