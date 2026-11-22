<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Limites par adresse IP des formulaires d'authentification, en plus de celles par adresse e-mail.
 *
 * Avant : la limite de connexion portait sur le couple e-mail + IP (5 par minute), donc une seule IP
 * essayait autant d'adresses différentes qu'elle voulait ; l'inscription n'avait aucune limite ; la
 * demande de lien de réinitialisation n'était limitée que par adresse demandée (un courriel de
 * réinitialisation pour n'importe quelle adresse, sans fin).
 */
class AuthIpThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_ip_cannot_try_unlimited_different_accounts_on_login(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/login', ['email' => "inconnu{$i}@example.org", 'password' => 'mauvais'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'encore@example.org', 'password' => 'mauvais'])->assertStatus(429);
    }

    public function test_one_ip_cannot_create_accounts_without_end(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/register', ['name' => 'X'])->assertSessionHasErrors();
        }

        $this->post('/register', ['name' => 'X'])->assertStatus(429);
    }

    public function test_one_ip_cannot_trigger_password_mails_for_unlimited_addresses(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/forgot-password', ['email' => "personne{$i}@example.org"])->assertSessionHasNoErrors();
        }

        $this->post('/forgot-password', ['email' => 'autre@example.org'])->assertStatus(429);
    }

    public function test_overlong_credentials_are_refused_before_hashing(): void
    {
        $this->post('/login', ['email' => 'a@example.org', 'password' => str_repeat('x', 1001)])->assertSessionHasErrors('password');
        $this->post('/forgot-password', ['email' => str_repeat('a', 250).'@example.org'])->assertSessionHasErrors('email');
    }
}
