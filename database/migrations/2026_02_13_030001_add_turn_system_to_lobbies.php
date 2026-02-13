<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lobbies', function (Blueprint $table) {
            $table->foreignId('current_turn_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->json('turn_order')->nullable();
            $table->integer('current_turn_index')->default(0);
            $table->json('vote_now_votes')->nullable();
            $table->json('reroll_votes')->nullable();
            $table->timestamp('turn_started_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lobbies', function (Blueprint $table) {
            $table->dropForeign(['current_turn_player_id']);
            $table->dropColumn([
                'current_turn_player_id',
                'turn_order',
                'current_turn_index',
                'vote_now_votes',
                'reroll_votes',
                'turn_started_at',
            ]);
        });
    }
};
