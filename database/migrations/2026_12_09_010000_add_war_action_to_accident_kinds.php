<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nature « fait de guerre » (`war_action`) : bombardement, mitraillage, ouvrage détruit par une
 * armée. Seule la contrainte de accidents.kind change ; aucune fiche n'est réécrite ici (les
 * natures viennent des corrections des fiches de contexte, rejouées par l'import).
 */
return new class extends Migration
{
    private const BEFORE = "('collision', 'derailment', 'level_crossing', 'fire', 'explosion', 'structure_failure', 'attack', 'person_struck', 'runaway', 'incident', 'other')";

    private const AFTER = "('collision', 'derailment', 'level_crossing', 'fire', 'explosion', 'structure_failure', 'attack', 'war_action', 'person_struck', 'runaway', 'incident', 'other')";

    public function up(): void
    {
        DB::statement('ALTER TABLE accidents DROP CONSTRAINT IF EXISTS accidents_kind_check');
        DB::statement('ALTER TABLE accidents ADD CONSTRAINT accidents_kind_check CHECK (kind IN '.self::AFTER.')');
    }

    public function down(): void
    {
        // Les fiches en `war_action` repassent en « autre » avant de remettre l'ancienne contrainte.
        DB::statement("UPDATE accidents SET kind = 'other' WHERE kind = 'war_action'");
        DB::statement('ALTER TABLE accidents DROP CONSTRAINT IF EXISTS accidents_kind_check');
        DB::statement('ALTER TABLE accidents ADD CONSTRAINT accidents_kind_check CHECK (kind IN '.self::BEFORE.')');
    }
};
