<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Redirections d'anciens slugs de gare (osm-<id>-...) vers le slug canonique
 * (basé sur le nom). Permet de passer à des URLs propres sans casser les liens
 * existants (redirection 301).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('old_slug')->unique();
            $table->foreignId('station_id')->constrained('stations')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_redirects');
    }
};
