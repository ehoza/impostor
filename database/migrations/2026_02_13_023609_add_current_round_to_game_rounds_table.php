<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_rounds', function (Blueprint $table) {
            $table->unsignedBigInteger('current_round')->default(0)->after('id');
        });

        if (DB::table('game_rounds')->count() === 0) {
            DB::table('game_rounds')->insert([
                'current_round' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_rounds', function (Blueprint $table) {
            $table->dropColumn('current_round');
        });
    }
};
