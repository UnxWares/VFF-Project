<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('era_id')->nullable()->constrained()->nullOnDelete();
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['line_id', 'material_id', 'era_id'], 'line_materials_unique_assoc');
            $table->index('material_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_materials');
    }
};
