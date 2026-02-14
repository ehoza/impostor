<?php

namespace App\Events;

use App\Models\Lobby;
use App\Models\Word;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class GameStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Lobby $lobby,
        public Word $word
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('lobby.'.$this->lobby->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'game.started';
    }

    public function broadcastWith(): array
    {
        return [
            'lobby_status' => $this->lobby->status,
            'word_category' => $this->word->category,
            'difficulty' => $this->word->difficulty,
        ];
    }
}
