<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matériel roulant : état d'utilisation d'une série (Wikidata P5817), périmètre de la fiche, et
 * catégorie « bimode ».
 *
 * - `service_status` : `in_service` ou `withdrawn`, seulement quand Wikidata le déclare, avec la date
 *   de lecture de la source (`service_status_checked_at`). Nul : la source ne dit rien, et la
 *   fiche n'écrit jamais « en service » (une date de retrait absente ne prouve pas qu'une série circule).
 * - `scope` : `france` (série rattachée à la France dans Wikidata) ou `attested` (série qui n'y est pas
 *   rattachée, par exemple un ICE ou une locomotive belge, ajoutée parce qu'une source atteste sa
 *   circulation sur une ligne française : voir `line_materials.evidence`).
 * - `type` : la catégorie reste une chaîne (pas d'énumération en base) ; seul son commentaire change.
 */
return new class extends Migration
{
    private const TYPE_COMMENT_BEFORE = 'locomotive_electrique | locomotive_diesel | locomotive_vapeur | automotrice | autorail | rame_tgv | voiture | wagon';

    private const TYPE_COMMENT_AFTER = 'locomotive_electrique | locomotive_diesel | locomotive_vapeur | automotrice | bimode | autorail | rame_tgv | voiture | wagon | tramway | engin_service';

    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table): void {
            $table->string('service_status', 20)->nullable()->comment('in_service | withdrawn, Wikidata P5817 ; nul si la source ne dit rien');
            $table->date('service_status_checked_at')->nullable()->comment('Date de lecture de Wikidata pour service_status');
            $table->string('scope', 20)->default('france')->comment('france : rattachée à la France dans Wikidata ; attested : circulation en France attestée par une ligne citée');
            $table->index('scope');
            $table->string('type')->comment(self::TYPE_COMMENT_AFTER)->change();
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table): void {
            $table->string('type')->comment(self::TYPE_COMMENT_BEFORE)->change();
            $table->dropIndex(['scope']);
            $table->dropColumn(['service_status', 'service_status_checked_at', 'scope']);
        });
    }
};
