<?php

namespace Tests\Unit;

use App\Support\Monitoring\RedactedUrl;
use App\Support\Monitoring\SentryScrubber;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Context\OsContext;
use Sentry\Context\RuntimeContext;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\Stacktrace;

/** Cas limites du nettoyage des événements d'erreur (données personnelles) : chaque champ, chaque borne. */
class SentryScrubberMutationTest extends TestCase
{
    public function test_every_personal_field_of_the_event_is_emptied(): void
    {
        $event = Event::createEvent();
        $event->setBreadcrumb([new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'auth', 'login marie@example.org')]);
        $event->setExtra(['email' => 'marie@example.org']);
        $event->setTags(['user' => 'marie']);
        $event->setRuntimeContext(new RuntimeContext('php', '8.5'));
        $event->setOsContext(new OsContext('Linux', '7', '7.3', 'x'));

        $out = SentryScrubber::scrub($event);

        $this->assertSame([], $out->getBreadcrumbs());
        $this->assertSame([], $out->getExtra());
        $this->assertSame([], $out->getTags());
        $this->assertNull($out->getRuntimeContext());
        $this->assertNull($out->getOsContext());
    }

    public function test_the_request_keeps_the_method_and_the_clean_url_only(): void
    {
        $event = Event::createEvent();
        $event->setRequest(['method' => 'GET', 'url' => 'https://vff-project.org/lignes/12?q=a@b.c#x', 'query_string' => 'q=a@b.c']);
        $this->assertSame(['method' => 'GET', 'url' => 'https://vff-project.org/lignes/12'], SentryScrubber::scrub($event)->getRequest());

        // Sans méthode ni adresse lisible : aucune clé vide ne subsiste (array_filter).
        $event = Event::createEvent();
        $event->setRequest(['url' => 'pas une adresse', 'cookies' => ['s' => 'x']]);
        $this->assertSame([], SentryScrubber::scrub($event)->getRequest());

        $event = Event::createEvent();
        $event->setRequest(['method' => 'POST', 'url' => 42]);
        $this->assertSame(['method' => 'POST'], SentryScrubber::scrub($event)->getRequest());
    }

    public function test_the_message_of_each_exception_is_cut_before_the_sql(): void
    {
        $event = Event::createEvent();
        $event->setExceptions([
            new ExceptionDataBag(new \RuntimeException('x'), new Stacktrace([new Frame('f', 'a.php', 1)]), null),
        ]);
        $event->getExceptions()[0]->setValue('Duplicate key (SQL: insert into users values (marie@example.org))');

        $out = SentryScrubber::scrub($event);

        $this->assertSame('Duplicate key', $out->getExceptions()[0]->getValue());
    }

    public function test_message_cuts_at_the_connection_marker_and_counts_characters(): void
    {
        $this->assertSame('Erreur', SentryScrubber::message('Erreur   (Connection: pgsql, SQL: select 1)'));
        $this->assertSame('Erreur', SentryScrubber::message('Erreur (SQL: select 1) puis (Connection: pgsql)'));
        $this->assertSame('Erreur (sans requête)', SentryScrubber::message('Erreur (sans requête)'));
        // 600 caractères accentués : 500 caractères, pas 500 octets.
        $out = SentryScrubber::message(str_repeat('é', 600));
        $this->assertSame(500, mb_strlen($out));
        $this->assertSame(str_repeat('é', 500), $out);
    }

    public function test_redacted_url_keeps_scheme_host_port_and_path(): void
    {
        $this->assertSame('http://localhost:8000/lignes/12', RedactedUrl::of('http://localhost:8000/lignes/12?a=1#f'));
        $this->assertSame('https://vff-project.org', RedactedUrl::of('https://vff-project.org?a=1'));
        $this->assertSame('https://vff-project.org/', RedactedUrl::of('https://vff-project.org/'));
    }

    public function test_redacted_url_rejects_what_is_not_an_absolute_address(): void
    {
        $this->assertNull(RedactedUrl::of('/lignes/12'));
        $this->assertNull(RedactedUrl::of('https:///pas-d-hote'));
        $this->assertNull(RedactedUrl::of('//vff-project.org/x'));
        $this->assertNull(RedactedUrl::of('http://'));
        $this->assertNull(RedactedUrl::of('vff-project.org/lignes'));
    }

    public function test_redacted_url_replaces_segments_of_32_characters_or_more_only(): void
    {
        $t31 = str_repeat('a', 31);
        $t32 = str_repeat('a', 32);
        $this->assertSame("https://h.org/x/$t31", RedactedUrl::of("https://h.org/x/$t31"));
        $this->assertSame('https://h.org/x/[token]', RedactedUrl::of("https://h.org/x/$t32"));
        $this->assertSame('https://h.org/x/[token]/y', RedactedUrl::of("https://h.org/x/$t32/y"));
        $this->assertSame('https://h.org/[token]', RedactedUrl::of('https://h.org/'.str_repeat('A1_-', 10)));
        $this->assertSame('https://h.org/a/[token]/[token]', RedactedUrl::of("https://h.org/a/$t32/$t32"));
        // Un segment long mais fait d'autres caractères reste, un jeton collé à un point aussi.
        $this->assertSame("https://h.org/{$t32}.html", RedactedUrl::of("https://h.org/{$t32}.html"));
        $this->assertSame("https://h.org/x{$t32}é", RedactedUrl::of("https://h.org/x{$t32}é"));
    }
}
