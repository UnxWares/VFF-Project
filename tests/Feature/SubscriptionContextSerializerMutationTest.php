<?php

namespace Tests\Feature;

use App\GraphQL\Support\SubscriptionContextSerializer;
use App\Models\Contribution;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Contexte d'un abonné GraphQL gardé dans Valkey : forme exacte écrite et relue (cibles des tests de mutation). */
class SubscriptionContextSerializerMutationTest extends TestCase
{
    use RefreshDatabase;

    private function serializer(): SubscriptionContextSerializer
    {
        return new SubscriptionContextSerializer(app(CreatesContext::class));
    }

    private function context(?Request $request, ?Authenticatable $user): GraphQLContext
    {
        return new class($request, $user) implements GraphQLContext
        {
            public function __construct(private ?Request $request, private ?Authenticatable $user) {}

            public function user(): ?Authenticatable
            {
                return $this->user;
            }

            public function setUser(?Authenticatable $user): void
            {
                $this->user = $user;
            }

            public function request(): ?Request
            {
                return $this->request;
            }
        };
    }

    /** @return array{server: ?array<string, mixed>, user: ?array<string, mixed>} */
    private function written(?Request $request, ?Authenticatable $user): array
    {
        /** @var array{server: ?array<string, mixed>, user: ?array<string, mixed>} $data */
        $data = unserialize($this->serializer()->serialize($this->context($request, $user)), ['allowed_classes' => false]);

        return $data;
    }

    #[Test]
    public function seules_cinq_variables_de_serveur_sont_gardees_avec_leur_valeur(): void
    {
        $request = Request::create('https://vff.test:8443/graphql', 'POST', [], ['session' => 'c'], [], [
            'HTTP_COOKIE' => 'session=secret', 'REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'Navigateur', 'HTTP_AUTHORIZATION' => 'Bearer x',
        ]);

        $server = $this->written($request, null)['server'];

        $this->assertEqualsCanonicalizing(['HTTP_HOST', 'SERVER_NAME', 'SERVER_PORT', 'HTTPS', 'REQUEST_METHOD'], array_keys($server ?? []));
        $this->assertSame('vff.test:8443', $server['HTTP_HOST']);
        $this->assertSame('vff.test', $server['SERVER_NAME']);
        $this->assertSame(8443, $server['SERVER_PORT']);
        $this->assertSame('on', $server['HTTPS']);
        $this->assertSame('POST', $server['REQUEST_METHOD']);
    }

    #[Test]
    public function sans_requete_le_serveur_est_nul_et_sans_compte_le_compte_aussi(): void
    {
        $data = $this->written(null, null);

        $this->assertSame(['server' => null, 'user' => null], $data);
    }

    #[Test]
    public function un_compte_est_garde_par_sa_classe_et_son_numero_seulement(): void
    {
        $user = User::factory()->create();

        $data = $this->written(Request::create('https://vff.test/'), $user);

        $this->assertSame(['class' => User::class, 'id' => $user->getKey()], $data['user']);
    }

    #[Test]
    public function un_utilisateur_qui_n_est_pas_un_modele_n_est_pas_garde(): void
    {
        $guest = new class implements Authenticatable
        {
            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): int
            {
                return 7;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return 'x';
            }

            public function getRememberToken(): string
            {
                return '';
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName(): string
            {
                return '';
            }
        };

        $this->assertNull($this->written(Request::create('https://vff.test/'), $guest)['user']);
    }

    #[Test]
    public function le_compte_est_relu_dans_la_base_et_la_requete_garde_hote_schema_et_methode(): void
    {
        $user = User::factory()->create();
        $request = Request::create('https://vff.test:8443/graphql', 'POST', [], [], [], ['HTTP_COOKIE' => 'session=secret', 'REMOTE_ADDR' => '203.0.113.9']);

        $restored = $this->serializer()->unserialize($this->serializer()->serialize($this->context($request, $user)));

        $this->assertInstanceOf(User::class, $restored->user());
        $this->assertSame($user->getKey(), $restored->user()->getKey());
        $this->assertSame('vff.test', $restored->request()?->getHost());
        $this->assertSame(8443, $restored->request()->getPort());
        $this->assertTrue($restored->request()->isSecure());
        $this->assertSame('POST', $restored->request()->getMethod());
        $this->assertNull($restored->request()->headers->get('Cookie'));
        $this->assertNull($restored->request()->server->get('REMOTE_ADDR'));
        $this->assertSame($restored->user(), $restored->request()->user());
    }

    #[Test]
    public function a_la_relecture_les_variables_ajoutees_au_texte_sont_filtrees_de_nouveau(): void
    {
        $payload = serialize(['server' => ['HTTP_HOST' => 'vff.test', 'HTTP_COOKIE' => 'session=pirate', 'REMOTE_ADDR' => '1.2.3.4'], 'user' => null]);

        $request = $this->serializer()->unserialize($payload)->request();

        $this->assertNotNull($request);
        $this->assertNull($request->server->get('HTTP_COOKIE'));
        $this->assertNull($request->server->get('REMOTE_ADDR'));
        $this->assertSame('vff.test', $request->server->get('HTTP_HOST'));
    }

    #[Test]
    public function un_texte_sans_serveur_valide_donne_un_contexte_sans_requete_ni_compte(): void
    {
        $user = User::factory()->create();
        $bad = [
            serialize('texte'),
            serialize(['user' => ['class' => User::class, 'id' => $user->getKey()]]),
            serialize(['server' => 'pas un tableau', 'user' => ['class' => User::class, 'id' => $user->getKey()]]),
            serialize(['server' => null, 'user' => ['class' => User::class, 'id' => $user->getKey()]]),
            serialize(new \ArrayObject(['server' => ['HTTP_HOST' => 'x'], 'user' => null])),
        ];

        foreach ($bad as $payload) {
            $context = $this->serializer()->unserialize($payload);
            $this->assertNull($context->user());
            $this->assertNull($context->request(), 'requête présente pour '.$payload);
        }
    }

    /** @return array<string, array{mixed, mixed}> */
    public static function badUsers(): array
    {
        return [
            'classe inconnue' => ['App\\Inexistante', 1],
            'classe qui n est pas un modele' => [\stdClass::class, 1],
            'modele non authentifiable' => [Contribution::class, 1],
            'authentifiable non modele' => [AuthenticatableWithoutModel::class, 1],
            'ni modele ni authentifiable' => [\ArrayObject::class, 1],
            'classe non texte' => [['x'], 1],
            'classe absente' => [null, 1],
            'numero tableau' => [User::class, [1]],
            'numero flottant' => [User::class, 1.5],
            'numero absent' => [User::class, null],
            'numero booleen' => [User::class, true],
        ];
    }

    #[Test]
    #[DataProvider('badUsers')]
    public function un_compte_mal_decrit_donne_un_abonne_anonyme_mais_garde_la_requete(mixed $class, mixed $id): void
    {
        User::factory()->create();
        $payload = serialize(['server' => ['HTTP_HOST' => 'vff.test'], 'user' => ['class' => $class, 'id' => $id]]);

        $context = $this->serializer()->unserialize($payload);

        $this->assertNull($context->user());
        $this->assertSame('vff.test', $context->request()?->getHost());
    }

    #[Test]
    public function un_utilisateur_qui_n_est_pas_un_tableau_donne_un_abonne_anonyme(): void
    {
        $context = $this->serializer()->unserialize(serialize(['server' => ['HTTP_HOST' => 'vff.test'], 'user' => 'texte']));

        $this->assertNull($context->user());
        $this->assertSame('vff.test', $context->request()?->getHost());
    }

    #[Test]
    public function un_numero_de_compte_en_texte_est_accepte_et_un_compte_supprime_est_anonyme(): void
    {
        $user = User::factory()->create();
        $payload = fn (mixed $id): string => serialize(['server' => ['HTTP_HOST' => 'vff.test'], 'user' => ['class' => User::class, 'id' => $id]]);

        $this->assertSame($user->getKey(), $this->serializer()->unserialize($payload((string) $user->getKey()))->user()?->getAuthIdentifier());
        $this->assertNull($this->serializer()->unserialize($payload($user->getKey() + 1000))->user());
        $this->assertInstanceOf(AuthUser::class, $this->serializer()->unserialize($payload($user->getKey()))->user());
    }

    #[Test]
    public function un_objet_glisse_dans_le_texte_n_est_jamais_instancie(): void
    {
        WakeupGadget::$woken = 0;
        $payload = serialize(['server' => ['HTTP_HOST' => 'vff.test'], 'user' => new WakeupGadget]);

        $context = $this->serializer()->unserialize($payload);

        $this->assertSame(0, WakeupGadget::$woken);
        $this->assertNull($context->user());
        $this->assertSame('vff.test', $context->request()?->getHost());
        $this->assertSame(0, WakeupGadget::$woken);
        $this->assertNull($this->serializer()->unserialize(serialize(new WakeupGadget))->request());
        $this->assertSame(0, WakeupGadget::$woken);
    }
}

/** Classe dont l'instanciation à la relecture (`__wakeup`) se verrait. */
final class WakeupGadget
{
    public static int $woken = 0;

    public function __wakeup(): void
    {
        self::$woken++;
    }
}

/** Authentifiable qui n'est pas un modèle : jamais cherché dans la base. */
final class AuthenticatableWithoutModel implements Authenticatable
{
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return 1;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return 'x';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
