<?php

namespace Tests\Feature\Commands;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * vff:make-admin : création d'un compte administrateur (mot de passe demandé sans affichage, règles
 * de l'inscription) ou promotion d'un compte existant après confirmation ; journal d'audit sans
 * adresse ni mot de passe.
 */
class MakeAdminTest extends TestCase
{
    use RefreshDatabase;

    private const NAME_Q = 'Nom d\'affichage';

    private const PASSWORD_Q = 'Mot de passe (au moins 8 caractères, rien ne s\'affiche)';

    private const CONFIRM_Q = 'Mot de passe, une seconde fois';

    /** @var list<string> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();
        Event::listen(MessageLogged::class, function (MessageLogged $log): void {
            $this->logged[] = $log->message.' '.json_encode($log->context);
        });
    }

    public function test_creates_an_administrator_with_a_hidden_password(): void
    {
        $this->artisan('vff:make-admin', ['email' => 'baptiste@exemple.fr'])
            ->expectsQuestion(self::NAME_Q, 'Baptiste')
            ->expectsQuestion(self::PASSWORD_Q, 'un-secret-solide')
            ->expectsQuestion(self::CONFIRM_Q, 'un-secret-solide')
            ->expectsOutputToContain('Compte administrateur n°')
            ->doesntExpectOutputToContain('un-secret-solide')
            ->assertSuccessful();

        $user = User::where('email', 'baptiste@exemple.fr')->sole();
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertSame('Baptiste', $user->name);
        $this->assertTrue(Hash::check('un-secret-solide', $user->password));
        $this->assertNotSame('un-secret-solide', $user->getRawOriginal('password'));

        $event = AuditEvent::sole();
        $this->assertSame(AuditAction::AdminGranted, $event->action);
        $this->assertEquals(['user_id' => $user->id, 'created' => true, 'previous_role' => null], $event->context);
        $this->assertStringNotContainsString('baptiste@exemple.fr', (string) json_encode($event->context));
        foreach ($this->logged as $line) {
            $this->assertStringNotContainsString('un-secret-solide', $line);
        }
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function refusedAccounts(): array
    {
        return [
            'mot de passe court' => ['Baptiste', 'court', 'court'],
            'confirmation différente' => ['Baptiste', 'un-secret-solide', 'autre-secret'],
            'nom vide' => ['', 'un-secret-solide', 'un-secret-solide'],
        ];
    }

    /** Mêmes règles que le formulaire d'inscription. */
    #[DataProvider('refusedAccounts')]
    public function test_refuses_what_the_registration_form_refuses(string $name, string $password, string $confirmation): void
    {
        $this->artisan('vff:make-admin', ['email' => 'baptiste@exemple.fr'])
            ->expectsQuestion(self::NAME_Q, $name)
            ->expectsQuestion(self::PASSWORD_Q, $password)
            ->expectsQuestion(self::CONFIRM_Q, $confirmation)
            ->doesntExpectOutputToContain($password)
            ->assertExitCode(2);

        $this->assertSame(0, User::count());
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_refuses_an_invalid_address(): void
    {
        $this->artisan('vff:make-admin', ['email' => 'pas-une-adresse'])->assertExitCode(2);
        $this->assertSame(0, User::count());
    }

    public function test_promotes_an_existing_account_after_confirmation(): void
    {
        $user = User::factory()->contributor()->create(['email' => 'Baptiste@Exemple.fr', 'name' => 'Baptiste']);

        $this->artisan('vff:make-admin', ['email' => 'baptiste@exemple.fr'])
            ->expectsConfirmation('Le compte n° '.$user->id.' (« Baptiste », rôle actuel : contributor) deviendra administrateur. Confirmer ?', 'yes')
            ->expectsOutputToContain('est maintenant administrateur')
            ->assertSuccessful();

        $this->assertSame(UserRole::Admin, $user->fresh()->role);
        $this->assertSame(1, User::count(), 'Aucun compte créé');
        $this->assertEquals(['user_id' => $user->id, 'created' => false, 'previous_role' => 'contributor'], AuditEvent::sole()->context);
    }

    public function test_nothing_changes_without_confirmation(): void
    {
        $user = User::factory()->create(['email' => 'paul@exemple.fr', 'name' => 'Paul']);

        $this->artisan('vff:make-admin', ['email' => 'paul@exemple.fr'])
            ->expectsConfirmation('Le compte n° '.$user->id.' (« Paul », rôle actuel : visitor) deviendra administrateur. Confirmer ?', 'no')
            ->assertFailed();

        $this->assertSame(UserRole::Visitor, $user->fresh()->role);
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_without_a_terminal_nothing_is_promoted(): void
    {
        $user = User::factory()->create(['email' => 'paul@exemple.fr']);

        $this->artisan('vff:make-admin', ['email' => 'paul@exemple.fr', '--no-interaction' => true])->assertFailed();
        $this->assertSame(UserRole::Visitor, $user->fresh()->role);
    }

    public function test_an_administrator_stays_as_is(): void
    {
        User::factory()->admin()->create(['email' => 'admin@exemple.fr']);

        $this->artisan('vff:make-admin', ['email' => 'admin@exemple.fr'])
            ->expectsOutputToContain('déjà administrateur')
            ->assertSuccessful();
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_the_password_cannot_be_given_as_an_argument(): void
    {
        $definition = Artisan::all()['vff:make-admin']->getDefinition();
        $this->assertSame(['email'], array_keys($definition->getArguments()));
        $this->assertFalse($definition->hasOption('password'));
    }
}
