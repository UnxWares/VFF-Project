<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contribution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('approved_at')->useCurrent();
            $table->text('notes')->nullable();
            // Un validateur n'approuve qu'une fois par contribution.
            $table->unique(['contribution_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_approvals');
    }
};
