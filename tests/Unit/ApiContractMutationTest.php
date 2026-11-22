<?php

namespace Tests\Unit;

use App\Support\ApiContract;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Instantané du contrat d'API : clés et types exacts pour REST, GraphQL et MCP, détection des ruptures. */
class ApiContractMutationTest extends TestCase
{
    private const SDL = <<<'GQL'
    type Query {
      line(slug: String!, era: Int): Line
      old: String @deprecated(reason: "x")
      list(first: Int!): [Line!]!
    }
    type Line { slug: String! name: String }
    interface Node { id: ID! }
    input Filter { text: String! limit: Int }
    enum Kind { A B @deprecated(reason: "x") }
    union Hit = Line
    GQL;

    private function emptySchema(): Schema
    {
        return BuildSchema::build('type Query { a: Int }');
    }

    /** @return array<string, mixed> */
    private function spec(): array
    {
        return [
            'paths' => [
                '/lines/{line}' => [
                    'parameters' => [['name' => 'ignored', 'in' => 'query']],
                    'get' => [
                        'parameters' => [
                            ['name' => 'line', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                            ['name' => 'era', 'in' => 'query', 'schema' => ['type' => 'integer', 'enum' => [1900, 2000, null]], 'deprecated' => true],
                            ['$ref' => '#/components/parameters/Page'],
                        ],
                        'responses' => [
                            '200' => ['content' => [
                                'application/json' => ['schema' => ['$ref' => '#/components/schemas/Line']],
                                'text/csv' => ['schema' => ['type' => 'object', 'properties' => ['csv' => ['type' => 'string']]]],
                            ]],
                            '422' => ['$ref' => '#/components/responses/Invalid'],
                        ],
                    ],
                    'post' => [
                        'deprecated' => true,
                        'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string']]]]]],
                        'responses' => ['204' => ['description' => 'ok']],
                    ],
                    'head' => ['responses' => ['200' => []]],
                    'summary' => 'texte',
                ],
            ],
            'components' => [
                'parameters' => ['Page' => ['name' => 'page', 'in' => 'query', 'schema' => ['type' => ['integer', 'null']]]],
                'responses' => ['Invalid' => ['content' => ['application/problem+json' => ['schema' => ['type' => 'object', 'properties' => ['message' => ['type' => 'string']]]]]]],
                'schemas' => [
                    'Line' => ['type' => 'object', 'properties' => [
                        'slug' => ['type' => 'string'],
                        'kind' => ['type' => 'string', 'enum' => ['a', 'b']],
                        'old' => ['type' => 'string', 'deprecated' => true],
                        'items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['n' => ['type' => 'integer']]]],
                        'tags' => ['type' => 'object', 'additionalProperties' => ['type' => 'object', 'properties' => ['v' => ['type' => 'string']]]],
                        'plain' => ['type' => 'object', 'additionalProperties' => true],
                        'self' => ['$ref' => '#/components/schemas/Line'],
                        'merged' => ['allOf' => [['type' => 'object', 'properties' => ['m1' => ['type' => 'boolean']]]], 'anyOf' => [['type' => 'object', 'properties' => ['m2' => ['type' => 'number']]]], 'oneOf' => [['type' => 'object', 'properties' => ['m3' => ['type' => 'string']]]]],
                    ]],
                ],
            ],
        ];
    }

    #[Test]
    public function rest_keys_and_types_are_exact(): void
    {
        $contract = ApiContract::build($this->spec(), $this->emptySchema(), []);

        $rest = array_filter($contract, fn (string $k): bool => str_starts_with($k, 'rest '), ARRAY_FILTER_USE_KEY);
        $this->assertSame([
            'rest body POST /lines/{line} application/json title' => 'string',
            'rest enum GET /lines/{line} query:era=1900' => 'value',
            'rest enum GET /lines/{line} query:era=2000' => 'value',
            'rest field GET /lines/{line} 200 items' => 'array',
            'rest field GET /lines/{line} 200 items[].n' => 'integer',
            'rest field GET /lines/{line} 200 kind' => 'string',
            'rest field GET /lines/{line} 200 kind=a' => 'value',
            'rest field GET /lines/{line} 200 kind=b' => 'value',
            'rest field GET /lines/{line} 200 merged' => 'object',
            'rest field GET /lines/{line} 200 merged.m1' => 'boolean',
            'rest field GET /lines/{line} 200 merged.m2' => 'number',
            'rest field GET /lines/{line} 200 merged.m3' => 'string',
            'rest field GET /lines/{line} 200 old' => 'string @deprecated',
            'rest field GET /lines/{line} 200 plain' => 'object',
            'rest field GET /lines/{line} 200 self' => 'object',
            'rest field GET /lines/{line} 200 slug' => 'string',
            'rest field GET /lines/{line} 200 tags' => 'object',
            'rest field GET /lines/{line} 200 tags{}.v' => 'string',
            'rest field GET /lines/{line} 422 message' => 'string',
            'rest op GET /lines/{line}' => 'operation',
            'rest op POST /lines/{line}' => 'operation @deprecated',
            'rest param GET /lines/{line} path:line' => 'string!',
            'rest param GET /lines/{line} query:era' => 'integer @deprecated',
            'rest param GET /lines/{line} query:page' => 'integer',
            'rest status GET /lines/{line} 200' => 'response',
            'rest status GET /lines/{line} 422' => 'response',
            'rest status POST /lines/{line} 204' => 'response',
        ], $rest);
    }

    #[Test]
    public function the_contract_is_sorted_by_key_as_strings(): void
    {
        $contract = ApiContract::build($this->spec(), BuildSchema::build(self::SDL), [['name' => 'z_tool']]);

        $keys = array_keys($contract);
        $sorted = $keys;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $keys);
    }

    #[Test]
    public function rest_details_are_individually_checked(): void
    {
        $c = ApiContract::build($this->spec(), $this->emptySchema(), []);

        $this->assertSame('operation', $c['rest op GET /lines/{line}']);
        $this->assertSame('operation @deprecated', $c['rest op POST /lines/{line}']);
        $this->assertArrayNotHasKey('rest op HEAD /lines/{line}', $c);
        $this->assertArrayNotHasKey('rest op SUMMARY /lines/{line}', $c);
        $this->assertArrayNotHasKey('rest op PARAMETERS /lines/{line}', $c);
        $this->assertSame('string!', $c['rest param GET /lines/{line} path:line']);
        $this->assertSame('integer @deprecated', $c['rest param GET /lines/{line} query:era']);
        $this->assertSame('integer', $c['rest param GET /lines/{line} query:page']);
        $this->assertSame('value', $c['rest enum GET /lines/{line} query:era=1900']);
        $this->assertArrayNotHasKey('rest enum GET /lines/{line} query:era=', $c);
        $this->assertSame('string', $c['rest body POST /lines/{line} application/json title']);
        $this->assertArrayNotHasKey('rest field GET /lines/{line} 200 csv', $c);
        $this->assertSame('response', $c['rest status GET /lines/{line} 422']);
        $this->assertSame('string', $c['rest field GET /lines/{line} 422 message']);
        $this->assertArrayNotHasKey('rest field POST /lines/{line} 204 title', $c);
        $this->assertSame('string @deprecated', $c['rest field GET /lines/{line} 200 old']);
    }

    #[Test]
    public function a_recursive_reference_is_visited_once_and_a_missing_one_is_mixed(): void
    {
        $spec = ['paths' => ['/x' => ['get' => ['responses' => ['200' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => [
            'a' => ['$ref' => '#/components/schemas/Node'],
            'b' => ['$ref' => '#/components/schemas/Absent'],
            'c' => [],
            'd' => ['type' => ['string', 'null']],
            'e' => ['type' => []],
            'f' => ['anyOf' => [['type' => 'string'], ['type' => 'null'], ['type' => 'integer'], ['type' => 'string']]],
            'g' => ['oneOf' => [['type' => 'null']]],
            'h' => ['properties' => ['k' => ['type' => 'string']]],
            'i' => ['allOf' => [['$ref' => '#/components/schemas/Node']]],
        ]]]]]]]]], 'components' => ['schemas' => ['Node' => ['type' => 'object', 'properties' => ['child' => ['$ref' => '#/components/schemas/Node'], 'id' => ['type' => 'integer']]]]]];

        $c = ApiContract::build($spec, $this->emptySchema(), []);

        $this->assertSame('object', $c['rest field GET /x 200 a']);
        $this->assertSame('integer', $c['rest field GET /x 200 a.id']);
        $this->assertSame('object', $c['rest field GET /x 200 a.child']);
        $this->assertArrayNotHasKey('rest field GET /x 200 a.child.id', $c);
        $this->assertSame('mixed', $c['rest field GET /x 200 b']);
        $this->assertSame('mixed', $c['rest field GET /x 200 c']);
        $this->assertSame('string', $c['rest field GET /x 200 d']);
        $this->assertSame('mixed', $c['rest field GET /x 200 e']);
        $this->assertSame('integer|string', $c['rest field GET /x 200 f']);
        $this->assertSame('mixed', $c['rest field GET /x 200 g']);
        $this->assertSame('object', $c['rest field GET /x 200 h']);
        $this->assertSame('string', $c['rest field GET /x 200 h.k']);
        $this->assertSame('integer', $c['rest field GET /x 200 i.id']);
    }

    #[Test]
    public function a_reference_chain_is_followed_but_not_beyond_ten_hops(): void
    {
        $schemas = [];
        for ($i = 0; $i < 12; $i++) {
            $schemas["S{$i}"] = $i === 11 ? ['type' => 'string'] : ['$ref' => '#/components/schemas/S'.($i + 1)];
        }
        $spec = ['paths' => ['/x' => ['get' => ['responses' => ['200' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => [
            'short' => ['$ref' => '#/components/schemas/S3'],
            'long' => ['$ref' => '#/components/schemas/S0'],
        ]]]]]]]]], 'components' => ['schemas' => $schemas]];

        $c = ApiContract::build($spec, $this->emptySchema(), []);

        $this->assertSame('string', $c['rest field GET /x 200 short']);
        $this->assertSame('mixed', $c['rest field GET /x 200 long']);
    }

    #[Test]
    public function a_chain_of_ten_references_resolves_and_eleven_does_not(): void
    {
        $schemas = [];
        for ($i = 0; $i <= 10; $i++) {
            $schemas["A{$i}"] = $i === 9 ? ['type' => 'string'] : ['$ref' => '#/components/schemas/A'.($i + 1)];
            $schemas["B{$i}"] = $i === 10 ? ['type' => 'string'] : ['$ref' => '#/components/schemas/B'.($i + 1)];
        }
        $spec = ['paths' => ['/x' => ['get' => ['responses' => ['200' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => [
            'ten' => ['$ref' => '#/components/schemas/A0'],
            'eleven' => ['$ref' => '#/components/schemas/B0'],
        ]]]]]]]]], 'components' => ['schemas' => $schemas]];

        $c = ApiContract::build($spec, $this->emptySchema(), []);

        $this->assertSame('string', $c['rest field GET /x 200 ten']);
        $this->assertSame('mixed', $c['rest field GET /x 200 eleven']);
    }

    #[Test]
    public function an_mcp_parameter_with_its_own_enum_does_not_take_the_one_of_its_items(): void
    {
        $c = ApiContract::mcp([['name' => 't', 'inputSchema' => ['properties' => ['p' => ['type' => 'array', 'enum' => ['a'], 'items' => ['enum' => ['b']]]]]]], []);

        $this->assertSame(['mcp tool t' => 'tool', 'mcp tool t.p' => 'array', 'mcp enum t.p=a' => 'value'], $c);
    }

    #[Test]
    public function graphql_keys_cover_objects_interfaces_inputs_enums_and_unions(): void
    {
        $c = ApiContract::build([], BuildSchema::build(self::SDL), []);
        $gql = array_filter($c, fn (string $k): bool => str_starts_with($k, 'gql '), ARRAY_FILTER_USE_KEY);

        $this->assertSame([
            'gql Filter.limit' => 'Int',
            'gql Filter.text' => 'String!',
            'gql Hit=Line' => 'member',
            'gql Kind=A' => 'value',
            'gql Kind=B' => 'value @deprecated',
            'gql Line.name' => 'String',
            'gql Line.slug' => 'String!',
            'gql Node.id' => 'ID!',
            'gql Query.line' => 'Line',
            'gql Query.line(era)' => 'Int',
            'gql Query.line(slug)' => 'String!',
            'gql Query.list' => '[Line!]!',
            'gql Query.list(first)' => 'Int!',
            'gql Query.old' => 'String @deprecated',
        ], $gql);
        $this->assertArrayNotHasKey('gql Int=', $gql);
    }

    #[Test]
    public function mcp_tools_parameters_enums_and_prompts(): void
    {
        $tools = [
            ['name' => 'search', 'inputSchema' => ['required' => ['q'], 'properties' => [
                'q' => ['type' => 'string'],
                'kind' => ['type' => ['string', 'null'], 'enum' => ['a', null, 'b']],
                'tags' => ['type' => 'array', 'items' => ['enum' => ['x', 'y']]],
                'free' => [],
            ]]],
            ['name' => 'ping'],
        ];
        $prompts = [['name' => 'brief', 'arguments' => [['name' => 'line', 'required' => true], ['name' => 'era']]], ['name' => 'bare']];

        $c = ApiContract::mcp($tools, $prompts);

        $this->assertSame([
            'mcp tool search' => 'tool',
            'mcp tool search.q' => 'string!',
            'mcp tool search.kind' => 'string|null',
            'mcp enum search.kind=a' => 'value',
            'mcp enum search.kind=b' => 'value',
            'mcp tool search.tags' => 'array',
            'mcp enum search.tags=x' => 'value',
            'mcp enum search.tags=y' => 'value',
            'mcp tool search.free' => 'mixed',
            'mcp tool ping' => 'tool',
            'mcp prompt brief' => 'prompt',
            'mcp prompt brief.line' => 'argument!',
            'mcp prompt brief.era' => 'argument',
            'mcp prompt bare' => 'prompt',
        ], $c);
    }

    #[Test]
    public function build_merges_the_three_families_and_keeps_prompts_optional(): void
    {
        $c = ApiContract::build(['paths' => ['/p' => ['get' => ['responses' => []]]]], BuildSchema::build('type Query { a: Int }'), [['name' => 't']]);

        $this->assertSame(['gql Query.a' => 'Int', 'mcp tool t' => 'tool', 'rest op GET /p' => 'operation'], $c);
        $with = ApiContract::build([], $this->emptySchema(), [], [['name' => 'pr']]);
        $this->assertSame('prompt', $with['mcp prompt pr']);
    }

    #[Test]
    public function breaking_reports_removed_keys_and_changed_types_but_not_deprecated_removals(): void
    {
        $recorded = [
            'rest op GET /gone' => 'operation',
            'rest op GET /old' => 'operation @deprecated',
            'rest field GET /x 200 a' => 'string',
            'rest field GET /x 200 b' => 'string @deprecated',
            'rest field GET /x 200 c' => 'string',
            'gql Query.same' => 'Int',
        ];
        $current = [
            'rest field GET /x 200 a' => 'integer',
            'rest field GET /x 200 b' => 'string',
            'rest field GET /x 200 c' => 'string @deprecated',
            'gql Query.same' => 'Int',
            'gql Query.new' => 'Int',
        ];

        $this->assertSame([
            'removed' => ['rest op GET /gone'],
            'changed' => ['rest field GET /x 200 a : string => integer'],
        ], ApiContract::breaking($recorded, $current));
        $this->assertSame(['removed' => [], 'changed' => []], ApiContract::breaking([], $current));
        $this->assertSame(['removed' => [], 'changed' => []], ApiContract::breaking($current, $current));
    }

    #[Test]
    public function a_required_input_becoming_optional_is_not_breaking_but_the_reverse_and_outputs_are(): void
    {
        $this->assertSame(['removed' => [], 'changed' => []], ApiContract::breaking(['rest param GET /x query:a' => 'string!'], ['rest param GET /x query:a' => 'string']));
        $this->assertSame(['removed' => [], 'changed' => []], ApiContract::breaking(['mcp tool t.a' => 'string!'], ['mcp tool t.a' => 'string']));
        $this->assertSame(['removed' => [], 'changed' => []], ApiContract::breaking(['mcp prompt p.a' => 'argument!'], ['mcp prompt p.a' => 'argument']));
        $this->assertSame(['removed' => [], 'changed' => []], ApiContract::breaking(['gql Query.f(a)' => 'Int!'], ['gql Query.f(a)' => 'Int']));
        $this->assertSame(['removed' => [], 'changed' => []], ApiContract::breaking(['rest param GET /x query:a' => 'string! @deprecated'], ['rest param GET /x query:a' => 'string']));

        $this->assertSame(['rest param GET /x query:a : string => string!'], ApiContract::breaking(['rest param GET /x query:a' => 'string'], ['rest param GET /x query:a' => 'string!'])['changed']);
        $this->assertSame(['rest param GET /x query:a : string! => integer'], ApiContract::breaking(['rest param GET /x query:a' => 'string!'], ['rest param GET /x query:a' => 'integer'])['changed']);
        $this->assertSame(['rest field GET /x 200 a : string! => string'], ApiContract::breaking(['rest field GET /x 200 a' => 'string!'], ['rest field GET /x 200 a' => 'string'])['changed']);
        $this->assertSame(['gql Query.f : Int! => Int'], ApiContract::breaking(['gql Query.f' => 'Int!'], ['gql Query.f' => 'Int'])['changed']);
        $this->assertSame(['rest body POST /x application/json a : string! => string'], ApiContract::breaking(['rest body POST /x application/json a' => 'string!'], ['rest body POST /x application/json a' => 'string'])['changed']);
        $this->assertSame(['mcp enum t.a=x : value => member'], ApiContract::breaking(['mcp enum t.a=x' => 'value'], ['mcp enum t.a=x' => 'member'])['changed']);
    }

    #[Test]
    public function the_constants_name_the_marker_and_the_file(): void
    {
        $this->assertSame(' @deprecated', ApiContract::DEPRECATED);
        $this->assertSame('tests/Fixtures/api-contract.json', ApiContract::FILE);
    }
}
