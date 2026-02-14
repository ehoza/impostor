<?php

namespace App\Events;

use App\Models\Lobby;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class PlayerVoted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Lobby $lobby,
        public int $voterId,
        public int $targetId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('lobby.'.$this->lobby->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'player.voted';
    }

    public function broadcastWith(): array
    {
        return [
            'voter_id' => $this->voterId,
            'target_id' => $this->targetId,
        ];
    }
}
