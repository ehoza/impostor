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
        Schema::table('players', function (Blueprint $table) {
            $table->boolean('has_voted_vote_now')->default(false);
            $table->boolean('has_voted_reroll')->default(false);
            $table->integer('turn_position')->nullable();
            $table->timestamp('last_active_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn([
                'has_voted_vote_now',
                'has_voted_reroll',
                'turn_position',
                'last_active_at',
            ]);
        });
    }
};
