<?php

use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::post('/lobby/create', [GameController::class, 'createLobby'])->name('lobby.create');
Route::post('/lobby/join/{code}', [GameController::class, 'joinLobby'])->name('lobby.join');
Route::get('/lobby/{code}', [GameController::class, 'showLobby'])->name('lobby.show');
Route::post('/lobby/{code}/start', [GameController::class, 'startGame'])->name('game.start');
Route::post('/lobby/{code}/settings', [GameController::class, 'updateSettings'])->name('lobby.settings');
Route::post('/lobby/{code}/leave', [GameController::class, 'leaveLobby'])->name('lobby.leave');

Route::get('/game/{code}', [GameController::class, 'showGame'])->name('game.play');
Route::get('/game/{code}/state', [GameController::class, 'getGameState'])->name('game.state');
Route::post('/game/{code}/vote', [GameController::class, 'votePlayer'])->name('game.vote');
Route::post('/game/{code}/end-voting', [GameController::class, 'endVoting'])->name('game.end-voting');

// Chat and messaging
Route::post('/game/{code}/message', [GameController::class, 'sendMessage'])->name('game.message');
Route::get('/game/{code}/messages', [GameController::class, 'getMessages'])->name('game.messages');

// Turn system
Route::post('/game/{code}/next-turn', [GameController::class, 'nextTurn'])->name('game.next-turn');

// Vote features
Route::post('/game/{code}/vote-now', [GameController::class, 'voteNow'])->name('game.vote-now');
Route::post('/game/{code}/vote-reroll', [GameController::class, 'voteReroll'])->name('game.vote-reroll');

// Auto-restart (host only)
Route::post('/game/{code}/restart', [GameController::class, 'restartGame'])->name('game.restart');
