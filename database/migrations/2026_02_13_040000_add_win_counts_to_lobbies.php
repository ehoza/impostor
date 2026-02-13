<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lobbies', function (Blueprint $table) {
            $table->integer('impostor_wins')->default(0);
            $table->integer('crew_wins')->default(0);
            $table->integer('current_round')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('lobbies', function (Blueprint $table) {
            $table->dropColumn(['impostor_wins', 'crew_wins', 'current_round']);
        });
    }
};
