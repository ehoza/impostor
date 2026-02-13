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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lobby_id')->constrained()->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('recipient_id')->nullable()->constrained('players')->onDelete('cascade');
            $table->text('content');
            $table->boolean('is_dm')->default(false);
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['lobby_id', 'created_at']);
            $table->index(['recipient_id', 'is_read']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
