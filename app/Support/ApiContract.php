<?php

namespace App\Support;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;

/**
 * Instantané du contrat public de l'API (REST, GraphQL, MCP) : une table plate « clé => type ».
 *
 * Clés : `rest op GET /lines/{line}`, `rest param GET /lines/{line} query:era`, `rest status GET /x 422`,
 * `rest field GET /x 200 data[].slug` (champ de la réponse), `rest enum … =valeur` ; `gql Type.champ`,
 * `gql Type.champ(argument)`, `gql Enum=VALEUR`, `gql Union=Membre` ; `mcp tool nom`, `mcp tool nom.paramètre`.
 * Valeur : le type, suivi de `!` (paramètre obligatoire) et de ` @deprecated` (retrait annoncé).
 *
 * Fichier versionné : `tests/Fixtures/api-contract.json`, mis à jour par `php artisan vff:api-contract`. Le test
 * `ApiContractTest` échoue quand une clé du fichier a disparu sans être marquée `@deprecated`, ou que son type a changé.
 */
final class ApiContract
{
    public const DEPRECATED = ' @deprecated';

    public const FILE = 'tests/Fixtures/api-contract.json';

    /**
     * @param  array<string, mixed>  $openapi  spécification Scramble (`/docs/api.json`)
     * @param  list<array<string, mixed>>  $tools  `result.tools` de `tools/list`
     * @param  list<array<string, mixed>>  $prompts  `result.prompts` de `prompts/list`
     * @return array<string, string>
     */
    public static function build(array $openapi, Schema $schema, array $tools, array $prompts = []): array
    {
        $contract = [...self::rest($openapi), ...self::graphql($schema), ...self::mcp($tools, $prompts)];
        ksort($contract, SORT_STRING);

        return $contract;
    }

    /**
     * Ce que le contrat enregistré promet encore et que l'API actuelle ne tient plus.
     *
     * @param  array<string, string>  $recorded
     * @param  array<string, string>  $current
     * @return array{removed: list<string>, changed: list<string>}
     */
    public static function breaking(array $recorded, array $current): array
    {
        $removed = $changed = [];
        foreach ($recorded as $key => $type) {
            if (! array_key_exists($key, $current)) {
                if (! str_ends_with($type, self::DEPRECATED)) {
                    $removed[] = $key;
                }

                continue;
            }
            if (self::bare($type) !== self::bare($current[$key])) {
                $changed[] = "{$key} : ".self::bare($type).' => '.self::bare($current[$key]);
            }
        }

        return ['removed' => $removed, 'changed' => $changed];
    }

    private static function bare(string $type): string
    {
        return str_ends_with($type, self::DEPRECATED) ? substr($type, 0, -strlen(self::DEPRECATED)) : $type;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, string>
     */
    private static function rest(array $spec): array
    {
        $out = [];
        foreach ((array) ($spec['paths'] ?? []) as $path => $item) {
            foreach ((array) $item as $method => $op) {
                if (! is_array($op) || ! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }
                $id = strtoupper($method).' '.$path;
                $out["rest op {$id}"] = 'operation'.(! empty($op['deprecated']) ? self::DEPRECATED : '');
                foreach ((array) ($op['parameters'] ?? []) as $p) {
                    $p = self::deref($spec, $p);
                    $type = self::typeOf($spec, (array) ($p['schema'] ?? []));
                    $out["rest param {$id} {$p['in']}:{$p['name']}"] = $type.(! empty($p['required']) ? '!' : '').(! empty($p['deprecated']) ? self::DEPRECATED : '');
                    foreach (self::enumOf($spec, (array) ($p['schema'] ?? [])) as $value) {
                        $out["rest enum {$id} {$p['in']}:{$p['name']}={$value}"] = 'value';
                    }
                }
                foreach ((array) ($op['requestBody']['content'] ?? []) as $media => $content) {
                    self::walk($spec, (array) ($content['schema'] ?? []), "rest body {$id} {$media} ", '', $out, []);
                }
                foreach ((array) ($op['responses'] ?? []) as $status => $response) {
                    $response = self::deref($spec, $response);
                    $out["rest status {$id} {$status}"] = 'response';
                    foreach ((array) ($response['content'] ?? []) as $media => $content) {
                        if (str_contains((string) $media, 'json')) {
                            self::walk($spec, (array) ($content['schema'] ?? []), "rest field {$id} {$status} ", '', $out, []);
                        }
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Parcourt un schéma JSON : une clé par propriété, `[]` pour les listes, `{}` pour les tables.
     *
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $node
     * @param  array<string, string>  $out
     * @param  list<string>  $seen
     */
    private static function walk(array $spec, array $node, string $prefix, string $path, array &$out, array $seen): void
    {
        if (isset($node['$ref'])) {
            if (in_array($node['$ref'], $seen, true)) {
                return;
            }
            $seen[] = $node['$ref'];
            $node = self::deref($spec, $node);
        }
        foreach (['allOf', 'anyOf', 'oneOf'] as $combiner) {
            foreach ((array) ($node[$combiner] ?? []) as $part) {
                self::walk($spec, (array) $part, $prefix, $path, $out, $seen);
            }
        }
        foreach ((array) ($node['properties'] ?? []) as $name => $property) {
            $property = (array) $property;
            $own = $path === '' ? (string) $name : "{$path}.{$name}";
            $out[$prefix.$own] = self::typeOf($spec, $property).(! empty(self::deref($spec, $property)['deprecated']) ? self::DEPRECATED : '');
            foreach (self::enumOf($spec, $property) as $value) {
                $out["{$prefix}{$own}={$value}"] = 'value';
            }
            self::walk($spec, $property, $prefix, $own, $out, $seen);
        }
        if (isset($node['items'])) {
            self::walk($spec, (array) $node['items'], $prefix, $path.'[]', $out, $seen);
        }
        if (isset($node['additionalProperties']) && is_array($node['additionalProperties'])) {
            self::walk($spec, $node['additionalProperties'], $prefix, $path.'{}', $out, $seen);
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $node
     */
    private static function typeOf(array $spec, array $node): string
    {
        $node = self::deref($spec, $node);
        if (isset($node['type'])) {
            $type = is_array($node['type']) ? implode('|', array_map('strval', array_filter($node['type'], fn ($t) => $t !== 'null'))) : (string) $node['type'];

            return $type === '' ? 'mixed' : $type;
        }
        foreach (['anyOf', 'oneOf', 'allOf'] as $combiner) {
            if (isset($node[$combiner])) {
                $types = array_values(array_unique(array_filter(array_map(fn ($part): string => self::typeOf($spec, (array) $part), (array) $node[$combiner]), fn ($t) => $t !== 'null')));
                sort($types);

                return $types === [] ? 'mixed' : implode('|', $types);
            }
        }

        return isset($node['properties']) ? 'object' : 'mixed';
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private static function enumOf(array $spec, array $node): array
    {
        $node = self::deref($spec, $node);

        return array_map('strval', array_values(array_filter((array) ($node['enum'] ?? []), fn ($v) => $v !== null)));
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function deref(array $spec, array $node): array
    {
        $guard = 0;
        while (isset($node['$ref']) && is_string($node['$ref']) && $guard++ < 10) {
            $target = $spec;
            foreach (explode('/', ltrim($node['$ref'], '#/')) as $part) {
                $target = $target[$part] ?? [];
            }
            $node = (array) $target;
        }

        return $node;
    }

    /** @return array<string, string> */
    private static function graphql(Schema $schema): array
    {
        $out = [];
        foreach ($schema->getTypeMap() as $name => $type) {
            if (str_starts_with((string) $name, '__')) {
                continue;
            }
            if ($type instanceof ObjectType || $type instanceof InterfaceType || $type instanceof InputObjectType) {
                foreach ($type->getFields() as $field) {
                    $out["gql {$name}.{$field->name}"] = (string) $field->getType().($field->isDeprecated() ? self::DEPRECATED : '');
                    foreach ($field->args ?? [] as $arg) {
                        $out["gql {$name}.{$field->name}({$arg->name})"] = (string) $arg->getType();
                    }
                }
            } elseif ($type instanceof EnumType) {
                foreach ($type->getValues() as $value) {
                    $out["gql {$name}={$value->name}"] = 'value'.($value->isDeprecated() ? self::DEPRECATED : '');
                }
            } elseif ($type instanceof UnionType) {
                foreach ($type->getTypes() as $member) {
                    $out["gql {$name}={$member->name}"] = 'member';
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $tools
     * @param  list<array<string, mixed>>  $prompts
     * @return array<string, string>
     */
    private static function mcp(array $tools, array $prompts): array
    {
        $out = [];
        foreach ($tools as $tool) {
            $name = (string) $tool['name'];
            $out["mcp tool {$name}"] = 'tool';
            $required = (array) ($tool['inputSchema']['required'] ?? []);
            foreach ((array) ($tool['inputSchema']['properties'] ?? []) as $param => $schema) {
                $type = is_array($schema['type'] ?? null) ? implode('|', $schema['type']) : (string) ($schema['type'] ?? 'mixed');
                $out["mcp tool {$name}.{$param}"] = $type.(in_array($param, $required, true) ? '!' : '');
            }
        }
        foreach ($prompts as $prompt) {
            $name = (string) $prompt['name'];
            $out["mcp prompt {$name}"] = 'prompt';
            foreach ((array) ($prompt['arguments'] ?? []) as $argument) {
                $out["mcp prompt {$name}.{$argument['name']}"] = 'argument'.(! empty($argument['required']) ? '!' : '');
            }
        }

        return $out;
    }
}
