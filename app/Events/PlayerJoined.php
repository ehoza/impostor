<?php

namespace App\Events;

use App\Models\Lobby;
use App\Models\Player;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class PlayerJoined implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Lobby $lobby,
        public Player $player
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('lobby.' . $this->lobby->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'player.joined';
    }

    public function broadcastWith(): array
    {
        return [
            'player' => [
                'id' => $this->player->id,
                'name' => $this->player->name,
                'is_host' => $this->player->is_host,
            ],
            'player_count' => $this->lobby->players()->count(),
        ];
    }
}
