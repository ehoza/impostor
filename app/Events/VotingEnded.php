<?php

namespace App\Events;

use App\Models\Lobby;
use App\Models\Player;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class VotingEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Lobby $lobby,
        public ?Player $eliminatedPlayer,
        public ?string $gameResult
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('lobby.' . $this->lobby->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'voting.ended';
    }

    public function broadcastWith(): array
    {
        return [
            'eliminated_player' => $this->eliminatedPlayer?->only(['id', 'name', 'is_impostor']),
            'game_result' => $this->gameResult,
            'lobby_status' => $this->lobby->status,
        ];
    }
}
