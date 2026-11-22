<?php

namespace Tests\Unit;

use App\Support\Seo\SiteOrigin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Origine publique du site tirée de `app.url`. */
class SiteOriginMutationTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function urls(): array
    {
        return [
            'barre finale' => ['https://vff-project.org/', 'https://vff-project.org'],
            'sans barre' => ['https://vff-project.org', 'https://vff-project.org'],
            'port' => ['http://localhost:8000', 'http://localhost:8000'],
            'chemin, requete et ancre' => ['https://vff-project.org:8443/sous/dossier/?a=1#b', 'https://vff-project.org:8443'],
            'identifiants retires' => ['https://moi:mdp@vff-project.org/x', 'https://vff-project.org'],
            'sans schema' => ['vff-project.org/', 'vff-project.org'],
            'sans hote' => ['/chemin//', '/chemin'],
            'texte libre, plusieurs barres' => ['pas une adresse///', 'pas une adresse'],
            'schema sans hote' => ['https:///x/', 'https:///x'],
            'vide' => ['', ''],
        ];
    }

    #[Test]
    #[DataProvider('urls')]
    public function l_origine_est_le_schema_l_hote_et_le_port_sans_barre_finale(string $configured, string $expected): void
    {
        config(['app.url' => $configured]);

        $this->assertSame($expected, SiteOrigin::get());
    }

    #[Test]
    public function une_valeur_absente_donne_une_origine_vide(): void
    {
        config(['app.url' => null]);

        $this->assertSame('', SiteOrigin::get());
    }
}
