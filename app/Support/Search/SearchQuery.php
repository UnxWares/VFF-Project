<?php

namespace App\Support\Search;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Requête de recherche lue une fois (SearchText::key) et traduite en conditions SQL communes à toute la recherche
 * par nom.
 *
 * Correspondance : chaque mot de la requête doit commencer un mot du texte cherché (ou s'y trouver, pour un mot de
 * trois lettres ou plus, classé en dernier), dans n'importe quel ordre ; les
 * petits mots (de, la, l', à…) sont facultatifs, sauf le dernier, qu'on est peut-être en train de taper ; une
 * abréviation en dernier mot vaut aussi pour elle-même (« st » trouve « Saint-… » et « Strasbourg »).
 *
 * Pertinence (rankSql) : voir sa description.
 *
 * Fautes de frappe (fuzzySql) : ressemblance par trigrammes (pg_trgm, `word_similarity`) au-dessus de
 * FUZZY_THRESHOLD, seuil mesuré sur le jeu d'essai (storage/app/reports/agents/aprem-2111/recherche.md).
 */
final class SearchQuery
{
    /** Ressemblance minimale d'un résultat approché (0 à 1) ; mesures : 0,5 laisse passer trop de voisins. */
    public const FUZZY_THRESHOLD = 0.6;

    /**
     * Longueur maximale (caractères) d'une requête lue : au-delà, elle est coupée. Chaque mot ajoute une condition
     * LIKE et la ressemblance porte sur tout le texte : sans plafond, une adresse de 6 000 caractères coûtait 7 s de
     * base pour /stations. Même plafond que la validation de l'API et du serveur MCP (`max:100`).
     */
    public const MAX_LENGTH = 100;

    /** Années qu'une requête peut nommer à côté d'un lieu (« Lagny 1933 ») : accidents. */
    private const YEARS = [1800, 2099];

    /**
     * @param  list<list<string>>  $tokens  mots exigés, chacun avec ses variantes (une suffit)
     * @param  list<string>  $cores  cœurs comparés au nom pour le rang : celui de la requête entière, et celui de
     *                               la requête sans ses mots génériques, noms étrangers traduits (SearchText)
     * @param  list<string>  $phrases  clés de la requête entière et sans ses mots génériques (rang « dans un autre nom »)
     */
    private function __construct(
        public readonly string $raw,
        public readonly string $key,
        public readonly string $core,
        public readonly array $tokens,
        private readonly array $cores,
        private readonly array $phrases,
    ) {}

    public static function parse(string $raw): self
    {
        $raw = mb_substr($raw, 0, self::MAX_LENGTH);
        $key = SearchText::key($raw);
        $words = array_values(array_filter(explode(' ', $key), fn (string $w) => $w !== ''));
        $rawWords = array_values(array_filter(explode(' ', SearchText::key($raw, false)), fn (string $w) => $w !== ''));
        $last = count($words) - 1;
        $tokens = [];
        $plain = [];
        foreach ($words as $i => $word) {
            if (in_array($word, SearchText::GENERIC_WORDS, true)) {
                continue;
            }
            $plain[] = SearchText::EXONYMS[$word] ?? $word;
            if ($i < $last && in_array($word, SearchText::STOPWORDS, true)) {
                continue;
            }
            $tokens[] = isset(SearchText::EXONYMS[$word]) ? [$word, SearchText::EXONYMS[$word]] : [$word];
        }
        // Rien que des petits mots ou des mots génériques (« de la », « gare ») : ils sont alors exigés.
        if ($tokens === [] && $words !== []) {
            $tokens = array_map(fn (string $w) => [$w], array_values(array_diff($words, SearchText::STOPWORDS)) ?: $words);
            $plain = $words;
        }
        // Dernier mot abrégé (« st », « mt », « g ») : aussi pris tel quel, comme un début de mot.
        $rawLast = end($rawWords);
        if ($tokens !== [] && is_string($rawLast) && isset(SearchText::ABBREVIATIONS[$rawLast]) && end($tokens)[0] !== $rawLast) {
            $tokens[count($tokens) - 1][] = $rawLast;
        }
        $plainKey = $plain === [] ? '' : ' '.implode(' ', $plain);
        $core = SearchText::core($key);

        return new self(
            $raw,
            $key,
            $core,
            $tokens,
            array_values(array_unique(array_filter([$core, SearchText::core($plainKey)], fn (string $c) => $c !== ''))),
            array_values(array_unique(array_filter([$key, $plainKey], fn (string $k) => substr_count($k, ' ') >= 2))),
        );
    }

    /**
     * Année citée à côté d'autres mots (« Lagny 1933 », « accident gare de Lyon 1988 ») : l'année, et la requête
     * sans elle, pour chercher les accidents de cette année par les autres mots (vide pour « accident 1933 » :
     * tous ceux de l'année). [null, la requête] sinon. Une requête qui n'est qu'une date passe d'abord par
     * CatalogSearch::dateCondition.
     *
     * @return array{0: ?int, 1: self}
     */
    public function splitYear(): array
    {
        foreach ($this->tokens as $i => $alternatives) {
            $word = $alternatives[0];
            if (preg_match('/^\d{4}$/', $word) === 1 && (int) $word >= self::YEARS[0] && (int) $word <= self::YEARS[1]) {
                $rest = array_map(fn (array $t) => $t[0], array_values(array_diff_key($this->tokens, [$i => true])));

                return [(int) $word, self::parse(implode(' ', $rest))];
            }
        }

        return [null, $this];
    }

    /**
     * Motif LIKE littéral : « % », « _ » et « \ » sont des données, jamais des jokers. La clé de recherche ne garde
     * que des lettres, chiffres et espaces, donc cet échappement ne change rien aujourd'hui ; il garde la requête
     * sûre (et son parcours d'index borné) si la normalisation évolue.
     */
    private static function escapeLike(string $text): string
    {
        return addcslashes($text, '%_\\');
    }

    public function isEmpty(): bool
    {
        return $this->tokens === [];
    }

    /**
     * Requête faite seulement de chiffres et d'espaces (« 570 000 », « 87481234 »), mots génériques à part
     * (« ligne 830 000 », « UIC 87686006 ») : ses chiffres, sinon null.
     */
    public function digits(): ?string
    {
        $generic = implode('|', SearchText::GENERIC_WORDS);
        $rest = trim((string) preg_replace('/(?<![\p{L}\d])('.$generic.')(?![\p{L}\d])/iu', ' ', $this->raw));

        return $rest !== '' && preg_match('/^[\d\s]+$/', $rest) === 1 ? (string) preg_replace('/\s+/', '', $rest) : null;
    }

    /**
     * Condition « tous les mots trouvés » sur une clé SQL (SearchFields::keys) : au début d'un mot, ou, si
     * `$anywhere`, n'importe où dans un mot pour les mots de 3 lettres ou plus (« vire » trouve « Évires »).
     *
     * @return array{0: string, 1: list<string>}
     */
    public function matchSql(string $keyExpr, bool $anywhere = false): array
    {
        $parts = [];
        $bindings = [];
        foreach ($this->tokens as $alternatives) {
            $parts[] = '('.implode(' OR ', array_fill(0, count($alternatives), "{$keyExpr} LIKE ?")).')';
            foreach ($alternatives as $word) {
                $bindings[] = ($anywhere && strlen($word) >= 3 ? '%' : '% ').self::escapeLike($word).'%';
            }
        }

        return [$parts === [] ? 'FALSE' : implode(' AND ', $parts), $bindings];
    }

    /**
     * Rang de pertinence (0 à 7, 0 = meilleur) : nom égal à la requête (petits mots ignorés), nom qui commence par
     * elle, requête dans le nom à un début de mot, requête de deux mots ou plus dans un autre nom (« Eisenbahnunfall
     * von Melun » : nom allemand), requête sans ses mots génériques dans le nom, tous les mots au début d'un mot du
     * nom, tous les mots au début d'un mot d'un autre nom (commune, ancien nom), puis le reste (milieu d'un mot).
     * Le nom exact et le début du nom comptent aussi pour la requête sans ses mots génériques (« Paris Nord
     * station » est le nom exact de Paris-Nord).
     *
     * @return array{0: string, 1: list<string>}
     */
    public function rankSql(string $nameKeyExpr, string $allKeyExpr): array
    {
        $core = SearchText::sqlCore($nameKeyExpr);
        [$inName, $inNameBindings] = $this->matchSql($nameKeyExpr);
        [$inAll, $inAllBindings] = $this->matchSql($allKeyExpr);
        $cores = $this->rankCores();
        $any = fn (string $sql, array $values) => $values === [] ? 'FALSE' : '('.implode(' OR ', array_fill(0, count($values), $sql)).')';
        $plain = array_slice($cores, 1);

        return [
            "CASE WHEN {$core} IN (".implode(', ', array_fill(0, count($cores), '?')).') THEN 0 WHEN '.$any("{$core} LIKE ?", $cores).' THEN 1 '
                ."WHEN {$core} LIKE ? THEN 2 WHEN ".$any("{$allKeyExpr} LIKE ?", $this->phrases).' THEN 3 WHEN '.$any("{$core} LIKE ?", $plain)
                ." THEN 4 WHEN {$inName} THEN 5 WHEN {$inAll} THEN 6 ELSE 7 END",
            [
                ...$cores,
                ...array_map(fn (string $c) => self::escapeLike($c).'%', $cores),
                '%'.self::escapeLike($cores[0]).'%',
                ...array_map(fn (string $p) => '%'.self::escapeLike($p).'%', $this->phrases),
                ...array_map(fn (string $c) => '%'.self::escapeLike($c).'%', $plain),
                ...$inNameBindings,
                ...$inAllBindings,
            ],
        ];
    }

    /**
     * Cœurs comparés au nom : celui de la requête entière, puis, s'il diffère, celui de la requête sans ses mots
     * génériques (noms étrangers traduits).
     *
     * @return non-empty-list<string>
     */
    private function rankCores(): array
    {
        return $this->cores === [] ? [$this->core] : $this->cores;
    }

    /**
     * Condition « fiche de la commune cherchée » : la commune (clé SQL `$cityKeyExpr`) est la requête et le nom
     * commence par elle (« strasbourg » : Strasbourg-Ville ; « mulhouse » : Mulhouse-Ville). Le rang de la gare
     * ouverte aux voyageurs qui la remplit vaut celui du nom exact : une ancienne gare « Strasbourg » ne passe plus
     * devant la gare de la ville.
     *
     * @return array{0: string, 1: list<string>}
     */
    public function citySql(string $nameKeyExpr, string $cityKeyExpr): array
    {
        $cores = $this->rankCores();
        $marks = implode(', ', array_fill(0, count($cores), '?'));
        $prefix = '('.implode(' OR ', array_fill(0, count($cores), SearchText::sqlCore($nameKeyExpr).' LIKE ?')).')';

        return [
            '('.SearchText::sqlCore($cityKeyExpr)." IN ({$marks}) AND {$prefix})",
            [...$cores, ...array_map(fn (string $c) => self::escapeLike($c).'%', $cores)],
        ];
    }

    /**
     * Même rang que rankSql, calculé en PHP sur des clés (SearchText::key) : pour les textes qui ne sont pas en
     * base (glossaire). null : la fiche ne correspond pas (matchSql avec `$anywhere`).
     *
     * @param  list<string>  $nameKeys  clés des noms de la fiche (un par langue)
     */
    public function rankKeys(array $nameKeys, string $allKey): ?int
    {
        if (! $this->matchesKey($allKey, true)) {
            return null;
        }
        $cores = $this->rankCores();
        $best = 7;
        foreach ($nameKeys as $nameKey) {
            $nameCore = SearchText::core($nameKey);
            foreach ($cores as $i => $c) {
                $best = min($best, match (true) {
                    $nameCore === $c => 0,
                    str_starts_with($nameCore, $c) => 1,
                    str_contains($nameCore, $c) => $i === 0 ? 2 : 4,
                    default => 7,
                });
            }
            if ($this->matchesKey($nameKey, false)) {
                $best = min($best, 5);
            }
        }
        foreach ($this->phrases as $p) {
            if (str_contains($allKey, $p)) {
                $best = min($best, 3);
            }
        }

        return $this->matchesKey($allKey, false) ? min($best, 6) : $best;
    }

    /** Chaque mot de la requête commence un mot de la clé (ou s'y trouve, si `$anywhere`, à partir de 3 lettres). */
    private function matchesKey(string $key, bool $anywhere): bool
    {
        foreach ($this->tokens as $alternatives) {
            $found = false;
            foreach ($alternatives as $word) {
                $found = $found || str_contains($key, ' '.$word) || ($anywhere && strlen($word) >= 3 && str_contains($key, $word));
            }
            if (! $found) {
                return false;
            }
        }

        return $this->tokens !== [];
    }

    /** Texte comparé par trigrammes (requête sans petits mots ni mots génériques, noms étrangers traduits). */
    public function fuzzyText(): string
    {
        $core = $this->cores === [] ? '' : $this->cores[count($this->cores) - 1];

        return trim($core !== '' ? $core : $this->key);
    }

    /**
     * Condition « ressemble » (fautes de frappe) sur une clé SQL, servie par un index GIN gin_trgm_ops : fixe le
     * seuil de la session (pg_trgm.word_similarity_threshold) avant d'être utilisée.
     *
     * @return array{0: string, 1: list<string>}
     */
    public function fuzzySql(string $keyExpr): array
    {
        DB::selectOne("SELECT set_config('pg_trgm.word_similarity_threshold', ?, false)", [(string) self::FUZZY_THRESHOLD]);

        return ["? <% {$keyExpr}", [$this->fuzzyText()]];
    }

    /**
     * Ordre des résultats approchés : les plus ressemblants d'abord.
     *
     * @return array{0: string, 1: list<string>}
     */
    public function fuzzyOrderSql(string $keyExpr): array
    {
        return ["word_similarity(?, {$keyExpr}) DESC", [$this->fuzzyText()]];
    }

    /**
     * Filtre « tous les mots » (sur tous les noms) d'une page de liste ou de l'API, ou, si `$approximate`, filtre
     * des fautes de frappe (sur le nom seul : plus court, plus juste, 4 fois plus rapide que sur tous les noms).
     * `$extra` : autre correspondance (code, numéro), [sql, liaisons].
     *
     * @template TBuilder of QueryBuilder|EloquentBuilder
     *
     * @param  TBuilder  $builder
     * @param  array{0: string, 1: string}  $keys  clés SQL du nom et de tous les noms (SearchFields::keys)
     * @param  array{0: string, 1: list<mixed>}|null  $extra
     * @return TBuilder
     */
    public function where(QueryBuilder|EloquentBuilder $builder, array $keys, ?array $extra = null, bool $approximate = false): QueryBuilder|EloquentBuilder
    {
        [$sql, $bindings] = $approximate ? $this->fuzzySql($keys[0]) : $this->matchSql($keys[1], anywhere: true);

        return $builder->where(function ($w) use ($sql, $bindings, $extra, $approximate): void {
            $w->whereRaw("({$sql})", $bindings);
            if ($extra !== null && ! $approximate) {
                $w->orWhereRaw("({$extra[0]})", $extra[1]);
            }
        });
    }

    /**
     * Rien ne correspond mot pour mot dans `$base` (et la recherche approchée a un sens) : les listes montrent alors
     * les noms approchés.
     *
     * @template TBuilder of QueryBuilder|EloquentBuilder
     *
     * @param  TBuilder  $base
     * @param  array{0: string, 1: string}  $keys  clés SQL du nom et de tous les noms (SearchFields::keys)
     * @param  array{0: string, 1: list<mixed>}|null  $extra
     */
    public function onlyApproximate(QueryBuilder|EloquentBuilder $base, array $keys, ?array $extra = null): bool
    {
        return $this->canBeFuzzy() && ! $this->where($base, $keys, $extra)->exists();
    }

    /** Une recherche approchée a-t-elle un sens (assez de lettres, pas un code) ? */
    public function canBeFuzzy(): bool
    {
        return preg_match('/[a-z]{3}/', $this->fuzzyText()) === 1;
    }

    /**
     * Tri par pertinence d'une page de liste : rang du nom, puis ressemblance (recherche approchée).
     *
     * @template TBuilder of QueryBuilder|EloquentBuilder
     *
     * @param  TBuilder  $builder
     * @return TBuilder
     */
    public function orderByRelevance(QueryBuilder|EloquentBuilder $builder, string $nameKey, string $allKey, bool $approximate = false): QueryBuilder|EloquentBuilder
    {
        if ($approximate) {
            [$sql, $bindings] = $this->fuzzyOrderSql($nameKey);

            return $builder->orderByRaw($sql, $bindings);
        }
        [$sql, $bindings] = $this->rankSql($nameKey, $allKey);

        return $builder->orderByRaw($sql, $bindings);
    }
}
