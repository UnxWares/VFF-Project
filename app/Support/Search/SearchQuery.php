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

    /**
     * @param  list<list<string>>  $tokens  mots exigés, chacun avec ses variantes (une suffit)
     */
    private function __construct(
        public readonly string $raw,
        public readonly string $key,
        public readonly string $core,
        public readonly array $tokens,
    ) {}

    public static function parse(string $raw): self
    {
        $raw = mb_substr($raw, 0, self::MAX_LENGTH);
        $key = SearchText::key($raw);
        $words = array_values(array_filter(explode(' ', $key), fn (string $w) => $w !== ''));
        $rawWords = array_values(array_filter(explode(' ', SearchText::key($raw, false)), fn (string $w) => $w !== ''));
        $last = count($words) - 1;
        $tokens = [];
        foreach ($words as $i => $word) {
            if ($i < $last && in_array($word, SearchText::STOPWORDS, true)) {
                continue;
            }
            $tokens[] = [$word];
        }
        if ($tokens === [] && $words !== []) {
            $tokens = array_map(fn (string $w) => [$w], $words);
        }
        // Dernier mot abrégé (« st », « mt », « g ») : aussi pris tel quel, comme un début de mot.
        $rawLast = end($rawWords);
        if ($tokens !== [] && is_string($rawLast) && isset(SearchText::ABBREVIATIONS[$rawLast]) && end($tokens)[0] !== $rawLast) {
            $tokens[count($tokens) - 1][] = $rawLast;
        }

        return new self($raw, $key, SearchText::core($key), $tokens);
    }

    public function isEmpty(): bool
    {
        return $this->tokens === [];
    }

    /** Requête faite seulement de chiffres et d'espaces (« 570 000 », « 87481234 ») : ses chiffres, sinon null. */
    public function digits(): ?string
    {
        return preg_match('/^[\d\s]+$/', trim($this->raw)) === 1 ? (string) preg_replace('/\s+/', '', $this->raw) : null;
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
                $bindings[] = ($anywhere && strlen($word) >= 3 ? '%' : '% ').$word.'%';
            }
        }

        return [$parts === [] ? 'FALSE' : implode(' AND ', $parts), $bindings];
    }

    /**
     * Rang de pertinence (0 à 5, 0 = meilleur) : nom égal à la requête (petits mots ignorés), nom qui commence par
     * elle, requête au début d'un mot du nom, tous les mots au début d'un mot du nom, tous les mots au début d'un
     * mot d'un autre nom (commune, ancien nom), puis le reste (milieu d'un mot).
     *
     * @return array{0: string, 1: list<string>}
     */
    public function rankSql(string $nameKeyExpr, string $allKeyExpr): array
    {
        $core = SearchText::sqlCore($nameKeyExpr);
        [$inName, $inNameBindings] = $this->matchSql($nameKeyExpr);
        [$inAll, $inAllBindings] = $this->matchSql($allKeyExpr);

        return [
            "CASE WHEN {$core} = ? THEN 0 WHEN {$core} LIKE ? THEN 1 WHEN {$core} LIKE ? THEN 2 WHEN {$inName} THEN 3 WHEN {$inAll} THEN 4 ELSE 5 END",
            [$this->core, $this->core.'%', '%'.$this->core.'%', ...$inNameBindings, ...$inAllBindings],
        ];
    }

    /** Texte comparé par trigrammes (requête sans petits mots). */
    public function fuzzyText(): string
    {
        return trim($this->core !== '' ? $this->core : $this->key);
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
