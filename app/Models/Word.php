<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Word extends Model
{
    /** @use HasFactory<\Database\Factories\WordFactory> */
    use HasFactory;

    protected $fillable = [
        'word',
        'category',
        'definition',
        'impostor_word_id',
        'is_impostor_word',
        'difficulty',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_impostor_word' => 'boolean',
            'difficulty' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function impostorWord()
    {
        return $this->belongsTo(Word::class, 'impostor_word_id');
    }

    public function alternativeWords()
    {
        return $this->hasMany(Word::class, 'impostor_word_id');
    }

    public function scopeCrewWords($query)
    {
        return $query->where('is_impostor_word', false);
    }

    public function scopeImpostorWords($query)
    {
        return $query->where('is_impostor_word', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByDifficulty($query, int $level)
    {
        return $query->where('difficulty', '<=', $level);
    }
}
