<?php

namespace Tests\Unit\Realtime;

use App\Http\Middleware\RequirePrivateBroadcastChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** Autorisation des canaux : seuls les canaux privés simples passent, tout le reste est refusé en 403. */
class RequirePrivateBroadcastChannelMutationTest extends TestCase
{
    private function pass(Request $request): SymfonyResponse
    {
        $next = fn (Request $r): Response => new Response('suite', 200);

        return (new RequirePrivateBroadcastChannel)->handle($request, $next);
    }

    /** @return array<string, array{string}> */
    public static function allowed(): array
    {
        return [
            'canal prive' => ['private-review-queue'],
            'prefixe seul' => ['private-'],
            'mot proche du chiffre' => ['private-encrypted'],
            'chiffre au milieu' => ['private-x-private-encrypted-y'],
        ];
    }

    /** @return array<string, array{mixed}> */
    public static function refused(): array
    {
        return [
            'presence' => ['presence-review-queue'],
            'public' => ['review-queue'],
            'chiffre' => ['private-encrypted-review-queue'],
            'chiffre seul' => ['private-encrypted-'],
            'prefixe au milieu' => ['x-private-review-queue'],
            'casse' => ['Private-review-queue'],
            'vide' => [''],
            'tableau' => [['private-review-queue']],
            'nombre' => [12],
        ];
    }

    #[Test]
    #[DataProvider('allowed')]
    public function un_canal_prive_simple_passe_a_la_suite(string $channel): void
    {
        $response = $this->pass(Request::create('/broadcasting/auth', 'POST', ['channel_name' => $channel]));

        $this->assertSame('suite', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    #[DataProvider('refused')]
    public function tout_autre_canal_est_refuse_en_403(mixed $channel): void
    {
        try {
            $this->pass(Request::create('/broadcasting/auth', 'POST', ['channel_name' => $channel]));
            $this->fail('canal admis : '.json_encode($channel));
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    #[Test]
    public function sans_nom_de_canal_la_requete_est_refusee_en_403(): void
    {
        try {
            $this->pass(Request::create('/broadcasting/auth', 'POST'));
            $this->fail('requête admise sans canal');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
