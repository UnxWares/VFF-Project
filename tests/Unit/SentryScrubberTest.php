<?php

namespace Tests\Unit;

use App\Support\Monitoring\SentryScrubber;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\Stacktrace;
use Sentry\UserDataBag;

/** Événement d'erreur envoyé à l'outil de suivi : rien qui désigne un visiteur. */
class SentryScrubberTest extends TestCase
{
    public function test_retire_compte_requete_et_contexte(): void
    {
        $event = Event::createEvent();
        $event->setUser(UserDataBag::createFromUserIdentifier('42'));
        $event->setServerName('machine-secrete');
        $event->setTags(['a' => 'b']);
        $event->setRequest(['method' => 'POST', 'url' => 'https://vff-project.org/login?email=a@b.c', 'cookies' => ['s' => 'x'], 'data' => ['password' => 'p'], 'headers' => ['Cookie' => 'x']]);

        $out = SentryScrubber::scrub($event);

        $this->assertNull($out->getUser());
        $this->assertNull($out->getServerName());
        $this->assertSame([], $out->getTags());
        $this->assertSame(['method' => 'POST', 'url' => 'https://vff-project.org/login'], $out->getRequest());
    }

    public function test_message_sans_requete_sql(): void
    {
        $this->assertSame('SQLSTATE[23505]: Unique violation', SentryScrubber::message('SQLSTATE[23505]: Unique violation (Connection: pgsql, SQL: insert into users (email) values (a@b.c))'));
        $this->assertSame(500, mb_strlen(SentryScrubber::message(str_repeat('x', 900))));
    }

    public function test_retire_les_arguments_des_fonctions_de_la_pile(): void
    {
        // Sentry relève les arguments de chaque appel (FrameBuilder::getFunctionArguments) quand
        // zend.exception_ignore_args est désactivé, ce qui est le cas du conteneur de développement.
        $frame = new Frame('Illuminate\\Auth\\SessionGuard::attempt', 'vendor/x.php', 12, null, null, ['credentials' => ['email' => 'marie@example.org', 'password' => 'secret']]);
        $top = new Frame('main', 'index.php', 1, null, null, ['argv' => 'secret']);
        $event = Event::createEvent();
        $event->setExceptions([new ExceptionDataBag(new \RuntimeException('x'), new Stacktrace([$frame]))]);
        $event->setStacktrace(new Stacktrace([$top]));

        $out = SentryScrubber::scrub($event);

        $stacktrace = $out->getExceptions()[0]->getStacktrace();
        $this->assertNotNull($stacktrace);
        $this->assertSame([], $stacktrace->getFrames()[0]->getVars());
        $this->assertSame(12, $stacktrace->getFrames()[0]->getLine(), 'le reste du cadre est gardé');
        $this->assertSame([], $out->getStacktrace()?->getFrames()[0]->getVars());
    }

    public function test_retire_le_jeton_du_chemin(): void
    {
        $event = Event::createEvent();
        $event->setRequest(['method' => 'GET', 'url' => 'https://vff-project.org/reset-password/'.str_repeat('ab12', 16).'?email=a@b.c']);

        $this->assertSame('https://vff-project.org/reset-password/[token]', SentryScrubber::scrub($event)->getRequest()['url']);
    }
}
