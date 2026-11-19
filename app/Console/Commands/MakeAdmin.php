<?php

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditLog;
use App\Support\Auth\AccountRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Nomme un administrateur : crée le compte s'il n'existe pas, sinon promeut le compte existant
 * après confirmation.
 *
 *   php artisan vff:make-admin prenom.nom@exemple.fr
 *
 * Le mot de passe d'un nouveau compte est demandé deux fois, sans affichage (jamais en argument :
 * il resterait dans l'historique du terminal et la liste des processus), et suit les règles de
 * l'inscription (AccountRules). Ni l'adresse ni le mot de passe ne sont écrits dans un journal ;
 * le journal d'audit garde l'identifiant du compte, la création ou la promotion et le rôle
 * précédent. Sans terminal interactif (--no-interaction), rien n'est fait.
 */
class MakeAdmin extends Command
{
    protected $signature = 'vff:make-admin
        {email : Adresse électronique du compte à créer ou à promouvoir}';

    protected $description = 'Crée un compte administrateur ou promeut un compte existant (mot de passe demandé sans affichage)';

    public function handle(AuditLog $audit): int
    {
        $email = trim((string) $this->argument('email'));
        $check = Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']]);
        if ($check->fails()) {
            $this->error('Adresse électronique invalide.');

            return self::INVALID;
        }

        // Confirmation ou mot de passe à taper : jamais sans terminal (tâche planifiée, script).
        if (! $this->input->isInteractive()) {
            $this->error('Commande interactive : lancez-la dans un terminal, sans --no-interaction.');

            return self::FAILURE;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();

        return $user === null ? $this->create($email, $audit) : $this->promote($user, $audit);
    }

    private function promote(User $user, AuditLog $audit): int
    {
        if ($user->isAdmin()) {
            $this->info(sprintf('Le compte n° %d est déjà administrateur : rien à faire.', $user->id));

            return self::SUCCESS;
        }

        $previous = $user->role;
        $question = sprintf('Le compte n° %d (« %s », rôle actuel : %s) deviendra administrateur. Confirmer ?', $user->id, $user->name, $previous->value);
        if (! $this->confirm($question, false)) {
            $this->warn('Aucun changement.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $previous, $audit): void {
            // `role` n'est pas affectable en masse (User::$fillable) : posé explicitement.
            $user->forceFill(['role' => UserRole::Admin->value])->save();
            $audit->record(AuditAction::AdminGranted, context: [
                'user_id' => $user->id,
                'created' => false,
                'previous_role' => $previous->value,
            ]);
        });

        $this->info(sprintf('Le compte n° %d est maintenant administrateur.', $user->id));

        return self::SUCCESS;
    }

    private function create(string $email, AuditLog $audit): int
    {
        $this->line('Aucun compte avec cette adresse : création d\'un compte administrateur.');

        $data = [
            'name' => trim((string) $this->ask('Nom d\'affichage')),
            'email' => $email,
            'password' => (string) $this->secret(sprintf('Mot de passe (au moins %d caractères, rien ne s\'affiche)', AccountRules::PASSWORD_MIN)),
            'password_confirmation' => (string) $this->secret('Mot de passe, une seconde fois'),
        ];

        $validator = Validator::make($data, AccountRules::newAccount());
        if ($validator->fails()) {
            // Messages des règles seulement : jamais la valeur saisie.
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::INVALID;
        }

        $user = DB::transaction(function () use ($data, $audit): User {
            $user = (new User)->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'], // haché par le cast du modèle
                'role' => UserRole::Admin->value,
                'locale_preference' => 'fr',
            ]);
            $user->save();

            $audit->record(AuditAction::AdminGranted, context: [
                'user_id' => $user->id,
                'created' => true,
                'previous_role' => null,
            ]);

            return $user;
        });

        $this->info(sprintf('Compte administrateur n° %d créé. Connectez-vous sur le site avec cette adresse.', $user->id));

        return self::SUCCESS;
    }
}
