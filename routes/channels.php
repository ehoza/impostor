<?php

use App\Models\Lobby;
use Illuminate\Support\Facades\Broadcast;

// Allow all clients to authenticate with private/presence channels
// In production, you should add proper authorization
Broadcast::channel('lobby.{code}', function ($user, $code) {
    $lobby = Lobby::where('code', strtoupper($code))->first();

    // Allow access if lobby exists and is waiting/playing
    // For simplicity, we're allowing all clients to connect
    // In production, check against session player
    return $lobby && in_array($lobby->status, ['waiting', 'playing']);
});

// You can add more specific channel authorizations here
// For example, to restrict to only players in the lobby:
// Broadcast::channel('lobby.{code}', function ($user, $code) {
//     $lobby = Lobby::where('code', strtoupper($code))->first();
//     $playerId = session('current_player_id');
//     return $lobby && $lobby->players()->where('id', $playerId)->exists();
// });
