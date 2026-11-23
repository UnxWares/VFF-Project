<?php

namespace Tests\Unit\Realtime;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Realtime\RealtimeRevocation;
use Illuminate\Broadcasting\Broadcasters\NullBroadcaster;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Pusher\Pusher;
use Tests\TestCase;

/** Coupure des connexions WebSocket : valeurs rendues, numéro envoyé en texte, erreurs consignées, champs surveillés. */
class RealtimeRevocationMutationTest extends TestCase
{
    private function enable(): void
    {
        config(['realtime.enabled' => true, 'broadcasting.default' => 'reverb']);
    }

    /** @param  callable(MockInterface): void  $pusherSetup */
    private function revocation(callable $pusherSetup): RealtimeRevocation
    {
        $pusher = \Mockery::mock(Pusher::class);
        $pusherSetup($pusher);
        $broadcaster = \Mockery::mock(PusherBroadcaster::class);
        $broadcaster->shouldReceive('getPusher')->andReturn($pusher);
        /** @var BroadcastManager&MockInterface $manager */
        $manager = \Mockery::mock(BroadcastManager::class);
        $manager->shouldReceive('connection')->andReturn($broadcaster);

        return new RealtimeRevocation($manager);
    }

    private function account(int $id = 42): User
    {
        $user = new User;
        $user->id = $id;
        $user->role = UserRole::Validator;

        return $user;
    }

    #[Test]
    public function le_numero_part_en_texte_et_la_coupure_rend_vrai(): void
    {
        $this->enable();
        $revocation = $this->revocation(function ($pusher): void {
            $pusher->shouldReceive('terminateUserConnections')->once()->with(\Mockery::on(fn ($id): bool => $id === '42'));
        });

        $this->assertTrue($revocation->terminateConnections($this->account(42)));
    }

    #[Test]
    public function desactive_rien_ne_part_et_la_reponse_est_fausse(): void
    {
        config(['realtime.enabled' => false, 'broadcasting.default' => 'reverb']);
        /** @var BroadcastManager&MockInterface $manager */
        $manager = \Mockery::mock(BroadcastManager::class);
        $manager->shouldNotReceive('connection');

        $this->assertFalse((new RealtimeRevocation($manager))->terminateConnections($this->account()));
    }

    #[Test]
    public function un_diffuseur_qui_n_est_pas_pusher_rend_faux_sans_erreur(): void
    {
        $this->enable();
        /** @var BroadcastManager&MockInterface $manager */
        $manager = \Mockery::mock(BroadcastManager::class);
        $manager->shouldReceive('connection')->andReturn(new NullBroadcaster);
        $handler = \Mockery::mock(ExceptionHandler::class);
        $handler->shouldNotReceive('report');
        $this->app->instance(ExceptionHandler::class, $handler);

        $this->assertFalse((new RealtimeRevocation($manager))->terminateConnections($this->account()));
    }

    #[Test]
    public function un_serveur_injoignable_est_consigne_et_la_reponse_est_fausse(): void
    {
        $this->enable();
        $error = new \RuntimeException('Reverb injoignable');
        $handler = \Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('report')->once()->with($error);
        $this->app->instance(ExceptionHandler::class, $handler);
        $revocation = $this->revocation(function ($pusher) use ($error): void {
            $pusher->shouldReceive('terminateUserConnections')->andThrow($error);
        });

        $this->assertFalse($revocation->terminateConnections($this->account()));
    }

    #[Test]
    public function apres_l_enregistrement_chaque_champ_surveille_coupe_et_les_autres_non(): void
    {
        $this->enable();
        $this->assertSame(['role', 'suspended_at'], RealtimeRevocation::WATCHED_FIELDS);

        foreach ([['role', 1], ['suspended_at', 1], ['name', 0], ['email', 0]] as [$field, $calls]) {
            $revocation = $this->revocation(function ($pusher) use ($calls): void {
                $pusher->shouldReceive('terminateUserConnections')->times($calls);
            });
            /** @var User&MockInterface $account */
            /** @var User&MockInterface $account */
            $account = \Mockery::mock(User::class)->makePartial();
            $account->shouldReceive('wasChanged')->andReturnUsing(fn ($fields): bool => in_array($field, (array) $fields, true));
            $account->setAttribute('id', 7);

            $revocation->afterSave($account);
        }
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function apres_l_enregistrement_les_deux_champs_sont_demandes_ensemble(): void
    {
        $this->enable();
        $asked = null;
        $revocation = $this->revocation(function ($pusher): void {
            $pusher->shouldReceive('terminateUserConnections')->once();
        });
        /** @var User&MockInterface $account */
        $account = \Mockery::mock(User::class)->makePartial();
        $account->shouldReceive('wasChanged')->once()->andReturnUsing(function ($fields) use (&$asked): bool {
            $asked = $fields;

            return true;
        });
        $account->setAttribute('id', 7);

        $revocation->afterSave($account);

        $this->assertSame(['role', 'suspended_at'], $asked);
    }
}
