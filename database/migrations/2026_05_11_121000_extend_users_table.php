<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('visitor')->after('password')
                ->comment('visitor | contributor | validator | admin');
            $table->integer('reputation')->default(0)->after('role');
            $table->text('bio')->nullable()->after('reputation');
            $table->string('avatar_path')->nullable()->after('bio');
            $table->string('locale_preference', 5)->default('fr')->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'reputation', 'bio', 'avatar_path', 'locale_preference']);
        });
    }
};
