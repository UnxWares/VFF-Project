<?php

namespace Tests\Unit;

use App\Support\Monitoring\SentryScrubber;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
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
}
