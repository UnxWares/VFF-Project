<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->string('type')->comment('opening | electrification | closure | dismantling | reopening | modernization | accident');
            $table->date('event_date');
            $table->text('description');
            $table->string('source')->nullable()->comment('Citation/référence de source');
            $table->timestamps();

            $table->index(['line_id', 'event_date']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_events');
    }
};
