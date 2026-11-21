<?php

namespace Tests\Feature\Performance;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Le JIT de PostgreSQL est coupé sur les connexions des requêtes web (AppServiceProvider) : il compilait des
 * requêtes PostGIS courtes, surestimées par le planificateur (fiche ligne, tuiles). Les tests tournent en
 * console : `database.web_jit_off_in_console` simule une requête web.
 */
class WebJitTest extends TestCase
{
    public function test_a_web_connection_runs_without_jit(): void
    {
        config(['database.web_jit_off_in_console' => true]);
        DB::purge();

        $this->assertSame('off', DB::selectOne('SHOW jit')->jit);
    }

    public function test_console_commands_keep_the_server_setting(): void
    {
        config(['database.web_jit_off_in_console' => false]);
        DB::purge();
        $server = DB::selectOne("SELECT reset_val FROM pg_settings WHERE name = 'jit'")->reset_val;

        $this->assertSame($server, DB::selectOne('SHOW jit')->jit);
    }
}
