<?php

namespace Tests\Feature;

use App\Services\Signalling\SignallingCorrectionsFile;
use Tests\TestCase;

/** Le chemin par défaut du fichier des corrections de signalisation est celui du dossier de données. */
class SignallingCorrectionsFilePathTest extends TestCase
{
    public function test_the_default_path_points_at_the_data_file(): void
    {
        $this->assertSame(database_path('data/signalling_history_corrections.php'), SignallingCorrectionsFile::defaultPath());
        $this->assertFileExists(SignallingCorrectionsFile::defaultPath());
    }
}
