<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    /** @use HasFactory<\Database\Factories\PlayerFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'lobby_id',
        'is_host',
        'is_impostor',
        'is_eliminated',
        'word_id',
        'session_id',
        'votes_received',
        'has_voted_vote_now',
        'has_voted_reroll',
        'turn_position',
        'last_active_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_host' => 'boolean',
            'is_impostor' => 'boolean',
            'is_eliminated' => 'boolean',
            'votes_received' => 'integer',
            'has_voted_vote_now' => 'boolean',
            'has_voted_reroll' => 'boolean',
            'turn_position' => 'integer',
            'last_active_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function lobby()
    {
        return $this->belongsTo(Lobby::class);
    }

    public function word()
    {
        return $this->belongsTo(Word::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_eliminated', false);
    }

    public function scopeNotEliminated($query)
    {
        return $query->where('is_eliminated', false);
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'recipient_id');
    }

    public function unreadDMs()
    {
        return $this->receivedMessages()
            ->where('is_dm', true)
            ->where('is_read', false);
    }
}
