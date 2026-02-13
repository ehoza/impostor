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
        if (Schema::hasTable('players')) {
            return;
        }

        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('lobby_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_host')->default(false);
            $table->boolean('is_impostor')->default(false);
            $table->boolean('is_eliminated')->default(false);
            $table->foreignId('word_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable();
            $table->integer('votes_received')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
