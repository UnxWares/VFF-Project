<?php

namespace Tests\Unit\Static;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Les pages de compte vouvoient (français) et disent « Sie » (allemand), comme le reste du site :
 * aucun « tu », « te », « ton », « dich », « dein »… dans lang/{fr,de}/auth.php et passwords.php.
 */
class AuthTextTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function files(): array
    {
        return [
            'fr auth' => ['fr', 'auth'],
            'fr passwords' => ['fr', 'passwords'],
            'de auth' => ['de', 'auth'],
            'de passwords' => ['de', 'passwords'],
        ];
    }

    #[DataProvider('files')]
    public function test_account_texts_do_not_use_the_informal_form(string $locale, string $file): void
    {
        $pattern = $locale === 'fr'
            ? '/\b(tu|te|t\'|toi|ton|ta|tes)\b|\bt\'|\b(réessaye|patiente|saisis|choisis|gère|rejoins|accepte)\b/iu'
            : '/\b(du|dich|dir|dein|deine|deinen|deinem|deiner|gib|wähle|verwalte|tritt)\b/iu';

        $offenders = [];
        foreach ($this->flatten(require dirname(__DIR__, 3)."/lang/{$locale}/{$file}.php") as $key => $text) {
            // Les extraits de code (placeholders :seconds) n'ont pas de forme de politesse.
            if (preg_match($pattern, strip_tags($text))) {
                $offenders[] = "{$key} : {$text}";
            }
        }

        $this->assertSame([], $offenders, "Tutoiement dans lang/{$locale}/{$file}.php :\n".implode("\n", $offenders));
    }

    /** @return array<string, string> */
    private function flatten(array $messages, string $prefix = ''): array
    {
        $flat = [];
        foreach ($messages as $key => $value) {
            is_array($value)
                ? $flat += $this->flatten($value, $prefix.$key.'.')
                : $flat[$prefix.$key] = (string) $value;
        }

        return $flat;
    }
}
