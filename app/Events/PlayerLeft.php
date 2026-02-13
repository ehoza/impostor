<?php

namespace App\Events;

use App\Models\Lobby;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class PlayerLeft implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Lobby $lobby,
        public int $playerId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('lobby.' . $this->lobby->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'player.left';
    }

    public function broadcastWith(): array
    {
        return [
            'player_id' => $this->playerId,
            'player_count' => $this->lobby->players()->count(),
        ];
    }
}
