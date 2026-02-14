<?php

use App\Models\Lobby;
use App\Models\Player;
use App\Models\Word;
use Illuminate\Support\Str;

test('can create a lobby', function () {
    $response = $this->post(route('lobby.create'), [
        'player_name' => 'Test Player',
        'avatar' => 'portrait-no-border1.png',
        'lobby_name' => 'Test Lobby',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('lobbies', [
        'name' => 'Test Lobby',
        'status' => 'waiting',
    ]);
    $this->assertDatabaseHas('players', [
        'name' => 'Test Player',
        'is_host' => true,
    ]);
});

test('lobby status endpoint returns status without session', function () {
    Lobby::factory()->create(['code' => 'STATUS', 'status' => 'waiting']);

    $response = $this->getJson(route('lobby.status', 'STATUS'));

    $response->assertOk()->assertJson(['status' => 'waiting']);

    Lobby::where('code', 'STATUS')->update(['status' => 'playing']);
    $response2 = $this->getJson(route('lobby.status', 'STATUS'));
    $response2->assertOk()->assertJson(['status' => 'playing']);
});

test('lobby status returns 404 for unknown code', function () {
    $this->getJson(route('lobby.status', 'NONEXIST'))
        ->assertNotFound();
});

test('can join an existing lobby', function () {
    $lobby = Lobby::factory()->create(['code' => 'TEST123', 'status' => 'waiting']);

    $response = $this->post(route('lobby.join', 'TEST123'), [
        'player_name' => 'Joining Player',
        'avatar' => 'portrait-no-border2.png',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('players', [
        'name' => 'Joining Player',
        'lobby_id' => $lobby->id,
        'is_host' => false,
    ]);
});

test('cannot join a full lobby', function () {
    $lobby = Lobby::factory()->create([
        'code' => 'FULL12',
        'status' => 'waiting',
        'settings' => ['max_players' => 2],
    ]);

    Player::factory()->create(['lobby_id' => $lobby->id, 'name' => 'Player 1']);
    Player::factory()->create(['lobby_id' => $lobby->id, 'name' => 'Player 2']);

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ])->postJson(route('lobby.join', 'FULL12'), [
        'player_name' => 'Third Player',
        'avatar' => 'portrait-no-border3.png',
    ]);

    $response->assertStatus(422);
});

test('cannot join a game in progress', function () {
    $lobby = Lobby::factory()->create(['code' => 'PLAYIN', 'status' => 'playing']);

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ])->postJson(route('lobby.join', 'PLAYIN'), [
        'player_name' => 'Late Player',
        'avatar' => 'portrait-no-border4.png',
    ]);

    $response->assertStatus(422);
});

test('host can start game with minimum players', function () {
    $lobby = Lobby::factory()->create(['code' => 'START1', 'status' => 'waiting']);
    $host = Player::factory()->create(['lobby_id' => $lobby->id, 'is_host' => true, 'session_id' => 'host-session']);
    Player::factory()->create(['lobby_id' => $lobby->id, 'name' => 'Player 2']);
    Player::factory()->create(['lobby_id' => $lobby->id, 'name' => 'Player 3']);

    $word = Word::factory()->create(['is_impostor_word' => false]);
    $impostorWord = Word::factory()->create(['is_impostor_word' => true]);
    $word->update(['impostor_word_id' => $impostorWord->id]);

    $this->withSession(['current_player_id' => $host->id])
        ->post(route('game.start', 'START1'))
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('lobbies', [
        'id' => $lobby->id,
        'status' => 'playing',
    ]);
});

test('cannot start game without enough players', function () {
    $lobby = Lobby::factory()->create(['code' => 'ALONE1', 'status' => 'waiting']);
    $host = Player::factory()->create(['lobby_id' => $lobby->id, 'is_host' => true, 'session_id' => 'host-session']);

    $this->withSession(['current_player_id' => $host->id])
        ->post(route('game.start', 'ALONE1'))
        ->assertStatus(400);
});

test('cannot start game with only two players', function () {
    $lobby = Lobby::factory()->create(['code' => 'TWO12', 'status' => 'waiting']);
    $host = Player::factory()->create(['lobby_id' => $lobby->id, 'is_host' => true, 'session_id' => 'host-session']);
    Player::factory()->create(['lobby_id' => $lobby->id, 'name' => 'Player 2']);

    $word = Word::factory()->create(['is_impostor_word' => false]);
    $impostorWord = Word::factory()->create(['is_impostor_word' => true]);
    $word->update(['impostor_word_id' => $impostorWord->id]);

    $response = $this->withSession(['current_player_id' => $host->id])
        ->postJson(route('game.start', 'TWO12'));

    $response->assertStatus(400)
        ->assertJson(['error' => 'Need at least 3 players to start']);
});

test('only host can start game', function () {
    $lobby = Lobby::factory()->create(['code' => 'NOHOST', 'status' => 'waiting']);
    $host = Player::factory()->create(['lobby_id' => $lobby->id, 'is_host' => true]);
    $player = Player::factory()->create(['lobby_id' => $lobby->id, 'is_host' => false, 'session_id' => 'player-session']);

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ])->withSession(['current_player_id' => $player->id])
        ->postJson(route('game.start', 'NOHOST'));

    // The controller uses ValidationException for host checks (422)
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['authorization']);
});

test('impostor is randomly selected when game starts', function () {
    $lobby = Lobby::factory()->create(['code' => 'IMPOST', 'status' => 'waiting']);
    $host = Player::factory()->create(['lobby_id' => $lobby->id, 'is_host' => true, 'session_id' => 'host-session']);
    Player::factory()->create(['lobby_id' => $lobby->id, 'name' => 'Player 2']);
    Player::factory()->create(['lobby_id' => $lobby->id, 'name' => 'Player 3']);

    $word = Word::factory()->create(['is_impostor_word' => false]);
    $impostorWord = Word::factory()->create(['is_impostor_word' => true]);
    $word->update(['impostor_word_id' => $impostorWord->id]);

    $this->withSession(['current_player_id' => $host->id])
        ->post(route('game.start', 'IMPOST'))
        ->assertJson(['success' => true]);

    $impostorCount = Player::where('lobby_id', $lobby->id)->where('is_impostor', true)->count();
    $crewCount = Player::where('lobby_id', $lobby->id)->where('is_impostor', false)->count();

    expect($impostorCount)->toBe(1);
    expect($crewCount)->toBe(2);
});

test('impostor gets different word than crew', function () {
    $lobby = Lobby::factory()->create(['code' => 'WORDS1', 'status' => 'waiting']);
    $host = Player::factory()->create(['lobby_id' => $lobby->id, 'is_host' => true, 'session_id' => 'host-session']);
    Player::factory()->create(['lobby_id' => $lobby->id, 'name' => 'Player 2']);
    Player::factory()->create(['lobby_id' => $lobby->id, 'name' => 'Player 3']);

    $crewWord = Word::factory()->create(['word' => 'Măr', 'is_impostor_word' => false]);
    $impostorWord = Word::factory()->create(['word' => 'Pear', 'is_impostor_word' => true]);
    $crewWord->update(['impostor_word_id' => $impostorWord->id]);

    $this->withSession(['current_player_id' => $host->id])
        ->post(route('game.start', 'WORDS1'))
        ->assertJson(['success' => true]);

    $host->refresh();
    $players = Player::where('lobby_id', $lobby->id)->get();

    $impostor = $players->firstWhere('is_impostor', true);
    $crew = $players->where('is_impostor', false);

    expect($impostor->word_id)->toBe($impostorWord->id);
    foreach ($crew as $crewMember) {
        expect($crewMember->word_id)->toBe($crewWord->id);
    }
});

test('voting eliminates most voted player', function () {
    $lobby = Lobby::factory()->create(['code' => 'VOTE01', 'status' => 'playing']);
    $host = Player::factory()->create(['lobby_id' => $lobby->id, 'session_id' => 's1', 'name' => 'P1', 'is_host' => true]);
    $player2 = Player::factory()->create(['lobby_id' => $lobby->id, 'session_id' => 's2', 'name' => 'P2']);
    $player3 = Player::factory()->create(['lobby_id' => $lobby->id, 'session_id' => 's3', 'name' => 'P3']);

    $this->withSession(['current_player_id' => $host->id])
        ->post(route('game.vote', 'VOTE01'), ['target_player_id' => $player2->id])
        ->assertJson(['success' => true]);

    $this->withSession(['current_player_id' => $player3->id])
        ->post(route('game.vote', 'VOTE01'), ['target_player_id' => $player2->id])
        ->assertJson(['success' => true]);

    $player2->refresh();
    expect($player2->is_eliminated)->toBeFalse();

    $this->withSession(['current_player_id' => $host->id])
        ->post(route('game.end-voting', 'VOTE01'));

    $player2->refresh();
    expect($player2->is_eliminated)->toBeTrue();
});

test('crew wins round when impostor eliminated and round restarts', function () {
    $crewWord = Word::factory()->create(['is_impostor_word' => false]);
    $impostorWord = Word::factory()->create(['is_impostor_word' => true]);
    $crewWord->update(['impostor_word_id' => $impostorWord->id]);

    $lobby = Lobby::factory()->create(['code' => 'CREWWN', 'status' => 'playing']);
    $impostor = Player::factory()->create(['lobby_id' => $lobby->id, 'is_impostor' => true, 'session_id' => 'imp']);
    $crew = Player::factory()->create(['lobby_id' => $lobby->id, 'is_impostor' => false, 'is_host' => true, 'session_id' => 'host']);

    $this->withSession(['current_player_id' => $impostor->id])
        ->post(route('game.vote', 'CREWWN'), ['target_player_id' => $impostor->id]);

    $response = $this->withSession(['current_player_id' => $crew->id])
        ->post(route('game.end-voting', 'CREWWN'));

    $response->assertJson(['was_impostor' => true]);

    $lobby->refresh();
    expect($lobby->crew_wins)->toBe(1);
});

test('impostor wins when equal to crew', function () {
    $lobby = Lobby::factory()->create(['code' => 'IMPWIN', 'status' => 'playing']);
    Player::factory()->create(['lobby_id' => $lobby->id, 'is_impostor' => true, 'session_id' => 'imp']);
    $crew = Player::factory()->create(['lobby_id' => $lobby->id, 'is_impostor' => false, 'session_id' => 'host', 'is_host' => true]);

    // Vote for crew
    $this->withSession(['current_player_id' => $crew->id])
        ->post(route('game.vote', 'IMPWIN'), ['target_player_id' => $crew->id]);

    $response = $this->withSession(['current_player_id' => $crew->id])
        ->post(route('game.end-voting', 'IMPWIN'));

    $response->assertJson(['game_result' => 'impostor_wins']);

    $lobby->refresh();
    expect($lobby->status)->toBe('finished');
});

test('lobby codes are unique', function () {
    $codes = [];
    for ($i = 0; $i < 10; $i++) {
        $code = strtoupper(Str::random(6));
        $codes[] = $code;
        Lobby::factory()->create(['code' => $code]);
    }

    expect($codes)->toHaveCount(count(array_unique($codes)));
});

test('host can update lobby settings', function () {
    $lobby = Lobby::factory()->create(['code' => 'SETTNG', 'status' => 'waiting']);
    $host = Player::factory()->create(['lobby_id' => $lobby->id, 'is_host' => true, 'session_id' => 'host']);

    $response = $this->withHeaders([
        'Accept' => 'application/json',
    ])->withSession(['current_player_id' => $host->id])
        ->postJson(route('lobby.settings', 'SETTNG'), [
            'settings' => [
                'impostor_count' => 2,
                'max_players' => 15,
                'discussion_time' => 90,
            ],
        ]);

    $response->assertJson(['success' => true]);

    $lobby->refresh();
    expect($lobby->settings['impostor_count'])->toBe(2);
    expect($lobby->settings['max_players'])->toBe(15);
    expect($lobby->settings['discussion_time'])->toBe(90);
});

test('non-host cannot update settings', function () {
    $lobby = Lobby::factory()->create(['code' => 'NOAUTH', 'status' => 'waiting']);
    $host = Player::factory()->create(['lobby_id' => $lobby->id, 'is_host' => true]);
    $player = Player::factory()->create(['lobby_id' => $lobby->id, 'is_host' => false, 'session_id' => 'player']);

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ])->withSession(['current_player_id' => $player->id])
        ->postJson(route('lobby.settings', 'NOAUTH'), [
            'settings' => ['impostor_count' => 5],
        ]);

    // The controller uses ValidationException for host checks (422)
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['authorization']);
});
