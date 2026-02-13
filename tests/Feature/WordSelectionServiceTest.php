<?php

use App\Models\Word;
use App\Services\WordSelectionService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('word_usage')->truncate();
    DB::table('game_rounds')->update(['current_round' => 0]);
});

test('selects a word when none have been used', function () {
    $crewWord = Word::factory()->create(['is_impostor_word' => false]);
    $impostorWord = Word::factory()->create(['is_impostor_word' => true]);
    $crewWord->update(['impostor_word_id' => $impostorWord->id]);

    $service = new WordSelectionService(cooldownRounds: 1);
    $word = $service->selectWordForGame();

    expect($word)->not->toBeNull()
        ->and($word->id)->toBe($crewWord->id)
        ->and(DB::table('word_usage')->count())->toBe(1);
});

test('excludes recently used words from selection', function () {
    $crewWord1 = Word::factory()->create(['is_impostor_word' => false]);
    $impostorWord1 = Word::factory()->create(['is_impostor_word' => true]);
    $crewWord1->update(['impostor_word_id' => $impostorWord1->id]);

    $crewWord2 = Word::factory()->create(['is_impostor_word' => false]);
    $impostorWord2 = Word::factory()->create(['is_impostor_word' => true]);
    $crewWord2->update(['impostor_word_id' => $impostorWord2->id]);

    $service = new WordSelectionService(cooldownRounds: 1);

    $firstWord = $service->selectWordForGame();
    expect($firstWord)->not->toBeNull();

    $secondWord = $service->selectWordForGame();
    expect($secondWord)->not->toBeNull()
        ->and($secondWord->id)->not->toBe($firstWord->id);
});

test('allows a word to be selected again after cooldown rounds', function () {
    $crewWord1 = Word::factory()->create(['is_impostor_word' => false]);
    $impostorWord1 = Word::factory()->create(['is_impostor_word' => true]);
    $crewWord1->update(['impostor_word_id' => $impostorWord1->id]);

    $crewWord2 = Word::factory()->create(['is_impostor_word' => false]);
    $impostorWord2 = Word::factory()->create(['is_impostor_word' => true]);
    $crewWord2->update(['impostor_word_id' => $impostorWord2->id]);

    $service = new WordSelectionService(cooldownRounds: 1);

    $firstWord = $service->selectWordForGame();
    $secondWord = $service->selectWordForGame();
    $thirdWord = $service->selectWordForGame();

    expect($wordIds = [$firstWord->id, $secondWord->id, $thirdWord->id])
        ->toContain($firstWord->id)
        ->and($thirdWord->id)->toBe($firstWord->id);
});
