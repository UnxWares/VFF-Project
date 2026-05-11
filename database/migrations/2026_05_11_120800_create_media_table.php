<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->morphs('mediable'); // mediable_type + mediable_id (indexés)
            $table->string('type')->default('photo')->comment('photo | plan | document | map');
            $table->string('file_path');
            $table->string('mime_type');
            $table->integer('file_size')->comment('Taille en octets');
            $table->string('title')->nullable();
            $table->text('caption')->nullable();
            $table->string('author')->nullable();
            $table->string('copyright')->nullable();
            $table->string('license')->default('CC-BY-SA-4.0');
            $table->date('captured_at')->nullable()->comment('Date de prise de vue/édition');
            $table->magellanPoint('location', srid: 4326)->nullable();
            $table->jsonb('exif_data')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('captured_at');
        });

        DB::statement('CREATE INDEX media_location_idx ON media USING GIST (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
