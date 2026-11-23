<?php

namespace Tests\Unit\Static;

use PHPUnit\Framework\TestCase;

/**
 * Configuration nginx de référence (deploy/nginx/vff-project.org.conf) : la position de la page « Autour de moi »
 * (`GET /api/v1/what-was-here?lat=…&lng=…`) n'entre dans aucun journal de nginx, comme le dit la politique de
 * confidentialité. Le journal d'accès n'écrit que le chemin, et l'erreur d'une requête (qui écrit la ligne de
 * requête entière, avec l'adresse IP complète) n'est pas journalisée pour ce point d'API.
 */
class NginxPositionLogTest extends TestCase
{
    private static function conf(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 3).'/deploy/nginx/vff-project.org.conf');
    }

    public function test_the_access_log_writes_the_path_without_the_query(): void
    {
        $this->assertMatchesRegularExpression('/log_format vff [^;]*"\$request_method \$uri \$server_protocol"/', self::conf());
        $this->assertDoesNotMatchRegularExpression('/log_format vff [^;]*(\$request_uri|\$args|\$query_string|\$request\b)/', self::conf());
    }

    public function test_the_nearby_endpoint_has_no_error_log_and_reaches_php_without_internal_redirect(): void
    {
        $this->assertSame(1, preg_match('/location = \/api\/v1\/what-was-here \{([^}]*)\}/', self::conf(), $m), 'location du point d\'API');
        $block = $m[1];

        $this->assertMatchesRegularExpression('/^\s*error_log \/dev\/null;/m', $block);
        $this->assertStringContainsString('include /etc/nginx/snippets/vff-php.conf;', $block);
        // Une redirection interne vers /index.php donnerait à l'erreur le journal de cette location-là.
        $this->assertStringNotContainsString('try_files', $block);
    }
}
