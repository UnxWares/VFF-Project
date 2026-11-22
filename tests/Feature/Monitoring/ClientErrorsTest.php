<?php

namespace Tests\Feature\Monitoring;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** Erreurs JavaScript du navigateur (POST /api/client-errors) : taille, débit, données retirées. */
class ClientErrorsTest extends TestCase
{
    use RefreshDatabase;

    /** @return object{logged: list<array<string, mixed>>} */
    private function fakeChannel(): object
    {
        $logger = new class
        {
            /** @var list<array<string, mixed>> */
            public array $logged = [];

            /** @param  array<string, mixed>  $context */
            public function error(string $message, array $context): void
            {
                $this->logged[] = $context;
            }
        };
        Log::shouldReceive('channel')->with('client-errors')->andReturn($logger);

        return $logger;
    }

    public function test_une_erreur_est_journalisee_sans_donnee_personnelle(): void
    {
        $logger = $this->fakeChannel();

        $this->postJson('/api/client-errors', [
            'type' => 'error',
            'message' => 'Boom pour marie@example.org avec '.str_repeat('a', 40),
            'page' => 'https://vff-project.org/lines/paris-lyon?email=marie%40example.org#x',
            'source' => 'https://vff-project.org/build/app.js?v=1',
            'line' => 12, 'column' => 3,
            'stack' => "Error: x\n at https://vff-project.org/build/app.js?token=zzz:12:3",
            'release' => 'v1.0',
            'cookie' => 'session=secret', 'user' => 'marie',
        ], ['REMOTE_ADDR' => '203.0.113.9'])->assertNoContent();

        $this->assertCount(1, $logger->logged);
        $entry = $logger->logged[0];
        $this->assertSame('https://vff-project.org/lines/paris-lyon', $entry['page']);
        $this->assertSame('https://vff-project.org/build/app.js', $entry['source']);
        $this->assertStringNotContainsString('marie', (string) json_encode($entry));
        $this->assertStringNotContainsString('token=zzz', (string) json_encode($entry));
        $this->assertStringNotContainsString(str_repeat('a', 40), (string) $entry['message']);
        $this->assertSame(['type', 'message', 'page', 'source', 'line', 'column', 'stack', 'release'], array_keys($entry));
    }

    public function test_un_corps_trop_gros_ou_invalide_est_ignore(): void
    {
        $logger = $this->fakeChannel();

        $this->call('POST', '/api/client-errors', server: ['CONTENT_TYPE' => 'application/json'], content: (string) json_encode(['message' => str_repeat('x', 9000)]))->assertNoContent();
        $this->call('POST', '/api/client-errors', server: ['CONTENT_TYPE' => 'application/json'], content: 'pas du json')->assertNoContent();
        $this->call('POST', '/api/client-errors', server: ['CONTENT_TYPE' => 'application/json'], content: '[1,2]')->assertNoContent();

        $this->assertSame([], $logger->logged);
    }

    public function test_message_et_pile_sont_tronques(): void
    {
        $logger = $this->fakeChannel();

        $this->postJson('/api/client-errors', ['message' => str_repeat('é ', 400), 'stack' => str_repeat("ligne\n", 100)])->assertNoContent();

        $this->assertLessThanOrEqual(300, mb_strlen((string) $logger->logged[0]['message']));
        $this->assertLessThanOrEqual(10, count(explode("\n", (string) $logger->logged[0]['stack'])));
    }

    public function test_debit_limite_par_adresse(): void
    {
        config(['monitoring.client_errors_per_minute' => 2]);
        $logger = $this->fakeChannel();

        foreach ([204, 204, 429] as $status) {
            $this->postJson('/api/client-errors', ['message' => 'x'], ['REMOTE_ADDR' => '203.0.113.50'])->assertStatus($status);
        }

        $this->assertCount(2, $logger->logged);
    }
}
