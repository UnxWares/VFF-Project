<?php

namespace Tests\Unit\Enums;

use App\Enums\ArgentanPhotoClass;
use PHPUnit\Framework\TestCase;

/**
 * Droits des photos de la galerie d'Argentan Web Ferroviaire : la licence CC BY-SA du pied de page
 * ne couvre que les œuvres de l'auteur. Seule une photo « œuvre propre » peut être republiée ;
 * une photo de nature non établie ne l'est jamais.
 */
class ArgentanPhotoClassTest extends TestCase
{
    public function test_only_the_authors_own_work_is_republishable(): void
    {
        foreach (ArgentanPhotoClass::cases() as $class) {
            $this->assertSame($class === ArgentanPhotoClass::OwnWork, $class->republishable(), $class->value);
        }
    }

    public function test_an_unknown_nature_is_never_republished(): void
    {
        $this->assertFalse(ArgentanPhotoClass::Unknown->republishable());
    }

    public function test_every_class_has_a_distinct_french_label(): void
    {
        $labels = array_map(fn (ArgentanPhotoClass $c) => $c->label(), ArgentanPhotoClass::cases());

        $this->assertCount(count(ArgentanPhotoClass::cases()), array_unique($labels));
        foreach ($labels as $label) {
            $this->assertNotSame('', $label);
        }
    }

    public function test_the_own_work_label_names_the_licence(): void
    {
        $this->assertStringContainsString('CC BY-SA 4.0', ArgentanPhotoClass::OwnWork->label());
    }

    public function test_stored_values_are_stable(): void
    {
        $this->assertSame(
            ['own_work', 'postcard', 'archive', 'credited', 'unknown'],
            array_map(fn (ArgentanPhotoClass $c) => $c->value, ArgentanPhotoClass::cases()),
        );
        $this->assertSame(ArgentanPhotoClass::Postcard, ArgentanPhotoClass::from('postcard'));
    }
}
