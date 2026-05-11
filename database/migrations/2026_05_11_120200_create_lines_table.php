<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lines', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->jsonb('alt_names')->nullable()->comment('Variantes/anciennes appellations');
            $table->string('code')->nullable()->index()->comment('Code administratif RFF/SNCF (ex: 420 000)');
            $table->string('type')->default('passengers')->comment('passengers | freight | mixed');
            $table->string('historical_operator')->nullable()->comment('Cie de l\'Ouest, État, SNCF, …');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lines');
    }
};
