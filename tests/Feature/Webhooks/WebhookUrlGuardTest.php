<?php

namespace Tests\Feature\Webhooks;

use App\Services\Webhooks\IpAddressPolicy;
use App\Services\Webhooks\WebhookSender;
use App\Services\Webhooks\WebhookTarget;
use App\Services\Webhooks\WebhookUrlGuard;
use App\Services\Webhooks\WebhookUrlRefused;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeHostResolver;
use Tests\TestCase;

/**
 * Protection SSRF des webhooks : HTTPS seulement, port 443, aucun identifiant, aucun nom local,
 * et toutes les adresses IP (écrites ou résolues, IPv4 et IPv6) publiques.
 */
class WebhookUrlGuardTest extends TestCase
{
    private FakeHostResolver $dns;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dns = new FakeHostResolver([
            'hooks.example.org' => ['93.184.215.14', '2606:2800:21f:cb07:6820:80da:af6b:8b2c'],
            'rebind.example.org' => ['93.184.215.14', '10.0.0.5'],
            'metadata.example.org' => ['169.254.169.254'],
            'v6local.example.org' => ['fd00:ec2::254'],
            'xn--bahnhf-0xa.example.org' => ['93.184.215.15'],
        ]);
    }

    private function guard(): WebhookUrlGuard
    {
        return new WebhookUrlGuard($this->dns);
    }

    private function refusal(string $url): ?string
    {
        try {
            $this->guard()->target($url);

            return null;
        } catch (WebhookUrlRefused $e) {
            return $e->reason;
        }
    }

    /** @return array<string, array{0: string}> */
    public static function blockedIps(): array
    {
        return [
            'bouclage v4' => ['127.0.0.1'],
            'bouclage v4 haut' => ['127.255.255.254'],
            'ce réseau' => ['0.0.0.0'],
            'privé 10' => ['10.1.2.3'],
            'privé 172.16' => ['172.16.0.1'],
            'privé 172.31' => ['172.31.255.255'],
            'privé 192.168' => ['192.168.1.1'],
            'partage opérateur (Alibaba)' => ['100.100.100.200'],
            'métadonnées AWS, GCP, Azure' => ['169.254.169.254'],
            'Oracle Cloud' => ['192.0.0.192'],
            'documentation' => ['203.0.113.7'],
            'performance' => ['198.18.0.1'],
            'multidiffusion' => ['224.0.0.1'],
            'diffusion' => ['255.255.255.255'],
            'réservé' => ['240.0.0.1'],
            'bouclage v6' => ['::1'],
            'non spécifié v6' => ['::'],
            'IPv4 transposée' => ['::ffff:127.0.0.1'],
            'IPv4 transposée publique' => ['::ffff:93.184.215.14'],
            'NAT64' => ['64:ff9b::a00:1'],
            'unique locale (AWS IMDS v6)' => ['fd00:ec2::254'],
            'lien local v6' => ['fe80::1'],
            'multidiffusion v6' => ['ff02::1'],
            'documentation v6' => ['2001:db8::1'],
            'Teredo' => ['2001:0:4136:e378::1'],
            '6to4' => ['2002:7f00:1::1'],
            'texte' => ['pas-une-ip'],
        ];
    }

    #[DataProvider('blockedIps')]
    public function test_non_public_addresses_are_refused(string $ip): void
    {
        $this->assertFalse(IpAddressPolicy::isPublic($ip));
    }

    public function test_public_addresses_are_accepted(): void
    {
        foreach (['93.184.215.14', '1.1.1.1', '172.32.0.1', '100.128.0.1', '2606:4700:4700::1111', '2a01:e0a::1'] as $ip) {
            $this->assertTrue(IpAddressPolicy::isPublic($ip), $ip);
        }
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function refusedUrls(): array
    {
        return [
            'http' => ['http://hooks.example.org/vff', WebhookUrlRefused::NOT_HTTPS],
            'ftp' => ['ftp://hooks.example.org/vff', WebhookUrlRefused::NOT_HTTPS],
            'sans protocole' => ['hooks.example.org/vff', WebhookUrlRefused::INVALID],
            'vide' => ['', WebhookUrlRefused::INVALID],
            'espace' => ['https://hooks.example.org/a b', WebhookUrlRefused::INVALID],
            'barre inverse' => ['https://hooks.example.org\\@10.0.0.1/', WebhookUrlRefused::INVALID],
            'trop longue' => ['https://hooks.example.org/'.str_repeat('a', 2050), WebhookUrlRefused::INVALID],
            'identifiants' => ['https://user:secret@hooks.example.org/', WebhookUrlRefused::CREDENTIALS],
            'port 8443' => ['https://hooks.example.org:8443/', WebhookUrlRefused::PORT],
            'port 22' => ['https://hooks.example.org:22/', WebhookUrlRefused::PORT],
            'localhost' => ['https://localhost/', WebhookUrlRefused::HOST],
            'sous-domaine de localhost' => ['https://api.localhost/', WebhookUrlRefused::HOST],
            '.internal' => ['https://metadata.google.internal/computeMetadata/v1/', WebhookUrlRefused::HOST],
            '.local' => ['https://imprimante.local/', WebhookUrlRefused::HOST],
            'home.arpa' => ['https://nas.home.arpa/', WebhookUrlRefused::HOST],
            'nom sans domaine' => ['https://intranet/', WebhookUrlRefused::HOST],
            'IP en décimal' => ['https://2130706433/', WebhookUrlRefused::HOST],
            'IP en hexadécimal' => ['https://0x7f.0.0.1/', WebhookUrlRefused::HOST],
            'IP raccourcie' => ['https://127.1/', WebhookUrlRefused::HOST],
            'IP bouclage' => ['https://127.0.0.1/', WebhookUrlRefused::PRIVATE_ADDRESS],
            'IP métadonnées' => ['https://169.254.169.254/latest/meta-data/', WebhookUrlRefused::PRIVATE_ADDRESS],
            'IPv6 bouclage' => ['https://[::1]/', WebhookUrlRefused::PRIVATE_ADDRESS],
            'IPv6 transposée' => ['https://[::ffff:10.0.0.1]/', WebhookUrlRefused::PRIVATE_ADDRESS],
            'DNS vers les métadonnées' => ['https://metadata.example.org/', WebhookUrlRefused::PRIVATE_ADDRESS],
            'DNS vers une IPv6 locale' => ['https://v6local.example.org/', WebhookUrlRefused::PRIVATE_ADDRESS],
            'une adresse DNS privée parmi d\'autres' => ['https://rebind.example.org/', WebhookUrlRefused::PRIVATE_ADDRESS],
            'nom introuvable' => ['https://inconnu.example.org/', WebhookUrlRefused::UNRESOLVED],
        ];
    }

    #[DataProvider('refusedUrls')]
    public function test_unsafe_urls_are_refused(string $url, string $reason): void
    {
        $this->assertSame($reason, $this->refusal($url));
    }

    public function test_public_https_url_is_normalised_and_pinned(): void
    {
        $target = $this->guard()->target('HTTPS://Hooks.Example.org./vff/hook?source=vff#ancre');

        $this->assertSame('https://hooks.example.org/vff/hook?source=vff', $target->url);
        $this->assertSame('hooks.example.org', $target->host);
        $this->assertSame(443, $target->port);
        $this->assertSame('93.184.215.14', $target->ip);
        $this->assertSame('hooks.example.org:443:93.184.215.14', $target->curlResolve());
    }

    public function test_idn_host_is_converted_to_ascii(): void
    {
        $this->assertSame('https://xn--bahnhf-0xa.example.org/', $this->guard()->target('https://bahnhöf.example.org')->url);
    }

    public function test_public_ip_literals_are_accepted_without_dns(): void
    {
        $v4 = $this->guard()->target('https://93.184.215.14/hook');
        $this->assertNull($v4->curlResolve());
        $this->assertSame('https://93.184.215.14/hook', $v4->url);

        $v6 = $this->guard()->target('https://[2606:4700:4700::1111]/hook');
        $this->assertSame('https://[2606:4700:4700::1111]/hook', $v6->url);
        $this->assertSame([], $this->dns->lookups);
    }

    public function test_ipv6_pin_is_bracketed(): void
    {
        $target = new WebhookTarget('https://h.example.org/', 'h.example.org', 443, '2606:4700::1');

        $this->assertSame('h.example.org:443:[2606:4700::1]', $target->curlResolve());
    }

    public function test_request_options_never_follow_redirects_nor_read_the_body(): void
    {
        $status = null;
        $options = app(WebhookSender::class)->options($this->guard()->target('https://hooks.example.org/'), $status);

        $this->assertFalse($options['allow_redirects']);
        $this->assertSame('', $options['proxy']);
        $this->assertSame(['hooks.example.org:443:93.184.215.14'], $options['curl'][CURLOPT_RESOLVE]);
        $this->assertSame(CURLPROTO_HTTPS, $options['curl'][CURLOPT_PROTOCOLS]);
        $this->assertLessThanOrEqual(5.0, $options['timeout']);
        $this->assertLessThanOrEqual(3.0, $options['connect_timeout']);
        $this->assertTrue(is_callable($options['on_headers']));
    }

    public function test_refusal_messages_are_translated(): void
    {
        app()->setLocale('en');
        try {
            $this->guard()->target('http://hooks.example.org/');
            $this->fail('Adresse acceptée');
        } catch (WebhookUrlRefused $e) {
            $this->assertSame('The address must start with https://.', $e->getMessage());
        }
    }
}
