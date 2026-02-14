<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Users,
    MessageSquare,
    LogOut,
    Swords,
    Check,
    X,
    Trophy,
    Skull,
    Sparkles,
    Target,
    ChevronLeft,
    Crown,
    ArrowRight,
} from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, computed } from 'vue';
import { avatarUrl } from '@/lib/avatars';
import ChatBox from '@/components/ChatBox.vue';
import { endVoting, state, vote, nextTurn, voteNow, voteReroll, restart } from '@/routes/game';
import { leave } from '@/routes/lobby';

interface Player {
    id: number;
    name: string;
    is_host: boolean;
    is_eliminated: boolean;
    turn_position: number;
    avatar: string | null;
}

interface VoteData {
    count: number;
    threshold: number;
    progress: number;
    has_voted: boolean;
}

interface EliminationVote {
    player_id: number;
    target_player_id: number | null;
}

interface GameStateData {
    status: string;
    game_result?: string | null;
    word: string | null;
    is_impostor: boolean;
    word_category: string | null;
    players: Player[];
    current_turn_player_id: number | null;
    current_turn_index: number;
    turn_order: number[];
    turn_started_at: string | null;
    current_round: number;
    impostor_wins: number;
    crew_wins: number;
    voting_phase?: boolean;
    last_eliminated?: { id: number; name: string; is_impostor: boolean } | null;
    vote_now: VoteData;
    reroll: VoteData;
    elimination_vote_has_voted?: boolean;
    elimination_votes?: EliminationVote[];
    total_voters?: number;
    auto_restart?: boolean;
}

const props = defineProps<{
    code: string;
    current_player: {
        id: number;
        name: string;
        is_host: boolean;
    };
    lobby: {
        code: string;
        current_turn_player_id: number | null;
        turn_order: { id: number; name: string; is_eliminated: boolean }[];
        current_turn_index: number;
        impostor_wins: number;
        crew_wins: number;
        current_round: number;
    };
    unread_dm_count: number;
}>();

const gameState = ref<GameStateData>({
    status: 'waiting',
    word: null,
    is_impostor: false,
    word_category: null,
    players: [],
    current_turn_player_id: null,
    current_turn_index: 0,
    turn_order: [],
    turn_started_at: null,
    current_round: 1,
    impostor_wins: 0,
    crew_wins: 0,
    vote_now: { count: 0, threshold: 1, progress: 0, has_voted: false },
    reroll: { count: 0, threshold: 1, progress: 0, has_voted: false },
    elimination_vote_has_voted: false,
    elimination_votes: [],
    total_voters: 0,
});

const votingPhase = ref(false);
const selectedPlayerId = ref<number | null>(null);
const selectedSkip = ref(false);
const gameResult = ref<string | null>(null);
const eliminatedPlayer = ref<{ id: number; name: string; is_impostor: boolean } | null>(null);
const showImpostorReveal = ref(false);
const showChat = ref(false);
const showDMNotification = ref(false);
const dmNotificationText = ref('');
const autoRestartCountdown = ref(0);
const autoRestartTimer = ref<number | null>(null);

let polling: number | null = null;

const currentPlayerData = computed(() => {
    const myId = props.current_player.id;
    return gameState.value.players.find((p) => Number(p.id) === Number(myId));
});

const canVote = computed(() => {
    return votingPhase.value && !currentPlayerData.value?.is_eliminated;
});

const activePlayers = computed(() => {
    return gameState.value.players.filter((p) => !p.is_eliminated);
});

const currentTurnPlayer = computed(() => {
    return gameState.value.players.find((p) => p.id === gameState.value.current_turn_player_id);
});

const isMyTurn = computed(() => {
    return gameState.value.current_turn_player_id === props.current_player.id;
});

// Desktop turn indicator calculations
const turnsPlayed = computed(() => {
    const n = Math.max(1, gameState.value.turn_order?.length ?? 0);
    return (gameState.value.current_round - 1) * n + (gameState.value.current_turn_index ?? 0);
});

const circlePlayers = computed(() => activePlayers.value.slice(0, 10));
const RADIUS_PX = 115; // Increased to give arrow plenty of room
const CENTER_SIZE = 48;

function positionOnCircle(index: number, total: number): { left: string; top: string } {
    if (total <= 0) return { left: '50%', top: '50%' };
    const angleDeg = (360 / total) * index - 90;
    const angleRad = (angleDeg * Math.PI) / 180;
    const x = Math.cos(angleRad) * RADIUS_PX;
    const y = Math.sin(angleRad) * RADIUS_PX;
    return {
        left: `calc(50% + ${x}px)`,
        top: `calc(50% + ${y}px)`,
    };
}

const currentTurnIndexInCircle = computed(() => {
    if (gameState.value.current_turn_player_id == null) return -1;
    return circlePlayers.value.findIndex((p) => p.id === gameState.value.current_turn_player_id);
});

const arrowRotationDeg = computed(() => {
    const n = circlePlayers.value.length;
    const idx = currentTurnIndexInCircle.value;
    if (n <= 0 || idx < 0) return -90;
    return (360 / n) * idx - 90;
});

// Arrow should stop before the player avatar so it's visible (leave a 12px gap)
const arrowLength = computed(() => RADIUS_PX - CENTER_SIZE / 2 - 12);

// Vote stats
const voteStats = computed(() => {
    const votes = gameState.value.elimination_votes || [];
    const totalVoters = gameState.value.total_voters || activePlayers.value.length;
    const playerVotes: Record<number, number> = {};
    let skipVotes = 0;
    votes.forEach((v) => {
        if (v.target_player_id === null) {
            skipVotes++;
        } else {
            playerVotes[v.target_player_id] = (playerVotes[v.target_player_id] || 0) + 1;
        }
    });
    return { totalVoters, votesCast: votes.length, skipVotes, playerVotes, remaining: totalVoters - votes.length };
});

function getPlayerVoteCount(playerId: number): number {
    return voteStats.value.playerVotes[playerId] || 0;
}

function getDonutProgress(count: number): number {
    const total = voteStats.value.totalVoters;
    if (total === 0) return 0;
    return (count / total) * 100;
}

const fetchGameState = async () => {
    try {
        const response = await axios.get(state.url(props.code));
        const oldVoteNowCount = gameState.value.vote_now?.count || 0;
        const oldRerollCount = gameState.value.reroll?.count || 0;
        const wasFinished = gameState.value.status === 'finished';

        gameState.value = response.data;
        votingPhase.value = !!gameState.value.voting_phase;

        if (response.data.elimination_votes) {
            gameState.value.elimination_votes = response.data.elimination_votes;
        }

        // Handle auto-restart countdown
        if (response.data.auto_restart && !wasFinished) {
            startAutoRestartCountdown();
        }

        if (gameState.value.last_eliminated && !gameState.value.voting_phase) {
            eliminatedPlayer.value = gameState.value.last_eliminated;
            showImpostorReveal.value = true;
        }

        if (oldVoteNowCount < gameState.value.vote_now.threshold && gameState.value.vote_now.count >= gameState.value.vote_now.threshold) {
            startVotingPhase();
        }

        if (oldRerollCount < gameState.value.reroll.threshold && gameState.value.reroll.count >= gameState.value.reroll.threshold) {
            showNotification('Word has been rerolled!');
        }

        if (response.data.status === 'finished' && response.data.game_result) {
            gameResult.value = response.data.game_result;
        }
        if (response.data.status === 'playing' && response.data.game_result == null) {
            gameResult.value = null;
            eliminatedPlayer.value = null;
            showImpostorReveal.value = false;
            autoRestartCountdown.value = 0;
            if (autoRestartTimer.value) {
                clearInterval(autoRestartTimer.value);
                autoRestartTimer.value = null;
            }
        }
    } catch (error) {
        console.error('Failed to fetch game state:', error);
    }
};

const startAutoRestartCountdown = () => {
    autoRestartCountdown.value = 8;
    if (autoRestartTimer.value) clearInterval(autoRestartTimer.value);
    autoRestartTimer.value = window.setInterval(() => {
        autoRestartCountdown.value--;
        if (autoRestartCountdown.value <= 0) {
            if (autoRestartTimer.value) clearInterval(autoRestartTimer.value);
            autoRestartTimer.value = null;
            // Host triggers the restart
            if (props.current_player?.is_host) {
                triggerRestart();
            }
        }
    }, 1000);
};

const triggerRestart = async () => {
    try {
        await axios.post(restart.url(props.code));
        await fetchGameState();
    } catch (error) {
        console.error('Failed to restart game:', error);
    }
};

const showNotification = (text: string) => {
    dmNotificationText.value = text;
    showDMNotification.value = true;
    setTimeout(() => {
        showDMNotification.value = false;
    }, 3000);
};

const hasVotedElimination = computed(() => gameState.value.elimination_vote_has_voted ?? false);

const myVoteTarget = computed(() => {
    const votes = gameState.value.elimination_votes || [];
    const myVote = votes.find((v) => v.player_id === props.current_player.id);
    return myVote?.target_player_id ?? null;
});

const voteForPlayer = (playerId: number) => {
    if (!canVote.value || hasVotedElimination.value) return;
    selectedPlayerId.value = playerId;
    selectedSkip.value = false;
};

const voteForSkip = () => {
    if (!canVote.value || hasVotedElimination.value) return;
    selectedSkip.value = true;
    selectedPlayerId.value = null;
};

const submitVote = async () => {
    if (hasVotedElimination.value) return;
    try {
        await axios.post(vote.url(props.code), {
            target_player_id: selectedSkip.value ? null : selectedPlayerId.value,
        });
        selectedPlayerId.value = null;
        selectedSkip.value = false;
        await fetchGameState();
    } catch (error) {
        console.error('Failed to vote:', error);
    }
};

const submitSkipVote = async () => {
    if (hasVotedElimination.value) return;
    try {
        await axios.post(vote.url(props.code), { target_player_id: null });
        selectedPlayerId.value = null;
        selectedSkip.value = false;
        await fetchGameState();
    } catch (error) {
        console.error('Failed to skip vote:', error);
    }
};

const endVotingPhase = async () => {
    try {
        const response = await axios.post(endVoting.url(props.code));
        eliminatedPlayer.value = response.data.eliminated;
        if (response.data.game_result) {
            gameResult.value = response.data.game_result;
        }
        if (response.data.eliminated) {
            showImpostorReveal.value = true;
        }
        if (response.data.auto_restart) {
            startAutoRestartCountdown();
        }
        await fetchGameState();
        setTimeout(() => {
            showImpostorReveal.value = false;
        }, 4000);
    } catch (error) {
        console.error('Failed to end voting:', error);
    }
};

const startVotingPhase = () => {
    votingPhase.value = true;
};

const handleNextTurn = async () => {
    try {
        await axios.post(nextTurn.url(props.code));
        await fetchGameState();
    } catch (error) {
        console.error('Failed to advance turn:', error);
    }
};

const handleVoteNow = async () => {
    try {
        await axios.post(voteNow.url(props.code));
        await fetchGameState();
    } catch (error) {
        console.error('Failed to vote now:', error);
    }
};

const handleVoteReroll = async () => {
    try {
        await axios.post(voteReroll.url(props.code));
        await fetchGameState();
    } catch (error: unknown) {
        const msg = axios.isAxiosError(error) && error.response?.data?.error;
        showNotification(msg || 'Failed to vote for reroll');
    }
};

const leaveGame = () => {
    if (confirm('Leave the game?')) {
        router.post(leave.url(props.code));
    }
};

const GAME_POLL_INTERVAL_MS = 1500;

onMounted(async () => {
    await fetchGameState();
    polling = window.setInterval(fetchGameState, GAME_POLL_INTERVAL_MS);
});

onUnmounted(() => {
    if (polling) clearInterval(polling);
    if (autoRestartTimer.value) clearInterval(autoRestartTimer.value);
});
</script>

<template>
    <Head :title="`Game - ${code}`" />

    <div class="bg-void flex h-screen w-screen flex-col overflow-hidden">
        <!-- Elimination Flash Overlay -->
        <div v-if="showImpostorReveal && eliminatedPlayer && !eliminatedPlayer.is_impostor" class="elimination-flash"></div>

        <!-- Notification Toast -->
        <Transition name="slide-down">
            <div
                v-if="showDMNotification"
                class="glass fixed top-3 left-1/2 z-50 flex -translate-x-1/2 items-center gap-2 rounded-xl border border-blue-400/50 bg-blue-600/90 px-4 py-2 text-sm text-white shadow-2xl sm:top-4 sm:gap-3 sm:rounded-2xl sm:px-6 sm:py-3"
            >
                <Sparkles class="h-4 w-4 sm:h-5 sm:w-5" />
                {{ dmNotificationText }}
            </div>
        </Transition>

        <!-- Header -->
        <header class="glass flex-shrink-0 border-b border-void-border px-3 py-2 sm:px-4 sm:py-3">
            <div class="flex items-center justify-between gap-2">
                <!-- Logo & Round -->
                <div class="flex items-center gap-2">
                    <div
                        class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-lg bg-void-elevated shadow-lg shadow-blue-500/20 sm:h-20 sm:w-20 sm:rounded-xl"
                    >
                        <img src="/images/Logo.png" alt="Word Impostor" class="h-8 w-auto object-contain sm:h-12" />
                    </div>
                    <div>
                        <h1 class="text-xs font-bold text-text-primary sm:text-base">Round {{ gameState.current_round }}</h1>
                        <p class="font-mono text-[9px] text-text-secondary sm:text-xs">{{ code }}</p>
                    </div>
                </div>

                <!-- Word (Compact for mobile) -->
                <div v-if="gameState.word" class="flex min-w-0 flex-1 items-center justify-center px-2">
                    <div
                        class="rounded-lg border px-2 py-1 text-center"
                        :class="gameState.is_impostor ? 'border-red-500/50 bg-red-950/30' : 'border-blue-500/50 bg-blue-950/30'"
                    >
                        <span class="text-[10px] text-text-secondary">Your word:</span>
                        <span class="ml-1 text-sm font-bold" :class="gameState.is_impostor ? 'text-red-400' : 'text-blue-400'">
                            {{ gameState.word }}
                        </span>
                    </div>
                </div>

                <!-- Scoreboard -->
                <div class="glass flex items-center gap-1 rounded-lg p-1 sm:gap-2 sm:rounded-2xl sm:p-1.5">
                    <div
                        class="flex items-center gap-1 rounded-md px-1.5 py-0.5 sm:gap-2 sm:rounded-lg sm:px-3 sm:py-1"
                        :class="gameResult === 'impostor_wins' ? 'bg-red-500/20' : ''"
                    >
                        <Skull class="h-3 w-3 text-red-400 sm:h-4 sm:w-4" />
                        <span class="text-sm font-black text-text-primary">{{ gameState.impostor_wins }}</span>
                    </div>
                    <div class="h-4 w-px bg-void-border sm:h-6"></div>
                    <div
                        class="flex items-center gap-1 rounded-md px-1.5 py-0.5 sm:gap-2 sm:rounded-lg sm:px-3 sm:py-1"
                        :class="gameResult === 'crew_wins' ? 'bg-blue-500/20' : ''"
                    >
                        <Trophy class="h-3 w-3 text-blue-400 sm:h-4 sm:w-4" />
                        <span class="text-sm font-black text-text-primary">{{ gameState.crew_wins }}</span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-1">
                    <button
                        @click="showChat = !showChat"
                        class="btn-glass relative rounded-lg p-1.5 sm:rounded-xl sm:p-2.5 md:hidden"
                        :class="showChat ? 'bg-void-hover text-text-primary' : 'text-text-secondary'"
                    >
                        <MessageSquare class="h-4 w-4 sm:h-5 sm:w-5" />
                        <span
                            v-if="unread_dm_count > 0"
                            class="absolute -top-1 -right-1 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-red-500 text-[9px] font-bold text-white"
                        >
                            {{ unread_dm_count }}
                        </span>
                    </button>
                    <button @click="leaveGame" class="btn-danger rounded-lg p-1.5 sm:rounded-xl sm:p-2.5">
                        <LogOut class="h-4 w-4 sm:h-5 sm:w-5" />
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Layout -->
        <main class="relative flex flex-1 overflow-hidden">
            <!-- Left Sidebar: Chat (Desktop) -->
            <aside class="hidden w-[260px] flex-shrink-0 flex-col border-r border-void-border bg-void-elevated/30 md:flex lg:w-72">
                <div class="flex h-full flex-col p-3">
                    <div class="mb-2 flex items-center gap-2">
                        <MessageSquare class="h-4 w-4 text-blue-400" />
                        <span class="text-sm font-bold text-text-primary">Chat</span>
                    </div>
                    <div class="flex-1 overflow-hidden rounded-xl">
                        <ChatBox :code="code" :current-player-id="current_player.id" :players="gameState.players" />
                    </div>
                </div>
            </aside>

            <!-- Mobile Chat Drawer -->
            <Transition name="slide-left">
                <div
                    v-show="showChat"
                    class="bg-void fixed inset-y-0 left-0 z-40 flex h-full w-[280px] flex-col border-r border-void-border shadow-2xl md:hidden"
                >
                    <div class="flex h-full flex-col p-3">
                        <div class="mb-3 flex items-center justify-between">
                            <span class="text-sm font-bold text-text-primary">Chat</span>
                            <button @click="showChat = false" class="btn-glass rounded-lg p-1.5">
                                <ChevronLeft class="h-4 w-4" />
                            </button>
                        </div>
                        <div class="flex-1 overflow-hidden">
                            <ChatBox :code="code" :current-player-id="current_player.id" :players="gameState.players" />
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Center: Main Game Area -->
            <section class="flex min-w-0 flex-1 flex-col overflow-y-auto">
                <!-- Full Word Display (Desktop) / Hidden on Mobile (shown in header) -->
                <div v-if="gameState.word" class="hidden flex-shrink-0 p-4 sm:block sm:p-6">
                    <div
                        class="word-card relative overflow-hidden rounded-2xl border-2 p-6 text-center sm:rounded-3xl sm:p-8"
                        :class="gameState.is_impostor ? 'border-red-500/50 bg-red-950/20' : 'border-blue-500/50 bg-blue-950/20'"
                    >
                        <div
                            class="absolute inset-0 opacity-50"
                            :class="
                                gameState.is_impostor
                                    ? 'bg-gradient-to-br from-red-600/20 to-transparent'
                                    : 'bg-gradient-to-br from-blue-600/20 to-transparent'
                            "
                        ></div>
                        <div class="relative z-10">
                            <div
                                class="mb-3 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold tracking-wider uppercase sm:mb-4 sm:gap-2 sm:px-4 sm:py-1.5 sm:text-sm"
                                :class="
                                    gameState.is_impostor
                                        ? 'bg-red-500/20 text-red-400 ring-1 ring-red-500/30'
                                        : 'bg-blue-500/20 text-blue-400 ring-1 ring-blue-500/30'
                                "
                            >
                                <Skull v-if="gameState.is_impostor" class="h-3 w-3 sm:h-4 sm:w-4" />
                                <Trophy v-else class="h-3 w-3 sm:h-4 sm:w-4" />
                                {{ gameState.is_impostor ? 'You are the IMPOSTOR' : 'You are CREW' }}
                            </div>

                            <div
                                class="text-3xl font-black tracking-wider uppercase sm:text-5xl md:text-6xl"
                                :class="
                                    gameState.is_impostor ? 'text-shadow-glow-red animate-pulse text-red-400' : 'text-shadow-glow-blue text-blue-400'
                                "
                            >
                                {{ gameState.word }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Game Content -->
                <div class="flex-1 space-y-2 p-2 sm:space-y-4 sm:p-4">
                    <!-- Victory Banner -->
                    <Transition name="bounce">
                        <div
                            v-if="gameResult"
                            class="glass flex-shrink-0 rounded-xl border-2 p-3 text-center sm:rounded-2xl sm:p-6"
                            :class="gameResult === 'crew_wins' ? 'border-blue-500/50 bg-blue-900/30' : 'border-red-500/50 bg-red-900/30'"
                        >
                            <div class="flex items-center justify-center gap-3 sm:gap-4">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-lg sm:h-20 sm:w-20 sm:rounded-2xl"
                                    :class="gameResult === 'crew_wins' ? 'bg-blue-500/20' : 'bg-red-500/20'"
                                >
                                    <component
                                        :is="gameResult === 'crew_wins' ? Trophy : Skull"
                                        class="h-5 w-5 sm:h-10 sm:w-10"
                                        :class="gameResult === 'crew_wins' ? 'text-blue-400' : 'text-red-400'"
                                    />
                                </div>
                                <div class="text-left">
                                    <h2 class="text-base font-black text-text-primary sm:text-3xl">
                                        {{ gameResult === 'crew_wins' ? 'Crew Wins!' : 'Impostor Wins!' }}
                                    </h2>
                                    <p v-if="eliminatedPlayer" class="text-xs text-text-secondary sm:text-sm">
                                        {{ eliminatedPlayer.name }} was {{ eliminatedPlayer.is_impostor ? 'the Impostor' : 'a Crew member' }}
                                    </p>
                                    <p v-if="autoRestartCountdown > 0" class="mt-1 text-xs font-bold text-amber-400 sm:mt-2 sm:text-sm">
                                        Next round in {{ autoRestartCountdown }}...
                                    </p>
                                </div>
                            </div>
                        </div>
                    </Transition>

                    <!-- Turn Indicator -->
                    <div v-if="!gameResult && !votingPhase" class="glass-card rounded-xl p-3 sm:rounded-2xl sm:p-6">
                        <!-- Mobile: Simple List View -->
                        <div class="sm:hidden">
                            <div class="mb-3 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Crown class="h-4 w-4 text-blue-400" />
                                    <span class="text-sm font-bold text-text-primary">
                                        {{ isMyTurn ? 'Your Turn!' : `${currentTurnPlayer?.name || 'Waiting'}'s turn` }}
                                    </span>
                                </div>
                                <span class="text-xs text-text-secondary">Turn {{ turnsPlayed }}</span>
                            </div>
                            <div class="mb-3 flex flex-wrap gap-2">
                                <div
                                    v-for="player in activePlayers"
                                    :key="player.id"
                                    class="flex items-center gap-1.5 rounded-lg px-2 py-1"
                                    :class="
                                        player.id === gameState.current_turn_player_id ? 'bg-blue-500/20 ring-1 ring-blue-500/30' : 'bg-void-hover'
                                    "
                                >
                                    <div
                                        class="flex h-6 w-6 items-center justify-center overflow-hidden rounded-md text-xs font-bold"
                                        :class="
                                            player.id === gameState.current_turn_player_id
                                                ? 'bg-blue-500 text-white'
                                                : 'bg-void-elevated text-text-secondary'
                                        "
                                    >
                                        <img
                                            v-if="player.avatar"
                                            :src="avatarUrl(player.avatar)"
                                            :alt="player.name"
                                            class="h-full w-full object-cover"
                                        />
                                        <span v-else>{{ player.name.charAt(0).toUpperCase() }}</span>
                                    </div>
                                    <span class="max-w-[60px] truncate text-xs">{{ player.name }}</span>
                                </div>
                            </div>
                            <button
                                v-if="isMyTurn"
                                @click="handleNextTurn"
                                class="btn-primary w-full rounded-xl py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-500/30"
                            >
                                End My Turn
                            </button>
                        </div>

                        <!-- Desktop: Circular View -->
                        <div class="hidden sm:block">
                            <div class="mb-4 flex justify-center">
                                <div
                                    class="rounded-full border border-void-border bg-void-elevated px-4 py-1.5 text-sm font-bold text-text-primary shadow-lg backdrop-blur-sm"
                                >
                                    <span class="text-text-secondary">Turns:</span>
                                    <span class="ml-2 text-blue-400">{{ turnsPlayed }}</span>
                                </div>
                            </div>
                            <div class="relative mx-auto w-full max-w-[340px]" style="height: 280px">
                                <div
                                    class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-dashed border-void-border/50"
                                    :style="{ width: `${RADIUS_PX * 2}px`, height: `${RADIUS_PX * 2}px` }"
                                ></div>
                                <!-- Arrow using SVG assets -->
                                <div
                                    v-if="currentTurnIndexInCircle >= 0 && circlePlayers.length > 0"
                                    class="absolute top-1/2 left-1/2 z-10 origin-center -translate-x-1/2 -translate-y-1/2 transition-transform duration-500 ease-out"
                                    :style="{ transform: `rotate(${arrowRotationDeg}deg)` }"
                                >
                                    <div
                                        class="relative flex items-center"
                                        :style="{ width: `${arrowLength + 20}px`, transform: `translateX(${CENTER_SIZE / 2 - 5}px)` }"
                                    >
                                        <!-- Shaft SVG -->
                                        <img
                                            src="/images/Shaft.svg"
                                            class="h-3 w-full"
                                            :class="isMyTurn ? 'brightness-125' : 'opacity-80'"
                                            alt=""
                                        />
                                        <!-- Arrow Head SVG -->
                                        <img
                                            src="/images/Arrow_head.svg"
                                            class="absolute -right-2 h-5 w-5 flex-shrink-0"
                                            :class="isMyTurn ? 'brightness-125' : 'opacity-80'"
                                            alt=""
                                        />
                                    </div>
                                </div>
                                <!-- Center using SVG -->
                                <div class="absolute top-1/2 left-1/2 z-20 -translate-x-1/2 -translate-y-1/2">
                                    <div class="relative flex h-14 w-14 items-center justify-center sm:h-16 sm:w-16">
                                        <!-- Center SVG background -->
                                        <img
                                            src="/images/Center.svg"
                                            class="absolute inset-0 h-full w-full"
                                            :class="isMyTurn ? 'drop-shadow-[0_0_8px_rgba(59,130,246,0.6)]' : 'opacity-90'"
                                            alt=""
                                        />
                                        <div v-if="isMyTurn" class="absolute -inset-2 animate-ping rounded-full bg-blue-400/10"></div>
                                        <div class="relative z-10 text-center">
                                            <div class="text-lg leading-none font-black text-blue-400 sm:text-xl">
                                                {{ currentTurnIndexInCircle >= 0 ? currentTurnIndexInCircle + 1 : '–' }}
                                            </div>
                                            <div class="text-[8px] font-medium tracking-wider text-text-tertiary">/{{ circlePlayers.length }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-for="(player, index) in circlePlayers"
                                    :key="player.id"
                                    class="absolute z-30 -translate-x-1/2 -translate-y-1/2 transition-all duration-500"
                                    :style="{
                                        left: positionOnCircle(index, circlePlayers.length).left,
                                        top: positionOnCircle(index, circlePlayers.length).top,
                                    }"
                                >
                                    <div
                                        class="flex flex-col items-center gap-1 transition-all duration-300"
                                        :class="player.id === gameState.current_turn_player_id ? 'scale-110' : 'opacity-70 hover:opacity-100'"
                                    >
                                        <div
                                            class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg text-xs font-bold shadow-lg transition-all duration-300 sm:h-10 sm:w-10 sm:text-sm"
                                            :class="[
                                                player.id === gameState.current_turn_player_id
                                                    ? 'bg-blue-500 text-white ring-2 shadow-blue-500/40 ring-white/50'
                                                    : 'bg-void-hover text-text-secondary',
                                                player.id === current_player.id ? 'ring-offset-void ring-2 ring-blue-400 ring-offset-2' : '',
                                            ]"
                                        >
                                            <img
                                                v-if="player.avatar"
                                                :src="avatarUrl(player.avatar)"
                                                :alt="player.name"
                                                class="h-full w-full object-cover"
                                            />
                                            <span v-else>{{ player.name.charAt(0).toUpperCase() }}</span>
                                        </div>
                                        <span
                                            class="max-w-[60px] truncate rounded-full bg-void-elevated px-1.5 py-0.5 text-center text-[9px] font-bold shadow-lg backdrop-blur-sm"
                                            :class="
                                                player.id === gameState.current_turn_player_id ? 'bg-void-hover text-blue-400' : 'text-text-secondary'
                                            "
                                        >
                                            {{ player.name.length > 5 ? player.name.slice(0, 4) + '…' : player.name }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 flex flex-col items-center gap-3">
                                <div
                                    class="inline-flex items-center gap-2 rounded-full px-5 py-2 text-sm font-bold transition-all duration-300"
                                    :class="[
                                        isMyTurn
                                            ? 'scale-105 border border-blue-500/50 bg-blue-500/20 text-blue-300 shadow-lg shadow-blue-500/20'
                                            : 'border border-void-border bg-void-elevated text-text-secondary',
                                    ]"
                                >
                                    <template v-if="isMyTurn">
                                        <Crown class="h-4 w-4 text-blue-400" />
                                        <span>Your Turn!</span>
                                    </template>
                                    <template v-else-if="currentTurnPlayer">
                                        <span class="text-text-secondary">{{ currentTurnPlayer.name }}'s turn</span>
                                    </template>
                                    <template v-else>
                                        <span>Waiting...</span>
                                    </template>
                                </div>
                                <button
                                    v-if="isMyTurn"
                                    @click="handleNextTurn"
                                    class="btn-primary group flex items-center justify-center gap-2 rounded-xl px-8 py-3 text-base font-bold text-white shadow-xl shadow-blue-500/30 transition-all hover:scale-105 hover:shadow-blue-500/50 active:scale-95"
                                >
                                    End My Turn
                                    <ChevronLeft class="h-5 w-5 rotate-180 transition-transform group-hover:translate-x-1" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Voting Panel -->
                    <div v-else-if="votingPhase" class="glass-card rounded-xl p-3 sm:rounded-2xl sm:p-6">
                        <div class="mb-3 flex items-center justify-between sm:mb-4">
                            <div class="flex items-center gap-2">
                                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-red-500/20 sm:h-10 sm:w-10 sm:rounded-xl">
                                    <Target class="h-3.5 w-3.5 text-red-400 sm:h-5 sm:w-5" />
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-text-primary sm:text-lg">Vote to Eliminate</h3>
                                    <p class="text-xs text-text-secondary">{{ voteStats.votesCast }}/{{ voteStats.totalVoters }} voted</p>
                                </div>
                            </div>
                            <span
                                v-if="hasVotedElimination"
                                class="rounded-full bg-blue-500/20 px-2 py-0.5 text-xs font-bold text-blue-400 ring-1 ring-blue-500/30"
                            >
                                Voted
                            </span>
                        </div>

                        <div
                            v-if="hasVotedElimination && myVoteTarget !== undefined"
                            class="mb-3 rounded-lg bg-blue-500/10 p-2 text-center ring-1 ring-blue-500/20 sm:mb-4 sm:rounded-xl sm:p-3"
                        >
                            <p class="text-xs text-text-secondary sm:text-sm">
                                You voted to
                                <span class="font-bold" :class="myVoteTarget === null ? 'text-blue-400' : 'text-red-400'">
                                    {{ myVoteTarget === null ? 'SKIP' : 'eliminate ' + gameState.players.find((p) => p.id === myVoteTarget)?.name }}
                                </span>
                            </p>
                        </div>

                        <div class="mb-3 grid grid-cols-2 gap-2 sm:mb-4 sm:grid-cols-3 sm:gap-4">
                            <div
                                v-for="player in activePlayers"
                                :key="player.id"
                                class="relative rounded-lg p-2 transition-all sm:rounded-xl sm:p-4"
                                :class="[
                                    selectedPlayerId === player.id && !hasVotedElimination
                                        ? 'cursor-pointer bg-red-600 text-white shadow-lg shadow-red-500/30'
                                        : 'glass-light',
                                    hasVotedElimination && myVoteTarget !== player.id
                                        ? 'cursor-default opacity-70'
                                        : 'cursor-pointer hover:bg-void-hover',
                                    myVoteTarget === player.id ? 'ring-2 ring-red-500' : '',
                                ]"
                                @click="!hasVotedElimination && voteForPlayer(player.id)"
                            >
                                <div class="flex items-center gap-2 sm:gap-3">
                                    <div class="relative h-10 w-10 flex-shrink-0 sm:h-14 sm:w-14">
                                        <svg class="h-full w-full -rotate-90" viewBox="0 0 36 36">
                                            <path
                                                class="text-void-hover"
                                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="3"
                                            />
                                            <path
                                                class="transition-all duration-500"
                                                :class="selectedPlayerId === player.id && !hasVotedElimination ? 'text-white' : 'text-blue-500'"
                                                :stroke-dasharray="`${getDonutProgress(getPlayerVoteCount(player.id))}, 100`"
                                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="3"
                                            />
                                        </svg>
                                        <div class="absolute inset-0 flex items-center justify-center p-1">
                                            <div class="flex h-6 w-6 items-center justify-center overflow-hidden rounded-full bg-void sm:h-9 sm:w-9">
                                                <img
                                                    v-if="player.avatar"
                                                    :src="avatarUrl(player.avatar)"
                                                    :alt="player.name"
                                                    class="h-full w-full object-cover"
                                                />
                                                <span v-else class="text-[10px] font-bold sm:text-xs">{{ getPlayerVoteCount(player.id) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="truncate text-xs font-bold sm:text-sm"
                                            :class="selectedPlayerId === player.id && !hasVotedElimination ? 'text-white' : 'text-text-primary'"
                                        >
                                            {{ player.name }}
                                        </p>
                                        <p
                                            class="text-[10px] sm:text-xs"
                                            :class="selectedPlayerId === player.id && !hasVotedElimination ? 'text-white/70' : 'text-text-tertiary'"
                                        >
                                            {{ getPlayerVoteCount(player.id) }}/{{ voteStats.totalVoters }}
                                        </p>
                                    </div>
                                </div>
                                <div v-if="myVoteTarget === player.id" class="absolute top-1.5 right-1.5 sm:top-2 sm:right-2">
                                    <Check class="h-3.5 w-3.5 text-red-400 sm:h-4 sm:w-4" />
                                </div>
                            </div>

                            <div
                                class="relative rounded-lg p-2 transition-all sm:rounded-xl sm:p-4"
                                :class="[
                                    selectedSkip && !hasVotedElimination
                                        ? 'cursor-pointer bg-blue-600 text-white shadow-lg shadow-blue-500/30'
                                        : 'glass-light',
                                    hasVotedElimination && myVoteTarget !== null ? 'cursor-default opacity-70' : 'cursor-pointer hover:bg-void-hover',
                                    myVoteTarget === null ? 'ring-2 ring-blue-500' : '',
                                ]"
                                @click="!hasVotedElimination && voteForSkip()"
                            >
                                <div class="flex items-center gap-2 sm:gap-3">
                                    <div class="relative h-10 w-10 flex-shrink-0 sm:h-14 sm:w-14">
                                        <svg class="h-full w-full -rotate-90" viewBox="0 0 36 36">
                                            <path
                                                class="text-void-hover"
                                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="3"
                                            />
                                            <path
                                                class="transition-all duration-500"
                                                :class="selectedSkip && !hasVotedElimination ? 'text-white' : 'text-blue-500'"
                                                :stroke-dasharray="`${getDonutProgress(voteStats.skipVotes)}, 100`"
                                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="3"
                                            />
                                        </svg>
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <span class="text-xs font-bold sm:text-sm">{{ voteStats.skipVotes }}</span>
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="text-xs font-bold sm:text-sm"
                                            :class="selectedSkip && !hasVotedElimination ? 'text-white' : 'text-text-primary'"
                                        >
                                            Skip
                                        </p>
                                        <p
                                            class="text-[10px] sm:text-xs"
                                            :class="selectedSkip && !hasVotedElimination ? 'text-white/70' : 'text-text-tertiary'"
                                        >
                                            {{ voteStats.skipVotes }}/{{ voteStats.totalVoters }}
                                        </p>
                                    </div>
                                </div>
                                <div v-if="myVoteTarget === null && hasVotedElimination" class="absolute top-1.5 right-1.5 sm:top-2 sm:right-2">
                                    <Check class="h-3.5 w-3.5 text-blue-400 sm:h-4 sm:w-4" />
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button
                                v-if="!hasVotedElimination"
                                @click="submitVote"
                                :disabled="selectedPlayerId === null && !selectedSkip"
                                class="btn-danger-filled flex-1 rounded-lg py-2.5 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-50 sm:rounded-xl sm:py-4 sm:text-sm"
                            >
                                Lock In Vote
                            </button>
                            <button
                                v-if="!hasVotedElimination"
                                @click="submitSkipVote"
                                class="btn-glass rounded-lg px-3 py-2.5 text-xs font-bold text-text-secondary hover:text-text-primary sm:rounded-xl sm:px-6 sm:text-sm"
                            >
                                Skip
                            </button>
                            <div
                                v-else
                                class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600/20 py-2.5 text-xs font-bold text-blue-400 ring-1 ring-blue-500/30 sm:rounded-xl sm:py-4 sm:text-sm"
                            >
                                <Check class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                Vote Locked
                            </div>
                            <button
                                v-if="current_player?.is_host"
                                @click="endVotingPhase"
                                class="btn-secondary rounded-lg px-3 py-2.5 text-xs font-bold sm:rounded-xl sm:px-6 sm:text-sm"
                            >
                                End
                            </button>
                        </div>
                    </div>

                    <!-- Elimination Reveal -->
                    <Transition name="bounce">
                        <div
                            v-if="showImpostorReveal && eliminatedPlayer"
                            class="glass rounded-lg border-2 p-3 sm:rounded-2xl sm:p-6"
                            :class="eliminatedPlayer.is_impostor ? 'border-blue-500/50 bg-blue-900/20' : 'border-red-500/50 bg-red-900/20'"
                        >
                            <div class="flex items-center gap-3 sm:gap-4">
                                <div
                                    class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg sm:h-16 sm:w-16 sm:rounded-2xl"
                                    :class="eliminatedPlayer.is_impostor ? 'bg-blue-500/20' : 'bg-red-500/20'"
                                >
                                    <component
                                        :is="eliminatedPlayer.is_impostor ? Check : X"
                                        class="h-5 w-5 sm:h-8 sm:w-8"
                                        :class="eliminatedPlayer.is_impostor ? 'text-blue-400' : 'text-red-400'"
                                    />
                                </div>
                                <div>
                                    <h3 class="text-sm font-black text-text-primary sm:text-xl">Elimination Result</h3>
                                    <p
                                        class="text-xs font-bold sm:text-base"
                                        :class="eliminatedPlayer.is_impostor ? 'text-blue-400' : 'text-red-400'"
                                    >
                                        {{ eliminatedPlayer.is_impostor ? 'THE IMPOSTOR!' : 'Not the impostor!' }}
                                    </p>
                                    <p class="text-xs text-text-secondary sm:text-sm">
                                        {{ eliminatedPlayer.name }} was
                                        {{ eliminatedPlayer.is_impostor ? 'eliminated. Crew wins!' : 'eliminated. Game continues...' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </section>

            <!-- Right Sidebar -->
            <aside class="hidden w-[260px] flex-shrink-0 flex-col border-l border-void-border bg-void-elevated/30 md:flex lg:w-72">
                <div v-if="!votingPhase && !gameResult" class="border-b border-void-border p-3">
                    <h3 class="mb-2 text-xs font-bold tracking-wider text-text-secondary uppercase">Actions</h3>
                    <div class="space-y-2">
                        <button
                            @click="handleVoteNow"
                            :disabled="gameState.vote_now.has_voted || currentPlayerData?.is_eliminated"
                            class="w-full rounded-lg p-2.5 text-left transition-all"
                            :class="
                                gameState.vote_now.has_voted
                                    ? 'cursor-not-allowed bg-blue-500/20 text-blue-400 ring-1 ring-blue-500/30'
                                    : 'glass-light hover:bg-void-hover'
                            "
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-md bg-red-500/20">
                                        <Swords class="h-3.5 w-3.5 text-red-400" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-text-primary">Vote Now</p>
                                    </div>
                                </div>
                                <span class="text-base font-black" :class="gameState.vote_now.has_voted ? 'text-blue-400' : 'text-text-primary'">
                                    {{ gameState.vote_now.count }}/{{ gameState.vote_now.threshold }}
                                </span>
                            </div>
                            <div v-if="!gameState.vote_now.has_voted" class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-void-hover">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-red-500 to-red-400 transition-all"
                                    :style="{ width: `${gameState.vote_now.progress}%` }"
                                ></div>
                            </div>
                        </button>

                        <button
                            @click="handleVoteReroll"
                            :disabled="gameState.reroll.has_voted || currentPlayerData?.is_eliminated"
                            class="w-full rounded-lg p-2.5 text-left transition-all"
                            :class="
                                gameState.reroll.has_voted
                                    ? 'cursor-not-allowed bg-blue-500/20 text-blue-400 ring-1 ring-blue-500/30'
                                    : 'glass-light hover:bg-void-hover'
                            "
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-md bg-blue-500/20">
                                        <Sparkles class="h-3.5 w-3.5 text-blue-400" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-text-primary">Reroll Word</p>
                                    </div>
                                </div>
                                <span class="text-base font-black" :class="gameState.reroll.has_voted ? 'text-blue-400' : 'text-text-primary'">
                                    {{ gameState.reroll.count }}/{{ gameState.reroll.threshold }}
                                </span>
                            </div>
                            <div v-if="!gameState.reroll.has_voted" class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-void-hover">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-blue-500 to-blue-400 transition-all"
                                    :style="{ width: `${gameState.reroll.progress}%` }"
                                ></div>
                            </div>
                        </button>
                    </div>
                </div>

                <div class="flex flex-1 flex-col overflow-hidden p-3">
                    <h3 class="mb-2 text-xs font-bold tracking-wider text-text-secondary uppercase">Players ({{ activePlayers.length }} alive)</h3>
                    <div class="flex-1 space-y-1.5 overflow-y-auto">
                        <div
                            v-for="player in gameState.players"
                            :key="player.id"
                            class="flex items-center gap-2 rounded-lg p-2 transition-all"
                            :class="[
                                player.id === gameState.current_turn_player_id
                                    ? 'bg-blue-500/10 ring-1 ring-blue-500/30'
                                    : 'glass-light hover:bg-void-hover',
                                player.is_eliminated ? 'opacity-40 grayscale' : '',
                            ]"
                        >
                            <div
                                class="flex h-8 w-8 flex-shrink-0 items-center justify-center overflow-hidden rounded-md text-sm font-bold"
                                :class="player.is_host ? 'bg-amber-500/20 text-amber-400' : 'bg-void-hover text-text-secondary'"
                            >
                                <img
                                    v-if="player.avatar"
                                    :src="avatarUrl(player.avatar)"
                                    :alt="player.name"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>{{ player.name.charAt(0).toUpperCase() }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="truncate text-sm font-medium text-text-primary">{{ player.name }}</span>
                                    <Crown v-if="player.is_host" class="h-3 w-3 flex-shrink-0 text-amber-400" />
                                </div>
                                <div class="flex items-center gap-1.5 text-xs">
                                    <span v-if="player.id === current_player.id" class="text-blue-400">You</span>
                                    <span v-if="player.is_eliminated" class="text-text-tertiary">Eliminated</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-void-border p-3">
                    <div class="flex items-center justify-between rounded-lg bg-void-hover p-2.5">
                        <div class="flex items-center gap-2">
                            <component
                                :is="votingPhase ? Swords : MessageSquare"
                                class="h-4 w-4"
                                :class="votingPhase ? 'text-red-400' : 'text-blue-400'"
                            />
                            <span class="text-sm text-text-secondary">{{ votingPhase ? 'Voting' : 'Discussion' }}</span>
                        </div>
                        <div class="flex items-center gap-1 text-sm text-text-secondary">
                            <Users class="h-4 w-4" />
                            {{ activePlayers.length }}/{{ gameState.players.length }}
                        </div>
                    </div>
                </div>
            </aside>
        </main>
    </div>
</template>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.3s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
    opacity: 0;
    transform: translate(-50%, -100%);
}

.slide-left-enter-active,
.slide-left-leave-active {
    transition: all 0.3s ease;
}

.slide-left-enter-from,
.slide-left-leave-to {
    opacity: 0;
    transform: translateX(-100%);
}

@media (min-width: 640px) {
    .slide-left-enter-from,
    .slide-left-leave-to {
        opacity: 0;
        transform: translateX(-20px);
    }
}

.bounce-enter-active {
    animation: bounce-in 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.bounce-leave-active {
    transition: all 0.3s ease;
}

.bounce-leave-to {
    opacity: 0;
    transform: scale(0.9);
}

@keyframes bounce-in {
    0% {
        opacity: 0;
        transform: scale(0.3);
    }
    50% {
        transform: scale(1.05);
    }
    70% {
        transform: scale(0.95);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}
</style>
