<?php

namespace App\Services\Accidents;

/**
 * Pages et rapports d'enquête du BEA-TT lus pour VFF (database/data/accident_bea_tt_reports.php), retrouvés par le lien de
 * la rangée du « Bilan général des enquêtes » (accident_sources.url).
 */
final class BeaTtReportTable
{
    /** @var array<string, array{slug: string, commune_insee?: string, date?: string, place: string, report: array{title: string, number?: string, url: string, published?: string}}> */
    private array $rows;

    /** @param ?array<string, array{slug: string, commune_insee?: string, date?: string, place: string, report: array{title: string, number?: string, url: string, published?: string}}> $rows lignes (par défaut database/data/accident_bea_tt_reports.php) */
    public function __construct(?array $rows = null)
    {
        $this->rows = $rows ?? require dirname(__DIR__, 3).'/database/data/accident_bea_tt_reports.php';
    }

    /** @return array{slug: string, commune_insee?: string, date?: string, place: string, report: array{title: string, number?: string, url: string, published?: string}}|null */
    public function find(string $url): ?array
    {
        return $this->rows[$url] ?? null;
    }

    /** @return array<string, array{slug: string, commune_insee?: string, date?: string, place: string, report: array{title: string, number?: string, url: string, published?: string}}> */
    public function rows(): array
    {
        return $this->rows;
    }
}
