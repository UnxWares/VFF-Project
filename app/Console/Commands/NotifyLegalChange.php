<?php

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Enums\LegalDocument;
use App\Enums\NotificationKind;
use App\Models\DatabaseNotification;
use App\Models\User;
use App\Notifications\LegalDocumentChanged;
use App\Services\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Information des titulaires d'un compte avant un changement important des CGU (« au moins
 * 15 jours avant son entrée en vigueur ») ou de la politique de confidentialité, sur le site
 * (page /notifications) et par courriel. Relancer la commande après des courriels en échec
 * renvoie les courriels sans doubler les notifications du site.
 *
 *   php artisan vff:notify-legal-change use-conditions --effective=2027-01-15          # simulation
 *   php artisan vff:notify-legal-change use-conditions --effective=2027-01-15 --send   # envoi
 *
 * À lancer à la main, une fois la nouvelle version publiée avec sa date. L'envoi est consigné
 * dans le journal d'audit (texte, date d'entrée en vigueur, nombre de destinataires) : c'est la
 * trace que les membres ont été prévenus dans le délai.
 */
class NotifyLegalChange extends Command
{
    protected $signature = 'vff:notify-legal-change
        {document : use-conditions ou privacy}
        {--effective= : Date d\'entrée en vigueur de la nouvelle version (AAAA-MM-JJ)}
        {--send : Envoie réellement les courriels (sinon simple simulation)}';

    protected $description = 'Prévient les membres, sur le site et par courriel, d\'un changement des CGU ou de la politique de confidentialité';

    public function handle(AuditLog $audit): int
    {
        $document = LegalDocument::tryFrom((string) $this->argument('document'));
        if ($document === null) {
            $this->error('Texte inconnu : attendu use-conditions ou privacy.');

            return self::INVALID;
        }

        $raw = (string) $this->option('effective');
        $effective = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $raw) === 1
            ? CarbonImmutable::createFromFormat('!Y-m-d', $raw)
            : null;
        if (! $effective instanceof CarbonImmutable || $effective->format('Y-m-d') !== $raw) {
            $this->error('Date d\'entrée en vigueur manquante ou invalide (--effective=AAAA-MM-JJ).');

            return self::INVALID;
        }

        $earliest = CarbonImmutable::today()->addDays($document->minimumNoticeDays());
        if ($effective->lt($earliest)) {
            $this->error(sprintf(
                'Entrée en vigueur trop proche : les membres doivent être prévenus %d jours avant, soit le %s au plus tôt.',
                $document->minimumNoticeDays(),
                $earliest->format('Y-m-d'),
            ));

            return self::INVALID;
        }

        $recipients = User::query()->count();
        if (! $this->option('send')) {
            $this->info(sprintf('Simulation : %d membre(s) seraient prévenus. Ajoutez --send pour envoyer.', $recipients));

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;
        User::query()->lazyById(100)->each(function (User $user) use ($document, $effective, &$sent, &$failed): void {
            $notification = new LegalDocumentChanged($document, $effective);
            // Nouvel essai après un courriel en échec : la notification du site existe déjà, on ne
            // la double pas.
            $channels = $this->alreadyOnSite($user, $notification) ? ['mail'] : ['database', 'mail'];
            try {
                $user->notifyNow($notification, $channels);
                $sent++;
            } catch (Throwable $e) {
                report($e);
                $failed++;
            }
        });

        $audit->record(AuditAction::MembersNotified, context: [
            'document' => $document->value,
            'effective_date' => $effective->format('Y-m-d'),
            'sent' => $sent,
            'failed' => $failed,
        ]);

        $this->info(sprintf('%d membre(s) prévenu(s).', $sent));
        if ($failed > 0) {
            $this->warn(sprintf('%d courriel(s) en échec : voir le journal de l\'application.', $failed));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** Le membre a-t-il déjà, sur le site, la notification de ce même changement ? */
    private function alreadyOnSite(User $user, LegalDocumentChanged $notification): bool
    {
        return $user->notifications()
            ->where('data->kind', NotificationKind::LegalDocumentChanged->value)
            ->get(['data'])
            ->contains(fn (DatabaseNotification $n): bool => $notification->isSameChange($n->data));
    }
}
