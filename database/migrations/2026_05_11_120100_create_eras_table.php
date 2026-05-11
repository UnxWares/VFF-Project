<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eras', function (Blueprint $table): void {
            $table->id();
            $table->smallInteger('year')->unique()->comment('1990, 2000, 2010, 2020, 2030');
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_current')->default(false)->comment('Une seule ère est `current` à la fois — 2030 actuellement');
            $table->date('editable_until')->nullable()->comment('Seul 2030 est éditable jusqu\'au 2029-12-31');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eras');
    }
};
