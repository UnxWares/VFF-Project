<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_eras', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('era_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->comment('active | tourist | no_commercial | declassified');
            $table->text('note')->nullable()->comment('Précision : ex. servie par ligne X mais plus par Y');
            $table->timestamps();

            $table->unique(['station_id', 'era_id']);
            $table->index(['era_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_eras');
    }
};
