<?php

namespace App\Events;

use App\Models\Lobby;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class SettingsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Lobby $lobby,
        public array $settings
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('lobby.' . $this->lobby->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'settings.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'settings' => $this->settings,
        ];
    }
}
