<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lobby extends Model
{
    /** @use HasFactory<\Database\Factories\LobbyFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'status',
        'game_result',
        'settings',
        'word_id',
        'current_turn_player_id',
        'turn_order',
        'current_turn_index',
        'vote_now_votes',
        'reroll_votes',
        'voting_phase',
        'last_eliminated_snapshot',
        'turn_started_at',
        'impostor_wins',
        'crew_wins',
        'current_round',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'turn_order' => 'array',
            'vote_now_votes' => 'array',
            'reroll_votes' => 'array',
            'voting_phase' => 'boolean',
            'last_eliminated_snapshot' => 'array',
            'turn_started_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }

    public function currentTurnPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'current_turn_player_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['waiting', 'playing']);
    }

    public function getActivePlayersAttribute(): int
    {
        return $this->players()->where('is_eliminated', false)->count();
    }

    public function hasPlayer(string $sessionId): bool
    {
        return $this->players()->where('session_id', $sessionId)->exists();
    }
}
