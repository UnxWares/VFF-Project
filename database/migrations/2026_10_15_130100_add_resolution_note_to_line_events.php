<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trace du résolveur de sections (vff:resolve-event-sections) sur chaque
 * événement : comment la portion a été calculée (points de contrôle PK
 * utilisés, lieux géocodés…) ou POURQUOI elle n'a pas pu l'être
 * (section_resolution = 'unresolved'). Sans cette note, un « unresolved »
 * serait inexploitable pour la relecture humaine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_events', function (Blueprint $table): void {
            $table->string('section_resolution_note', 500)->nullable()->after('section_resolution')
                ->comment('Méthode / points utilisés, ou raison de l\'échec de résolution');
            $table->integer('section_length_m')->nullable()->after('section_resolution_note')
                ->comment('Longueur de section_geom (m), pour contrôle rapide');
        });
    }

    public function down(): void
    {
        Schema::table('line_events', function (Blueprint $table): void {
            $table->dropColumn(['section_resolution_note', 'section_length_m']);
        });
    }
};
