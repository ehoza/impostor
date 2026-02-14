<?php

namespace App\Services;

use App\Models\Word;
use Illuminate\Support\Facades\DB;

class WordSelectionService
{
    public function __construct(
        private readonly int $cooldownRounds = 100
    ) {}

    /**
     * Select a crew word for next game, excluding words used in the last N rounds.
     */
    public function selectWordForGame(?string $language = 'en'): ?Word
    {
        return DB::transaction(function () use ($language) {
            $round = $this->incrementRound();
            $excludedWordIds = $this->getRecentlyUsedWordIds($round);

            $word = Word::crewWords()
                ->with('impostorWord')
                ->whereNotNull('impostor_word_id')
                ->where('language', $language)
                ->when($excludedWordIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $excludedWordIds))
                ->inRandomOrder()
                ->first();

            if ($word) {
                $this->recordUsage($word->id, $round);
            }

            return $word;
        });
    }

    private function incrementRound(): int
    {
        $row = DB::table('game_rounds')->lockForUpdate()->first();

        if (! $row) {
            DB::table('game_rounds')->insert([
                'current_round' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 1;
        }

        $newRound = $row->current_round + 1;
        DB::table('game_rounds')->update([
            'current_round' => $newRound,
            'updated_at' => now(),
        ]);

        return $newRound;
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function getRecentlyUsedWordIds(int $currentRound): \Illuminate\Support\Collection
    {
        $minRound = max(1, $currentRound - $this->cooldownRounds);

        return DB::table('word_usage')
            ->whereBetween('round', [$minRound, $currentRound - 1])
            ->pluck('word_id');
    }

    private function recordUsage(int $wordId, int $round): void
    {
        DB::table('word_usage')->insert([
            'word_id' => $wordId,
            'round' => $round,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
