<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Lobby;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoRestartGame implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $lobbyId) {}

    public function handle(): void
    {
        $lobby = Lobby::find($this->lobbyId);

        if (! $lobby) {
            return;
        }

        // Only restart if game is still finished (hasn't been manually restarted)
        if ($lobby->status === 'finished') {
            $controller = app(\App\Http\Controllers\GameController::class);
            $controller->restartRound($lobby);
        }
    }
}
