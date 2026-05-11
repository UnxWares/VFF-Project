<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->comment('line | line_segment | station | material | line_event | media');
            $table->string('action')->comment('create | update | delete');
            $table->string('target_type')->nullable()->comment('Classe Eloquent ciblée');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->jsonb('payload')->comment('Diff/données proposées');
            $table->string('status')->default('pending')->comment('pending | approved | rejected | applied');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['target_type', 'target_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};
