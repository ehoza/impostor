<?php

namespace App\Http\Controllers;

use App\Models\Lobby;
use App\Models\Message;
use App\Models\Player;
use App\Models\Word;
use App\Services\WordSelectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class GameController extends Controller
{
    /**
     * Create a new lobby.
     */
    public function createLobby(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'player_name' => 'required|string|max:30',
            'lobby_name' => 'nullable|string|max:50',
            'settings' => 'nullable|array',
        ]);

        // Generate unique lobby code
        $code = $this->generateUniqueLobbyCode();

        $settings = array_merge($this->getDefaultSettings(), $validated['settings'] ?? []);

        $lobby = Lobby::create([
            'code' => $code,
            'name' => $validated['lobby_name'],
            'status' => 'waiting',
            'settings' => $settings,
            'vote_now_votes' => [],
            'reroll_votes' => [],
        ]);

        $player = $this->createPlayer($lobby, $validated['player_name'], true);

        // Cache session
        $this->cachePlayerSession($player->id, $code);

        return redirect()->route('lobby.show', $code);
    }

    /**
     * Join an existing lobby.
     */
    public function joinLobby(Request $request, string $code): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'player_name' => 'required|string|max:30',
        ]);

        $lobby = Lobby::where('code', strtoupper($code))
            ->whereIn('status', ['waiting', 'playing'])
            ->first();

        if (! $lobby) {
            throw ValidationException::withMessages([
                'code' => 'Lobby not found or already closed.',
            ]);
        }

        if ($lobby->status === 'playing') {
            throw ValidationException::withMessages([
                'code' => 'Game already in progress.',
            ]);
        }

        // Check max players
        $maxPlayers = $lobby->settings['max_players'] ?? 10;
        if ($lobby->players()->count() >= $maxPlayers) {
            throw ValidationException::withMessages([
                'code' => 'Lobby is full.',
            ]);
        }

        $player = $this->createPlayer($lobby, $validated['player_name'], false);
        $this->cachePlayerSession($player->id, $code);

        // Broadcast to other players
        broadcast(new \App\Events\PlayerJoined($lobby, $player))->toOthers();

        return redirect()->route('lobby.show', $code);
    }

    /**
     * Show lobby page.
     */
    public function showLobby(string $code): \Inertia\Response
    {
        $lobby = Lobby::where('code', strtoupper($code))
            ->with(['players' => function ($query) {
                $query->orderBy('created_at');
            }])
            ->firstOrFail();

        $currentPlayerId = session('current_player_id');

        $currentPlayer = $lobby->players()->find($currentPlayerId);

        // Convert to array and add computed properties
        $playersData = $lobby->players->map(function ($player) {
            return [
                'id' => $player->id,
                'name' => $player->name,
                'is_host' => $player->is_host,
                'is_ready' => $player->is_ready ?? false,
            ];
        });

        return Inertia::render('Lobby', [
            'lobby' => [
                'code' => $lobby->code,
                'name' => $lobby->name,
                'status' => $lobby->status,
                'settings' => $lobby->settings,
                'player_count' => $lobby->players->count(),
            ],
            'players' => $playersData,
            'current_player' => $currentPlayer ? [
                'id' => $currentPlayer->id,
                'name' => $currentPlayer->name,
                'is_host' => $currentPlayer->is_host,
            ] : null,
        ]);
    }

    /**
     * Show game page.
     */
    public function showGame(string $code): \Inertia\Response
    {
        $lobby = Lobby::where('code', strtoupper($code))
            ->with(['players', 'currentTurnPlayer'])
            ->firstOrFail();

        $currentPlayerId = session('current_player_id');
        $currentPlayer = $lobby->players()->find($currentPlayerId);

        if (! $currentPlayer) {
            return redirect()->route('home');
        }

        // Get unread DM count
        $unreadDMs = Message::where('recipient_id', $currentPlayerId)
            ->where('is_dm', true)
            ->where('is_read', false)
            ->count();

        // Get turn order with player info
        $turnOrder = [];
        if ($lobby->turn_order) {
            $players = $lobby->players->keyBy('id');
            $turnOrder = collect($lobby->turn_order)->map(function ($playerId) use ($players) {
                $player = $players->get($playerId);

                return $player ? [
                    'id' => $player->id,
                    'name' => $player->name,
                    'is_eliminated' => $player->is_eliminated,
                ] : null;
            })->filter()->values()->all();
        }

        return Inertia::render('Game', [
            'code' => $lobby->code,
            'current_player' => [
                'id' => $currentPlayer->id,
                'name' => $currentPlayer->name,
                'is_host' => $currentPlayer->is_host,
            ],
            'lobby' => [
                'code' => $lobby->code,
                'current_turn_player_id' => $lobby->current_turn_player_id,
                'turn_order' => $turnOrder,
                'current_turn_index' => $lobby->current_turn_index,
                'impostor_wins' => $lobby->impostor_wins,
                'crew_wins' => $lobby->crew_wins,
                'current_round' => $lobby->current_round,
            ],
            'unread_dm_count' => $unreadDMs,
        ]);
    }

    /**
     * Start the game.
     */
    public function startGame(Request $request, string $code): \Illuminate\Http\JsonResponse
    {
        $lobby = Lobby::where('code', strtoupper($code))
            ->with('players')
            ->firstOrFail();

        $this->validateHost($lobby);

        if ($lobby->players->count() < 2) {
            return response()->json(['error' => 'Need at least 2 players'], 400);
        }

        $word = app(WordSelectionService::class)->selectWordForGame();

        if (! $word) {
            return response()->json(['error' => 'No words available'], 500);
        }

        $playerIds = $lobby->players->pluck('id')->shuffle()->values()->all();
        $turnOrder = $playerIds;

        $eligibleForImpostor = $lobby->players->filter(fn ($p) => ($p->impostor_streak ?? 0) < 3);
        $impostorPool = $eligibleForImpostor->isEmpty() ? $lobby->players : $eligibleForImpostor;
        $impostorCount = min($lobby->settings['impostor_count'] ?? 1, $impostorPool->count());
        $impostorIds = $impostorPool->pluck('id')->shuffle()->take($impostorCount)->all();

        foreach ($lobby->players as $player) {
            $isImpostor = in_array($player->id, $impostorIds);
            $player->update([
                'is_impostor' => $isImpostor,
                'word_id' => $isImpostor ? $word->impostor_word_id : $word->id,
                'is_eliminated' => false,
                'impostor_streak' => $isImpostor ? 1 : 0,
                'has_voted_vote_now' => false,
                'has_voted_reroll' => false,
                'turn_position' => array_search($player->id, $turnOrder),
            ]);
        }

        $lobby->update([
            'status' => 'playing',
            'word_id' => $word->id,
            'turn_order' => $turnOrder,
            'current_turn_index' => 0,
            'current_turn_player_id' => $turnOrder[0] ?? null,
            'turn_started_at' => now(),
            'vote_now_votes' => [],
            'reroll_votes' => [],
            'current_round' => 1,
        ]);

        // Broadcast game start
        broadcast(new \App\Events\GameStarted($lobby, $word));

        return response()->json(['success' => true]);
    }

    /**
     * Get game state (for polling/fallback).
     */
    public function getGameState(string $code): \Illuminate\Http\JsonResponse
    {
        $lobby = Lobby::where('code', strtoupper($code))
            ->with(['players' => function ($query) {
                $query->select('id', 'name', 'lobby_id', 'is_host', 'is_eliminated', 'turn_position');
            }])
            ->firstOrFail();

        $currentPlayerId = session('current_player_id');
        $currentPlayer = $lobby->players()->find($currentPlayerId);

        if (! $currentPlayer) {
            return response()->json(['error' => 'Not in lobby'], 403);
        }

        $playerWord = $currentPlayer->word;
        $isImpostor = $currentPlayer->is_impostor;

        $players = $lobby->players->map(function ($player) {
            return [
                'id' => $player->id,
                'name' => $player->name,
                'is_host' => $player->is_host,
                'is_eliminated' => $player->is_eliminated,
                'turn_position' => $player->turn_position,
            ];
        });

        // Calculate vote now progress
        $activePlayers = $lobby->players->where('is_eliminated', false)->count();
        $voteNowCount = count($lobby->vote_now_votes ?? []);
        $voteNowThreshold = ceil($activePlayers * 0.7);
        $voteNowProgress = $activePlayers > 0 ? ($voteNowCount / $voteNowThreshold) * 100 : 0;

        $rerollCount = count($lobby->reroll_votes ?? []);
        $rerollThreshold = ceil($activePlayers * 0.7);
        $rerollProgress = $activePlayers > 0 ? ($rerollCount / $rerollThreshold) * 100 : 0;

        $voteKey = "lobby_{$lobby->id}_votes";
        $eliminationVotes = Cache::get($voteKey, []);
        $eliminationVoteHasVoted = isset($eliminationVotes[$currentPlayerId]);

        return response()->json([
            'status' => $lobby->status,
            'game_result' => $lobby->game_result,
            'word' => $playerWord ? $playerWord->word : null,
            'is_impostor' => $isImpostor,
            'word_category' => $playerWord?->category,
            'players' => $players,
            'current_turn_player_id' => $lobby->current_turn_player_id,
            'current_turn_index' => $lobby->current_turn_index,
            'turn_order' => $lobby->turn_order,
            'turn_started_at' => $lobby->turn_started_at,
            'current_round' => $lobby->current_round,
            'impostor_wins' => $lobby->impostor_wins,
            'crew_wins' => $lobby->crew_wins,
            'vote_now' => [
                'count' => $voteNowCount,
                'threshold' => $voteNowThreshold,
                'progress' => min($voteNowProgress, 100),
                'has_voted' => in_array($currentPlayerId, $lobby->vote_now_votes ?? []),
            ],
            'reroll' => [
                'count' => $rerollCount,
                'threshold' => $rerollThreshold,
                'progress' => min($rerollProgress, 100),
                'has_voted' => in_array($currentPlayerId, $lobby->reroll_votes ?? []),
            ],
            'elimination_vote_has_voted' => $eliminationVoteHasVoted,
        ]);
    }

    /**
     * Vote to eliminate a player. One vote per player per voting phase (locked in).
     * Pass target_player_id: null to skip voting.
     */
    public function votePlayer(Request $request, string $code): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'target_player_id' => 'nullable|exists:players,id',
        ]);

        $lobby = Lobby::where('code', strtoupper($code))->firstOrFail();
        $currentPlayerId = session('current_player_id');

        $currentPlayer = $lobby->players()->find($currentPlayerId);
        if (! $currentPlayer || $currentPlayer->is_eliminated) {
            return response()->json(['error' => 'Cannot vote'], 403);
        }

        $voteKey = "lobby_{$lobby->id}_votes";
        $votes = Cache::get($voteKey, []);

        if (isset($votes[$currentPlayerId])) {
            return response()->json(['error' => 'Vote already locked in'], 400);
        }

        $votes[$currentPlayerId] = $validated['target_player_id'];
        Cache::put($voteKey, $votes, now()->addMinutes(10));

        broadcast(new \App\Events\PlayerVoted($lobby, $currentPlayer->id, $validated['target_player_id']));

        return response()->json(['success' => true]);
    }

    /**
     * End voting phase and eliminate player (or skip if no votes).
     */
    public function endVoting(Request $request, string $code): \Illuminate\Http\JsonResponse
    {
        $lobby = Lobby::where('code', strtoupper($code))->with('players')->firstOrFail();
        $this->validateHost($lobby);

        $voteKey = "lobby_{$lobby->id}_votes";
        $votes = Cache::get($voteKey, []);

        $eliminationVotes = array_filter($votes, fn ($targetId) => $targetId !== null);
        Cache::forget($voteKey);

        $this->resetVotePhase($lobby);

        if (empty($eliminationVotes)) {
            $this->advanceTurn($lobby);
            broadcast(new \App\Events\VotingEnded($lobby, null, null));

            return response()->json([
                'success' => true,
                'eliminated' => null,
                'was_impostor' => null,
                'game_result' => null,
                'skipped' => true,
            ]);
        }

        $voteCounts = array_count_values($eliminationVotes);
        arsort($voteCounts);
        $mostVotedId = (int) array_key_first($voteCounts);

        $eliminatedPlayer = $lobby->players()->find($mostVotedId);
        if (! $eliminatedPlayer) {
            $this->advanceTurn($lobby);
            broadcast(new \App\Events\VotingEnded($lobby, null, null));

            return response()->json([
                'success' => true,
                'eliminated' => null,
                'was_impostor' => null,
                'game_result' => null,
            ]);
        }

        $eliminatedPlayer->update(['is_eliminated' => true]);

        $impostorsRemaining = $lobby->players()->where('is_impostor', true)->where('is_eliminated', false)->count();
        $crewRemaining = $lobby->players()->where('is_impostor', false)->where('is_eliminated', false)->count();

        if ($impostorsRemaining >= $crewRemaining) {
            $lobby->update([
                'status' => 'finished',
                'game_result' => 'impostor_wins',
                'impostor_wins' => $lobby->impostor_wins + 1,
            ]);
            broadcast(new \App\Events\VotingEnded($lobby, $eliminatedPlayer, 'impostor_wins'));

            return response()->json([
                'success' => true,
                'eliminated' => [
                    'id' => $eliminatedPlayer->id,
                    'name' => $eliminatedPlayer->name,
                    'is_impostor' => false,
                ],
                'was_impostor' => false,
                'game_result' => 'impostor_wins',
            ]);
        }

        if ($eliminatedPlayer->is_impostor) {
            $restarted = $this->restartRound($lobby);
            $lobby->update(['crew_wins' => $lobby->crew_wins + 1]);

            if (! $restarted) {
                $lobby->update([
                    'status' => 'finished',
                    'game_result' => 'crew_wins',
                ]);
            }

            broadcast(new \App\Events\VotingEnded($lobby, $eliminatedPlayer, 'crew_wins'));

            return response()->json([
                'success' => true,
                'eliminated' => [
                    'id' => $eliminatedPlayer->id,
                    'name' => $eliminatedPlayer->name,
                    'is_impostor' => true,
                ],
                'was_impostor' => true,
                'game_result' => $restarted ? null : 'crew_wins',
                'round_restarted' => $restarted,
            ]);
        }

        $this->advanceTurn($lobby);
        broadcast(new \App\Events\VotingEnded($lobby, $eliminatedPlayer, null));

        return response()->json([
            'success' => true,
            'eliminated' => [
                'id' => $eliminatedPlayer->id,
                'name' => $eliminatedPlayer->name,
                'is_impostor' => false,
            ],
            'was_impostor' => false,
            'game_result' => null,
        ]);
    }

    private function resetVotePhase(Lobby $lobby): void
    {
        $lobby->update([
            'vote_now_votes' => [],
            'reroll_votes' => [],
        ]);
        $lobby->players()->update([
            'has_voted_vote_now' => false,
            'has_voted_reroll' => false,
        ]);
    }

    private function restartRound(Lobby $lobby): bool
    {
        $lobby->load('players');
        $word = app(WordSelectionService::class)->selectWordForGame();

        if (! $word) {
            return false;
        }

        $allPlayers = $lobby->players;
        $eligibleForImpostor = $allPlayers->filter(fn ($p) => ($p->impostor_streak ?? 0) < 3);
        $impostorPool = $eligibleForImpostor->isEmpty() ? $allPlayers : $eligibleForImpostor;
        $impostorCount = min($lobby->settings['impostor_count'] ?? 1, $impostorPool->count());
        $impostorIds = $impostorPool->pluck('id')->shuffle()->take($impostorCount)->all();

        $playerIds = $allPlayers->pluck('id')->shuffle()->values()->all();

        foreach ($lobby->players as $player) {
            $isImpostor = in_array($player->id, $impostorIds);
            $player->update([
                'is_impostor' => $isImpostor,
                'word_id' => $isImpostor ? $word->impostor_word_id : $word->id,
                'is_eliminated' => false,
                'impostor_streak' => $isImpostor ? ($player->impostor_streak ?? 0) + 1 : 0,
                'has_voted_vote_now' => false,
                'has_voted_reroll' => false,
                'turn_position' => array_search($player->id, $playerIds) !== false ? array_search($player->id, $playerIds) : null,
            ]);
        }

        $lobby->update([
            'status' => 'playing',
            'word_id' => $word->id,
            'turn_order' => $playerIds,
            'current_turn_index' => 0,
            'current_turn_player_id' => $playerIds[0] ?? null,
            'turn_started_at' => now(),
            'vote_now_votes' => [],
            'reroll_votes' => [],
            'current_round' => $lobby->current_round + 1,
        ]);

        broadcast(new \App\Events\GameStarted($lobby, $word));

        return true;
    }

    /**
     * Leave lobby.
     */
    public function leaveLobby(string $code): \Illuminate\Http\RedirectResponse
    {
        $lobby = Lobby::where('code', strtoupper($code))->firstOrFail();
        $currentPlayerId = session('current_player_id');

        $player = $lobby->players()->find($currentPlayerId);

        if ($player) {
            if ($player->is_host) {
                $newHost = $lobby->players()->where('id', '!=', $player->id)->first();
                if ($newHost) {
                    $newHost->update(['is_host' => true]);
                } else {
                    $lobby->delete();

                    return redirect()->route('home');
                }
            }

            $playerId = $player->id;
            $player->delete();

            if ($lobby->status === 'playing' && $lobby->turn_order) {
                $turnOrder = array_values(array_filter($lobby->turn_order, fn ($id) => (int) $id !== $playerId));
                $lobby->update(['turn_order' => $turnOrder]);

                if ($lobby->current_turn_player_id === $playerId && ! empty($turnOrder)) {
                    $this->advanceTurn($lobby->fresh());
                }
            }

            broadcast(new \App\Events\PlayerLeft($lobby, $playerId));
        }

        return redirect()->route('home');
    }

    /**
     * Update lobby settings.
     */
    public function updateSettings(Request $request, string $code): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.impostor_count' => 'nullable|integer|min:1|max:5',
            'settings.max_players' => 'nullable|integer|min:2|max:20',
            'settings.discussion_time' => 'nullable|integer|min:15|max:300',
            'settings.voting_time' => 'nullable|integer|min:15|max:180',
            'settings.word_difficulty' => 'nullable|integer|min:1|max:5',
        ]);

        $lobby = Lobby::where('code', strtoupper($code))->firstOrFail();
        $this->validateHost($lobby);

        $lobby->update(['settings' => array_merge($lobby->settings, $validated['settings'])]);

        broadcast(new \App\Events\SettingsUpdated($lobby, $lobby->settings));

        return response()->json(['success' => true, 'settings' => $lobby->settings]);
    }

    /**
     * Send a chat message.
     */
    public function sendMessage(Request $request, string $code): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:500',
            'recipient_id' => 'nullable|exists:players,id',
        ]);

        $lobby = Lobby::where('code', strtoupper($code))->firstOrFail();
        $currentPlayerId = session('current_player_id');
        $currentPlayer = $lobby->players()->find($currentPlayerId);

        if (! $currentPlayer) {
            return response()->json(['error' => 'Not in lobby'], 403);
        }

        $isDm = ! empty($validated['recipient_id']);

        $message = Message::create([
            'lobby_id' => $lobby->id,
            'sender_id' => $currentPlayerId,
            'recipient_id' => $validated['recipient_id'] ?? null,
            'content' => $validated['content'],
            'is_dm' => $isDm,
            'is_read' => false,
        ]);

        // Broadcast message
        broadcast(new \App\Events\MessageSent($lobby, $message));

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'content' => $message->content,
                'sender_id' => $message->sender_id,
                'sender_name' => $currentPlayer->name,
                'recipient_id' => $message->recipient_id,
                'is_dm' => $message->is_dm,
                'created_at' => $message->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Get chat messages.
     */
    public function getMessages(string $code): \Illuminate\Http\JsonResponse
    {
        $lobby = Lobby::where('code', strtoupper($code))->firstOrFail();
        $currentPlayerId = session('current_player_id');
        $currentPlayer = $lobby->players()->find($currentPlayerId);

        if (! $currentPlayer) {
            return response()->json(['error' => 'Not in lobby'], 403);
        }

        $messages = Message::where('lobby_id', $lobby->id)
            ->where(function ($query) use ($currentPlayerId) {
                $query->where('is_dm', false)
                    ->orWhere('recipient_id', $currentPlayerId)
                    ->orWhere('sender_id', $currentPlayerId);
            })
            ->with('sender:id,name')
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender->name,
                    'recipient_id' => $message->recipient_id,
                    'is_dm' => $message->is_dm,
                    'is_read' => $message->is_read,
                    'created_at' => $message->created_at->toISOString(),
                ];
            });

        // Mark DMs as read
        Message::where('lobby_id', $lobby->id)
            ->where('recipient_id', $currentPlayerId)
            ->where('is_dm', true)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['messages' => $messages]);
    }

    /**
     * Vote to start voting immediately.
     */
    public function voteNow(Request $request, string $code): \Illuminate\Http\JsonResponse
    {
        $lobby = Lobby::where('code', strtoupper($code))
            ->with('players')
            ->firstOrFail();

        $currentPlayerId = session('current_player_id');
        $currentPlayer = $lobby->players()->find($currentPlayerId);

        if (! $currentPlayer || $currentPlayer->is_eliminated) {
            return response()->json(['error' => 'Cannot vote'], 403);
        }

        // Check if already voted
        $voteNowVotes = $lobby->vote_now_votes ?? [];
        if (in_array($currentPlayerId, $voteNowVotes)) {
            return response()->json(['error' => 'Already voted'], 400);
        }

        // Add vote
        $voteNowVotes[] = $currentPlayerId;
        $lobby->update(['vote_now_votes' => $voteNowVotes]);
        $currentPlayer->update(['has_voted_vote_now' => true]);

        $activePlayers = $lobby->players->where('is_eliminated', false)->count();
        $threshold = ceil($activePlayers * 0.7);
        $activated = count($voteNowVotes) >= $threshold;

        if ($activated) {
            Cache::forget("lobby_{$lobby->id}_votes");
        }

        broadcast(new \App\Events\VoteNowUpdated($lobby, count($voteNowVotes), $threshold, $activated));

        return response()->json([
            'success' => true,
            'count' => count($voteNowVotes),
            'threshold' => $threshold,
            'activated' => $activated,
        ]);
    }

    /**
     * Vote to reroll the word.
     */
    public function voteReroll(Request $request, string $code): \Illuminate\Http\JsonResponse
    {
        $lobby = Lobby::where('code', strtoupper($code))
            ->with('players')
            ->firstOrFail();

        $currentPlayerId = session('current_player_id');
        $currentPlayer = $lobby->players()->find($currentPlayerId);

        if (! $currentPlayer || $currentPlayer->is_eliminated) {
            return response()->json(['error' => 'Cannot vote'], 403);
        }

        if ($currentPlayer->is_impostor) {
            return response()->json(['error' => 'Impostor cannot vote for reroll'], 403);
        }

        $rerollVotes = $lobby->reroll_votes ?? [];
        if (in_array($currentPlayerId, $rerollVotes)) {
            return response()->json(['error' => 'Already voted'], 400);
        }

        // Add vote
        $rerollVotes[] = $currentPlayerId;
        $lobby->update(['reroll_votes' => $rerollVotes]);
        $currentPlayer->update(['has_voted_reroll' => true]);

        $activePlayers = $lobby->players->where('is_eliminated', false)->count();
        $threshold = ceil($activePlayers * 0.7);
        $activated = count($rerollVotes) >= $threshold;

        // If threshold reached, reroll the word
        if ($activated) {
            $this->rerollWordInternal($lobby);
        }

        broadcast(new \App\Events\RerollUpdated($lobby, count($rerollVotes), $threshold, $activated));

        return response()->json([
            'success' => true,
            'count' => count($rerollVotes),
            'threshold' => $threshold,
            'activated' => $activated,
        ]);
    }

    /**
     * Advance to the next player's turn.
     */
    public function nextTurn(Request $request, string $code): \Illuminate\Http\JsonResponse
    {
        $lobby = Lobby::where('code', strtoupper($code))
            ->with('players')
            ->firstOrFail();

        $currentPlayerId = session('current_player_id');
        $currentPlayer = $lobby->players()->find($currentPlayerId);

        // Only host or current turn player can advance
        if (! $currentPlayer || (! $currentPlayer->is_host && $lobby->current_turn_player_id !== $currentPlayerId)) {
            return response()->json(['error' => 'Not authorized'], 403);
        }

        $this->advanceTurn($lobby);

        return response()->json([
            'success' => true,
            'current_turn_player_id' => $lobby->current_turn_player_id,
            'current_turn_index' => $lobby->current_turn_index,
        ]);
    }

    /**
     * Advance to next turn internally.
     */
    private function advanceTurn(Lobby $lobby): void
    {
        $turnOrder = $lobby->turn_order ?? [];
        if (empty($turnOrder)) {
            return;
        }

        $currentIndex = $lobby->current_turn_index ?? 0;
        $nextIndex = ($currentIndex + 1) % count($turnOrder);

        // Check if we've completed a round
        $newRound = $lobby->current_round;
        if ($nextIndex === 0) {
            $newRound = $lobby->current_round + 1;
        }

        $activeInOrder = $lobby->players()->whereIn('id', $turnOrder)->where('is_eliminated', false)->pluck('id')->all();
        if (empty($activeInOrder)) {
            return;
        }

        $attempts = 0;
        $nextPlayer = null;
        while ($attempts < count($turnOrder)) {
            $nextPlayerId = $turnOrder[$nextIndex];
            $nextPlayer = $lobby->players()->find($nextPlayerId);

            if ($nextPlayer && ! $nextPlayer->is_eliminated) {
                break;
            }

            $nextIndex = ($nextIndex + 1) % count($turnOrder);
            $attempts++;
        }

        if (! $nextPlayer || $nextPlayer->is_eliminated) {
            return;
        }

        $lobby->update([
            'current_turn_index' => $nextIndex,
            'current_turn_player_id' => $turnOrder[$nextIndex],
            'turn_started_at' => now(),
            'current_round' => $newRound,
        ]);

        broadcast(new \App\Events\TurnAdvanced($lobby, $turnOrder[$nextIndex], $nextIndex, $newRound));
    }

    /**
     * Reroll the word internally.
     */
    private function rerollWordInternal(Lobby $lobby): void
    {
        $word = app(WordSelectionService::class)->selectWordForGame();

        if (! $word) {
            return;
        }

        // Reset reroll votes
        $lobby->update([
            'reroll_votes' => [],
            'word_id' => $word->id,
        ]);

        $impostorWordId = $word->impostor_word_id ?? $word->id;

        foreach ($lobby->players as $player) {
            $player->update([
                'word_id' => $player->is_impostor ? $impostorWordId : $word->id,
                'has_voted_reroll' => false,
            ]);
        }

        broadcast(new \App\Events\WordRerolled($lobby, $word));
    }

    private function generateUniqueLobbyCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (Lobby::where('code', $code)->exists());

        return $code;
    }

    private function createPlayer(Lobby $lobby, string $name, bool $isHost): Player
    {
        return Player::create([
            'name' => $name,
            'lobby_id' => $lobby->id,
            'is_host' => $isHost,
            'session_id' => session()->getId(),
            'is_impostor' => false,
            'is_eliminated' => false,
            'has_voted_vote_now' => false,
            'has_voted_reroll' => false,
            'last_active_at' => now(),
        ]);
    }

    private function cachePlayerSession(int $playerId, string $lobbyCode): void
    {
        session(['current_player_id' => $playerId, 'current_lobby_code' => $lobbyCode]);
    }

    private function validateHost(Lobby $lobby): void
    {
        $currentPlayerId = session('current_player_id');
        $player = $lobby->players()->find($currentPlayerId);

        if (! $player || ! $player->is_host) {
            throw ValidationException::withMessages([
                'authorization' => 'Only the host can perform this action.',
            ]);
        }
    }

    private function getDefaultSettings(): array
    {
        return [
            'impostor_count' => 1,
            'max_players' => 10,
            'discussion_time' => 60,
            'voting_time' => 30,
            'word_difficulty' => 3,
        ];
    }
}
