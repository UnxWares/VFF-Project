<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->jsonb('alt_names')->nullable();
            $table->string('type')->default('gare')->comment('gare | halte | point_arret | passage_niveau | embranchement');
            $table->magellanPoint('geom', srid: 4326);
            $table->string('city')->nullable();
            $table->string('department_code', 3)->nullable()->comment('Code INSEE');
            $table->date('opened_at')->nullable();
            $table->date('closed_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('city');
            $table->index('department_code');
        });

        DB::statement('CREATE INDEX stations_geom_idx ON stations USING GIST (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stations');
    }
};
