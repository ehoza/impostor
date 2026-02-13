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
        if (Schema::hasTable('lobbies')) {
            return;
        }

        Schema::create('lobbies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name')->nullable();
            $table->enum('status', ['waiting', 'playing', 'finished'])->default('waiting');
            $table->json('settings')->nullable();
            $table->foreignId('word_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lobbies');
    }
};
