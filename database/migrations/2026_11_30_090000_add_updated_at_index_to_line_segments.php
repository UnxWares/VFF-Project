<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index sur `line_segments.updated_at` : la dernière modification des segments (`MAX(updated_at)`) sert
 * d'empreinte des données à l'accueil (App\Services\NetworkStats::dataFingerprint) et de version des tuiles
 * à la carte (MapController : `dataVersion`). Sans index, PostgreSQL relisait les 383 000 segments à chaque
 * page (environ 100 ms) ; avec l'index, la valeur se lit au bout de l'arbre (moins d'une milliseconde).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('line_segments', function (Blueprint $table): void {
            $table->dropIndex(['updated_at']);
        });
    }
};
