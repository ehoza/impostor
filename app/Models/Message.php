<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    /** @use HasFactory<\Database\Factories\MessageFactory> */
    use HasFactory;

    protected $fillable = [
        'lobby_id',
        'sender_id',
        'recipient_id',
        'content',
        'is_dm',
        'is_read',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_dm' => 'boolean',
            'is_read' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function lobby(): BelongsTo
    {
        return $this->belongsTo(Lobby::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'recipient_id');
    }

    public function scopePublic($query)
    {
        return $query->where('is_dm', false);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForPlayer($query, int $playerId)
    {
        return $query->where(function ($q) use ($playerId) {
            $q->where('is_dm', false)
                ->orWhere('recipient_id', $playerId)
                ->orWhere('sender_id', $playerId);
        });
    }
}
