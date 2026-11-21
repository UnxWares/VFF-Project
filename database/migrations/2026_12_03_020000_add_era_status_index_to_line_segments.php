<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Index (era_id, status) couvrant line_id et traffic_type sur `line_segments`, pour le compteur par
 * trafic de la liste des lignes (App\Services\LineDirectory::facets : tronçons en service d'une ère,
 * lignes distinctes par trafic). Sans lui, PostgreSQL croisait l'index des statuts (378 000 entrées
 * « en service » toutes ères confondues) avec celui des lignes, puis relisait 8 000 pages de la
 * table : environ 45 ms à chaque affichage de /lines. Avec lui, la lecture se fait dans l'index seul.
 *
 * CONCURRENTLY : la création ne bloque ni les lectures ni une reconstruction en cours. PostgreSQL
 * l'interdit dans une transaction, d'où `$withinTransaction = false`.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS line_segments_era_id_status_index
            ON line_segments (era_id, status) INCLUDE (line_id, traffic_type)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS line_segments_era_id_status_index');
    }
};
