<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name')->comment('CC 7100, TGV Atlantique, Z2, X73500, …');
            $table->string('type')->comment('locomotive_electrique | locomotive_diesel | locomotive_vapeur | automotrice | autorail | rame_tgv | voiture | wagon');
            $table->string('builder')->nullable()->comment('Alstom, ANF, Siemens, …');
            $table->smallInteger('in_service_from')->nullable()->comment('Année (1955, 2010, …)');
            $table->smallInteger('in_service_to')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
