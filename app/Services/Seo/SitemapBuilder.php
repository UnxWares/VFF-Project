<?php

namespace App\Services\Seo;

use App\Models\Accident;
use App\Models\Line;
use App\Models\Material;
use App\Models\Station;
use App\Services\Communes\CommuneRail;
use App\Services\Departments\DepartmentFigures;
use App\Support\Seo\StructuredData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use XMLWriter;

/**
 * Plans du site (`/sitemap.xml` et un plan par famille de pages publiques, paginés à 50 000 adresses au plus).
 *
 * Les pages n'ont qu'une adresse par fiche : la langue vient du cookie, du profil ou de Accept-Language, pas
 * de l'adresse, donc pas de `hreflang`. Seules les fiches publiées y figurent : accidents sans alias ni nature
 * masquée (portée PublicAccidents), jamais les pages de compte, de contribution ou de validation. `lastmod` est
 * la date de mise à jour de la fiche (jamais inventée : absente pour les pages éditoriales et les communes, dont la
 * fiche est calculée à la volée). Chaque plan est
 * gardé une heure dans le cache.
 */
final class SitemapBuilder
{
    public function __construct(private readonly int $pageSize = self::PAGE_SIZE) {}

    /** Adresses par fichier (limite du protocole). */
    public const PAGE_SIZE = 50000;

    /** Durée de garde d'un plan (secondes). */
    public const TTL = 3600;

    /** Familles de fiches, dans l'ordre de l'index. */
    public const FAMILIES = ['pages', 'lines', 'stations', 'communes', 'departments', 'materials', 'accidents'];

    /** Pages éditoriales et annuaires : noms de routes. */
    private const PAGES = [
        'home', 'whoarewe', 'maps', 'lines.index', 'stations.index', 'materials.index', 'accidents.index', 'accidents.statistics', 'network.figures', 'departments.index', 'network.chronology', 'network.on-this-day', 'nearby', 'tourist-railways', 'projects', 'glossary', 'quiz',
        'contribute', 'contribute.reputation', 'donate', 'legal', 'privacy', 'use-conditions', 'accessibility',
        'api.docs', 'docs.swagger', 'credits.tech', 'credits.media',
    ];

    /** Index : le plan du journal, puis chaque plan de famille et de page. */
    public function index(string $origin): string
    {
        return Cache::remember('sitemap:index:'.md5($origin), self::TTL, function () use ($origin): string {
            $x = self::writer();
            $x->startElement('sitemapindex');
            $x->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
            $this->sitemapEntry($x, StructuredData::absoluteUrl($origin, '/journal/sitemap.xml'));
            foreach (self::FAMILIES as $family) {
                $pages = max(1, (int) ceil($this->count($family) / $this->pageSize));
                for ($page = 1; $page <= $pages; $page++) {
                    $this->sitemapEntry($x, StructuredData::absoluteUrl($origin, self::path($family, $page)));
                }
            }
            $x->endElement();

            return $x->outputMemory();
        });
    }

    /** Plan d'une famille ; null si la famille ou la page n'existe pas. */
    public function family(string $origin, string $family, int $page): ?string
    {
        if (! in_array($family, self::FAMILIES, true) || $page < 1) {
            return null;
        }
        if ($page > max(1, (int) ceil($this->count($family) / $this->pageSize))) {
            return null;
        }

        return Cache::remember("sitemap:{$family}:{$page}:".md5($origin), self::TTL, function () use ($origin, $family, $page): string {
            $x = self::writer();
            $x->startElement('urlset');
            $x->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
            foreach ($this->rows($family, $page) as [$path, $lastmod]) {
                $x->startElement('url');
                $x->writeElement('loc', StructuredData::absoluteUrl($origin, $path));
                if ($lastmod !== null) {
                    $x->writeElement('lastmod', $lastmod);
                }
                $x->endElement();
            }
            $x->endElement();

            return $x->outputMemory();
        });
    }

    public static function path(string $family, int $page): string
    {
        return "/sitemap-{$family}-{$page}.xml";
    }

    /** @return iterable<array{0: string, 1: ?string}> */
    private function rows(string $family, int $page): iterable
    {
        if ($family === 'pages') {
            // Découpées comme les autres familles : sans cela, chaque page du plan redonnait les pages éditoriales.
            foreach (array_slice(self::PAGES, ($page - 1) * $this->pageSize, $this->pageSize) as $name) {
                yield [route($name, [], false), null];
            }

            return;
        }

        if ($family === 'communes') {
            $rows = DB::table('communes')->where('type', 'commune-actuelle')
                ->whereRaw('code_insee = ANY(?::text[])', [self::codeArray()])
                ->orderBy('code_insee')->offset(($page - 1) * $this->pageSize)->limit($this->pageSize)->get(['code_insee', 'nom']);
            foreach ($rows as $row) {
                yield ['/communes/'.CommuneRail::slug((string) $row->code_insee, (string) $row->nom), null];
            }

            return;
        }

        if ($family === 'departments') {
            // Départements dont les chiffres sont calculés (cache) : jamais une fiche qui répondrait 404.
            foreach (array_slice($this->departmentSlugs(), ($page - 1) * $this->pageSize, $this->pageSize) as $slug) {
                yield ['/departments/'.$slug, null];
            }

            return;
        }

        $query = match ($family) {
            'lines' => $this->table(Line::class)->selectRaw('slug, coalesce(encyclopedia_updated_at, updated_at) as modified'),
            'stations' => $this->table(Station::class)->select('slug', 'updated_at as modified'),
            'materials' => $this->table(Material::class)->select('slug', 'updated_at as modified'),
            default => Accident::query()->toBase()->select('slug', 'updated_at as modified'),
        };
        $base = match ($family) {
            'lines' => '/lines/',
            'stations' => '/stations/',
            'materials' => '/materials/',
            default => '/accidents/',
        };
        $rows = $query->orderBy('id')->offset(($page - 1) * $this->pageSize)->limit($this->pageSize)->get();
        foreach ($rows as $row) {
            yield [$base.rawurlencode((string) $row->slug), $row->modified === null ? null : Carbon::parse($row->modified)->toDateString()];
        }
    }

    /** @param class-string<Model> $model */
    private function table(string $model): Builder
    {
        return DB::table((new $model)->getTable())->whereNotNull('slug');
    }

    private function count(string $family): int
    {
        return match ($family) {
            'pages' => count(self::PAGES),
            'lines' => $this->table(Line::class)->count(),
            'stations' => $this->table(Station::class)->count(),
            'materials' => $this->table(Material::class)->count(),
            'communes' => DB::table('communes')->where('type', 'commune-actuelle')->whereRaw('code_insee = ANY(?::text[])', [self::codeArray()])->count(),
            'departments' => count($this->departmentSlugs()),
            default => Accident::query()->count(),
        };
    }

    /** @return list<string> adresses des fiches de département calculées (DepartmentFigures) */
    private function departmentSlugs(): array
    {
        return array_column((new DepartmentFigures)->index()['departments'] ?? [], 'slug');
    }

    /** Communes qui ont du rail (CommuneRail::codes), en tableau PostgreSQL. */
    private static function codeArray(): string
    {
        return '{'.implode(',', (new CommuneRail)->codes()).'}';
    }

    private function sitemapEntry(XMLWriter $x, string $loc): void
    {
        $x->startElement('sitemap');
        $x->writeElement('loc', $loc);
        $x->endElement();
    }

    private static function writer(): XMLWriter
    {
        $x = new XMLWriter;
        $x->openMemory();
        $x->setIndent(true);
        $x->startDocument('1.0', 'UTF-8');

        return $x;
    }
}
