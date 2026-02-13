<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ShieldAlert,
    Users,
    MessageSquare,
    LogOut,
    Swords,
    Check,
    X,
    Trophy,
    Skull,
    ArrowRight,
    ChevronRight,
    Sparkles,
    Target,
    UserX,
    ChevronLeft,
} from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, computed } from 'vue';
import ChatBox from '@/components/ChatBox.vue';
import TurnIndicator from '@/components/TurnIndicator.vue';
import VoteButton from '@/components/VoteButton.vue';
import { endVoting, state, vote, nextTurn, voteNow, voteReroll } from '@/routes/game';
import { leave } from '@/routes/lobby';

interface Player {
    id: number;
    name: string;
    is_host: boolean;
    is_eliminated: boolean;
    turn_position: number;
}

interface VoteData {
    count: number;
    threshold: number;
    progress: number;
    has_voted: boolean;
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
    elimination_voted_player_ids?: number[];
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
    elimination_voted_player_ids: [],
});

const votingPhase = ref(false);
const selectedPlayerId = ref<number | null>(null);
const votes = ref<Record<number, number>>({});
const timer = ref(0);
const gameResult = ref<string | null>(null);
const eliminatedPlayer = ref<{ id: number; name: string; is_impostor: boolean } | null>(null);
const showImpostorReveal = ref(false);
const showChat = ref(false); // Default to false on mobile
const showDMNotification = ref(false);
const dmNotificationText = ref('');
const discussionTimeLeft = ref(0);

let polling: number | null = null;
let timerInterval: number | null = null;

const currentPlayerData = computed(() => {
    return gameState.value.players.find((p) => p.id === props.current_player.id);
});

const canVote = computed(() => {
    return votingPhase.value && !currentPlayerData.value?.is_eliminated;
});

const activePlayers = computed(() => {
    return gameState.value.players.filter((p) => !p.is_eliminated);
});

const isMyTurn = computed(() => {
    return gameState.value.current_turn_player_id === props.current_player.id;
});

const fetchGameState = async () => {
    try {
        const response = await axios.get(state.url(props.code));
        const oldVoteNowCount = gameState.value.vote_now?.count || 0;
        const oldRerollCount = gameState.value.reroll?.count || 0;

        gameState.value = response.data;

        // Sync voting phase from server so all players (not just host) see when voting ends
        votingPhase.value = !!gameState.value.voting_phase;

        // When server says voting just ended, show elimination reveal for everyone
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
            // Clear elimination reveal after a short delay so "Crew wins! New round..." is visible, then reset for new round
            setTimeout(() => {
                eliminatedPlayer.value = null;
                showImpostorReveal.value = false;
            }, 3500);
        }
    } catch (error) {
        console.error('Failed to fetch game state:', error);
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

const voteForPlayer = (playerId: number) => {
    if (!canVote.value || hasVotedElimination.value) return;
    selectedPlayerId.value = playerId;
};

const submitVote = async () => {
    if (hasVotedElimination.value) return;
    try {
        await axios.post(vote.url(props.code), {
            target_player_id: selectedPlayerId.value,
        });
        selectedPlayerId.value = null;
        await fetchGameState();
    } catch (error) {
        console.error('Failed to vote:', error);
    }
};

const submitSkipVote = async () => {
    if (hasVotedElimination.value) return;
    try {
        await axios.post(vote.url(props.code), {
            target_player_id: null,
        });
        selectedPlayerId.value = null;
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

        // Refresh state so host sees voting_phase false and all players/round updates
        await fetchGameState();

        // Hide elimination reveal after a few seconds
        setTimeout(() => {
            showImpostorReveal.value = false;
        }, 4000);
    } catch (error) {
        console.error('Failed to end voting:', error);
    }
};

const startVotingPhase = () => {
    votingPhase.value = true;
    timer.value = 30;
};

const skipVotingAndNextTurn = async () => {
    votingPhase.value = false;
    votes.value = {};
    selectedPlayerId.value = null;
    try {
        await axios.post(nextTurn.url(props.code));
        await fetchGameState();
    } catch (error) {
        console.error('Failed to skip voting:', error);
    }
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

const formatTimer = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
};

onMounted(async () => {
    await fetchGameState();
    polling = window.setInterval(fetchGameState, 2000);

    discussionTimeLeft.value = 60;
    timerInterval = window.setInterval(async () => {
        if (votingPhase.value) {
            if (timer.value > 0) {
                timer.value--;
            } else if (props.current_player?.is_host) {
                await skipVotingAndNextTurn();
            }
        } else {
            if (discussionTimeLeft.value > 0) {
                discussionTimeLeft.value--;
            }
        }
    }, 1000);
});

onUnmounted(() => {
    if (polling) clearInterval(polling);
    if (timerInterval) clearInterval(timerInterval);
});
</script>

<template>
    <Head :title="`Game - ${code}`" />

    <div class="flex h-screen w-screen flex-col overflow-hidden bg-gradient-to-br from-gray-950 via-gray-900 to-gray-950">
        <!-- Notification Toast -->
        <Transition name="slide-down">
            <div
                v-if="showDMNotification"
                class="glass fixed top-3 left-1/2 z-50 flex -translate-x-1/2 items-center gap-2 rounded-xl border border-purple-400/50 bg-purple-600/90 px-4 py-2 text-sm text-white shadow-2xl sm:top-4 sm:gap-3 sm:rounded-2xl sm:px-6 sm:py-3"
            >
                <Sparkles class="h-4 w-4 sm:h-5 sm:w-5" />
                {{ dmNotificationText }}
            </div>
        </Transition>

        <!-- Header HUD -->
        <header class="glass flex-shrink-0 border-b border-white/5 px-3 py-2 sm:px-4 sm:py-3">
            <div class="mx-auto flex max-w-7xl items-center justify-between">
                <!-- Logo & Code -->
                <div class="flex items-center gap-2 sm:gap-4">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-red-500 to-red-700 shadow-lg shadow-red-500/20 sm:h-10 sm:w-10 sm:rounded-xl"
                    >
                        <ShieldAlert class="h-4 w-4 text-white sm:h-5 sm:w-5" />
                    </div>
                    <div>
                        <h1 class="text-xs font-bold text-white sm:text-sm">Round {{ gameState.current_round }}</h1>
                        <p class="font-mono text-[10px] text-gray-400 sm:text-xs">{{ code }}</p>
                    </div>
                </div>

                <!-- Scoreboard -->
                <div class="glass flex items-center gap-0.5 rounded-xl p-0.5 sm:gap-1 sm:rounded-2xl sm:p-1">
                    <div
                        class="flex items-center gap-1.5 rounded-lg px-2 py-1 sm:gap-2 sm:rounded-xl sm:px-4 sm:py-2"
                        :class="gameResult === 'impostor_wins' ? 'bg-red-500/20' : ''"
                    >
                        <Skull class="h-3 w-3 text-red-400 sm:h-4 sm:w-4" />
                        <div class="text-center">
                            <div class="text-[8px] font-bold tracking-wider text-red-400 uppercase sm:text-[10px]">Impostor</div>
                            <div class="text-base leading-none font-black text-white sm:text-lg">{{ gameState.impostor_wins }}</div>
                        </div>
                    </div>
                    <div class="h-5 w-px bg-gray-700 sm:h-8"></div>
                    <div
                        class="flex items-center gap-1.5 rounded-lg px-2 py-1 sm:gap-2 sm:rounded-xl sm:px-4 sm:py-2"
                        :class="gameResult === 'crew_wins' ? 'bg-blue-500/20' : ''"
                    >
                        <Trophy class="h-3 w-3 text-blue-400 sm:h-4 sm:w-4" />
                        <div class="text-center">
                            <div class="text-[8px] font-bold tracking-wider text-blue-400 uppercase sm:text-[10px]">Crew</div>
                            <div class="text-base leading-none font-black text-white sm:text-lg">{{ gameState.crew_wins }}</div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-1 sm:gap-2">
                    <button
                        @click="showChat = !showChat"
                        class="btn-glass relative rounded-lg p-2 sm:rounded-xl sm:p-2.5"
                        :class="showChat ? 'bg-gray-700/50 text-white' : 'text-gray-400'"
                    >
                        <MessageSquare class="h-4 w-4 sm:h-5 sm:w-5" />
                        <span
                            v-if="unread_dm_count > 0"
                            class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white"
                        >
                            {{ unread_dm_count }}
                        </span>
                    </button>
                    <button @click="leaveGame" class="btn-glass rounded-lg p-2 text-red-400 hover:text-red-300 sm:rounded-xl sm:p-2.5">
                        <LogOut class="h-4 w-4 sm:h-5 sm:w-5" />
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Game Area -->
        <main class="relative flex-1 overflow-hidden p-2 sm:p-4">
            <div class="mx-auto flex h-full max-w-7xl gap-2 sm:gap-4">
                <!-- Chat Sidebar / Mobile Drawer -->
                <Transition name="slide-left">
                    <div
                        v-show="showChat"
                        class="absolute inset-y-0 left-0 z-40 h-full w-[280px] flex-shrink-0 sm:relative sm:inset-auto sm:block sm:w-72 lg:w-80"
                        :class="showChat ? 'block' : 'hidden sm:block'"
                    >
                        <!-- Mobile overlay backdrop -->
                        <div v-if="showChat" class="fixed inset-0 z-[-1] bg-black/50 sm:hidden" @click="showChat = false"></div>
                        <div class="h-full pt-2 pl-2 sm:pt-0 sm:pl-0">
                            <!-- Mobile close button -->
                            <div class="mb-2 flex items-center justify-between sm:hidden">
                                <span class="text-sm font-bold text-white">Chat</span>
                                <button @click="showChat = false" class="btn-glass rounded-lg p-1.5">
                                    <ChevronLeft class="h-4 w-4" />
                                </button>
                            </div>
                            <ChatBox :code="code" :current-player-id="current_player.id" :players="gameState.players" />
                        </div>
                    </div>
                </Transition>

                <!-- Game Content -->
                <div class="flex min-w-0 flex-1 flex-col gap-2 sm:gap-4">
                    <!-- Victory Banner -->
                    <Transition name="bounce">
                        <div
                            v-if="gameResult"
                            class="glass flex-shrink-0 rounded-xl p-3 text-center sm:rounded-2xl sm:p-5"
                            :class="gameResult === 'crew_wins' ? 'border-blue-500/30 bg-blue-900/30' : 'border-red-500/30 bg-red-900/30'"
                        >
                            <div class="flex items-center justify-center gap-3 sm:gap-4">
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-xl sm:h-16 sm:w-16 sm:rounded-2xl"
                                    :class="gameResult === 'crew_wins' ? 'bg-blue-500/20' : 'bg-red-500/20'"
                                >
                                    <component
                                        :is="gameResult === 'crew_wins' ? Trophy : Skull"
                                        class="h-6 w-6"
                                        :class="gameResult === 'crew_wins' ? 'text-blue-400' : 'text-red-400'"
                                    />
                                </div>
                                <div class="text-left">
                                    <h2 class="text-lg font-black text-white sm:text-2xl">
                                        {{ gameResult === 'crew_wins' ? 'Crew Wins!' : 'Impostor Wins!' }}
                                    </h2>
                                    <p v-if="eliminatedPlayer" class="text-xs text-gray-300 sm:text-sm">
                                        {{ eliminatedPlayer.name }} was {{ eliminatedPlayer.is_impostor ? 'the Impostor' : 'a Crew member' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </Transition>

                    <!-- Top Section: Turn + Word Card -->
                    <div class="flex flex-shrink-0 flex-col gap-2 sm:flex-row sm:gap-4">
                        <!-- Turn Indicator - Hidden on mobile when voting -->
                        <div v-if="!gameResult && !votingPhase" class="glass-card w-full flex-shrink-0 rounded-xl p-3 sm:w-64 sm:rounded-2xl sm:p-4">
                            <TurnIndicator
                                :players="gameState.players"
                                :current-turn-player-id="gameState.current_turn_player_id"
                                :current-player-id="current_player.id"
                                @next-turn="handleNextTurn"
                            />
                        </div>

                        <!-- Voting Panel -->
                        <div v-else-if="votingPhase" class="glass-card w-full flex-1 rounded-xl p-3 sm:rounded-2xl sm:p-4">
                            <div class="mb-2 flex items-center justify-between gap-2 sm:mb-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-6 w-6 items-center justify-center rounded-md bg-red-500/20 sm:h-8 sm:w-8 sm:rounded-lg">
                                        <Target class="h-3 w-3 text-red-400 sm:h-4 sm:w-4" />
                                    </div>
                                    <h3 class="text-sm font-bold text-white sm:text-base">
                                        {{ hasVotedElimination ? 'Vote Locked In' : 'Vote to Eliminate' }}
                                    </h3>
                                </div>
                                <span class="text-[10px] text-gray-400 sm:text-xs">
                                    {{ (gameState.elimination_voted_player_ids || []).length }}/{{ activePlayers.length }} voted
                                </span>
                            </div>
                            <!-- Who has voted (for active players) -->
                            <div class="mb-2 flex flex-wrap gap-1 sm:mb-3">
                                <span
                                    v-for="player in activePlayers"
                                    :key="player.id"
                                    class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] sm:rounded-lg sm:px-2 sm:py-1 sm:text-xs"
                                    :class="
                                        (gameState.elimination_voted_player_ids || []).includes(player.id)
                                            ? 'bg-green-500/20 text-green-400'
                                            : 'bg-gray-700/50 text-gray-500'
                                    "
                                >
                                    {{ player.name.charAt(0).toUpperCase() }}
                                    <Check v-if="(gameState.elimination_voted_player_ids || []).includes(player.id)" class="h-2.5 w-2.5 sm:h-3 sm:w-3" />
                                </span>
                            </div>
                            <!-- Vote targets -->
                            <div class="mb-2 grid grid-cols-2 gap-1.5 sm:mb-3 sm:grid-cols-4 sm:gap-2">
                                <button
                                    v-for="player in activePlayers"
                                    :key="player.id"
                                    @click="voteForPlayer(player.id)"
                                    :disabled="hasVotedElimination"
                                    class="flex items-center gap-1.5 rounded-lg p-1.5 text-xs transition-all disabled:cursor-not-allowed disabled:opacity-60 sm:gap-2 sm:rounded-xl sm:p-2 sm:text-sm"
                                    :class="selectedPlayerId === player.id ? 'bg-red-600 text-white' : 'glass-light hover:bg-gray-700/50'"
                                >
                                    <div
                                        class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-md bg-gray-700 text-xs font-bold sm:h-8 sm:w-8 sm:rounded-lg sm:text-sm"
                                    >
                                        {{ player.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <span class="truncate">{{ player.name }}</span>
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-1.5 sm:gap-2">
                                <button
                                    v-if="!hasVotedElimination"
                                    @click="submitVote"
                                    :disabled="selectedPlayerId === null"
                                    class="flex flex-1 min-w-0 items-center justify-center gap-1.5 rounded-lg bg-red-600 py-2 text-xs font-bold text-white transition-all hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50 sm:gap-2 sm:rounded-xl sm:py-2.5 sm:text-sm"
                                >
                                    <Check class="h-3.5 w-3.5 flex-shrink-0 sm:h-4 sm:w-4" />
                                    <span class="truncate sm:inline">Lock In Vote</span>
                                </button>
                                <button
                                    v-if="!hasVotedElimination"
                                    @click="submitSkipVote"
                                    class="flex flex-shrink-0 items-center justify-center gap-1.5 rounded-lg border border-gray-500 bg-gray-800/50 px-3 py-2 text-xs font-bold text-gray-300 transition-all hover:border-gray-400 hover:bg-gray-700/50 hover:text-white sm:gap-2 sm:rounded-xl sm:px-4 sm:py-2.5 sm:text-sm"
                                    title="Don't vote to eliminate anyone"
                                >
                                    Skip (no vote)
                                </button>
                                <div
                                    v-else
                                    class="flex flex-1 min-w-0 items-center justify-center gap-1.5 rounded-lg bg-green-600/30 py-2 text-xs font-bold text-green-400 sm:gap-2 sm:rounded-xl sm:py-2.5 sm:text-sm"
                                >
                                    <Check class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                    Vote Locked
                                </div>
                                <button
                                    v-if="current_player?.is_host"
                                    @click="endVotingPhase"
                                    class="flex-shrink-0 rounded-lg bg-amber-600 px-3 py-2 text-xs font-bold text-white transition-all hover:bg-amber-700 sm:rounded-xl sm:px-4 sm:py-2.5 sm:text-sm"
                                >
                                    <span class="sm:hidden">End</span>
                                    <span class="hidden sm:inline">End Voting</span>
                                </button>
                            </div>
                        </div>

                        <!-- Word Card -->
                        <div
                            class="glass-card w-full flex-1 rounded-xl p-3 sm:rounded-2xl sm:p-4"
                            :class="gameState.is_impostor ? 'role-card-impostor' : 'role-card-crew'"
                        >
                            <div class="mb-2 flex items-center justify-between sm:mb-3">
                                <div class="flex items-center gap-1.5 sm:gap-2">
                                    <component
                                        :is="gameState.is_impostor ? Skull : ShieldAlert"
                                        class="h-4 w-4 sm:h-5 sm:w-5"
                                        :class="gameState.is_impostor ? 'text-red-400' : 'text-blue-400'"
                                    />
                                    <span class="text-xs font-bold text-white sm:text-sm">Your Secret Word</span>
                                </div>
                                <span class="badge text-[10px] sm:text-xs" :class="gameState.is_impostor ? 'badge-impostor' : 'badge-crew'">
                                    {{ gameState.is_impostor ? 'IMPOSTOR' : 'CREW' }}
                                </span>
                            </div>

                            <div class="py-2 text-center sm:py-3">
                                <div class="mb-1 text-2xl font-black text-white sm:text-3xl" :class="gameState.is_impostor ? 'text-shadow-glow' : ''">
                                    {{ gameState.word || '???' }}
                                </div>
                                <div v-if="gameState.word_category" class="text-[10px] text-gray-400 sm:text-xs">
                                    {{ gameState.word_category }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Bar -->
                    <div class="glass flex flex-shrink-0 items-center justify-between rounded-xl p-2 sm:rounded-2xl sm:p-3">
                        <div class="flex items-center gap-2 sm:gap-4">
                            <div
                                class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 sm:gap-2 sm:rounded-xl sm:px-3 sm:py-2"
                                :class="votingPhase ? 'bg-red-500/20' : 'bg-green-500/20'"
                            >
                                <component
                                    :is="votingPhase ? Swords : MessageSquare"
                                    class="h-3.5 w-3.5 sm:h-4 sm:w-4"
                                    :class="votingPhase ? 'text-red-400' : 'text-green-400'"
                                />
                                <span class="text-xs font-semibold text-white sm:text-sm">
                                    {{ votingPhase ? 'Voting' : 'Discussion' }}
                                </span>
                            </div>

                            <div class="hidden items-center gap-1.5 text-gray-400 sm:flex sm:gap-2">
                                <Users class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                <span class="text-xs sm:text-sm">{{ activePlayers.length }} alive</span>
                            </div>
                        </div>

                        <div
                            class="font-mono text-xl font-black sm:text-2xl"
                            :class="votingPhase && timer < 10 ? 'animate-pulse text-red-400' : 'text-white'"
                        >
                            {{ formatTimer(votingPhase ? timer : discussionTimeLeft) }}
                        </div>
                    </div>

                    <!-- Vote Buttons -->
                    <div v-if="!votingPhase && !gameResult" class="grid flex-shrink-0 grid-cols-2 gap-2 sm:gap-4">
                        <VoteButton
                            :vote-data="gameState.vote_now"
                            type="vote-now"
                            :disabled="currentPlayerData?.is_eliminated"
                            @vote="handleVoteNow"
                        />
                        <VoteButton
                            :vote-data="gameState.reroll"
                            type="reroll"
                            :disabled="currentPlayerData?.is_eliminated || gameState.is_impostor"
                            @vote="handleVoteReroll"
                        />
                    </div>

                    <!-- Players Grid -->
                    <div class="glass-card flex flex-1 flex-col overflow-hidden rounded-xl p-2.5 sm:rounded-2xl sm:p-4">
                        <div class="mb-2 flex flex-shrink-0 items-center gap-1.5 sm:mb-3 sm:gap-2">
                            <Users class="h-3.5 w-3.5 text-gray-400 sm:h-4 sm:w-4" />
                            <h3 class="text-xs font-bold text-white sm:text-sm">Players</h3>
                            <span class="text-[10px] text-gray-500 sm:text-xs">({{ activePlayers.length }})</span>
                        </div>
                        <!-- Mobile: 2 columns, Desktop: 4 columns -->
                        <div class="grid grid-cols-2 gap-1.5 overflow-y-auto sm:grid-cols-3 sm:gap-2 md:grid-cols-4">
                            <div
                                v-for="player in gameState.players"
                                :key="player.id"
                                class="glass-light flex items-center gap-1.5 rounded-lg p-1.5 transition-all sm:gap-2 sm:rounded-xl sm:p-2"
                                :class="[
                                    player.id === gameState.current_turn_player_id ? 'ring-1 ring-amber-400/50 sm:ring-2' : '',
                                    player.is_eliminated ? 'opacity-40 grayscale' : '',
                                ]"
                            >
                                <div
                                    class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md text-xs font-bold sm:h-10 sm:w-10 sm:rounded-lg sm:text-sm"
                                    :class="player.is_host ? 'bg-amber-500/20 text-amber-400' : 'bg-gray-700/50 text-gray-300'"
                                >
                                    {{ player.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-xs font-medium text-white sm:text-sm">{{ player.name }}</div>
                                    <div class="flex items-center gap-1">
                                        <span v-if="player.is_host" class="text-[8px] text-amber-400 sm:text-[10px]">HOST</span>
                                        <span v-if="player.id === current_player.id" class="text-[8px] text-blue-400 sm:text-[10px]">YOU</span>
                                    </div>
                                </div>
                                <div
                                    v-if="player.id === gameState.current_turn_player_id"
                                    class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-400 sm:h-2 sm:w-2"
                                ></div>
                                <UserX v-if="player.is_eliminated" class="h-3.5 w-3.5 text-gray-500 sm:h-4 sm:w-4" />
                            </div>
                        </div>
                    </div>

                    <!-- End Turn Button -->
                    <Transition name="slide-up">
                        <div v-if="isMyTurn && !votingPhase && !gameResult" class="flex-shrink-0">
                            <button
                                @click="handleNextTurn"
                                class="btn-glass flex w-full items-center justify-center gap-1.5 rounded-xl bg-gradient-to-r from-amber-600 to-orange-600 py-3 text-sm font-bold text-white shadow-lg shadow-amber-600/20 transition-all hover:from-amber-500 hover:to-orange-500 sm:gap-2 sm:rounded-2xl sm:py-4"
                            >
                                <ArrowRight class="h-4 w-4 sm:h-5 sm:w-5" />
                                <span class="sm:hidden">End Turn</span>
                                <span class="hidden sm:inline">End My Turn</span>
                                <ChevronRight class="h-4 w-4 sm:h-5 sm:w-5" />
                            </button>
                        </div>
                    </Transition>

                    <!-- Impostor Reveal -->
                    <Transition name="bounce">
                        <div
                            v-if="showImpostorReveal && eliminatedPlayer"
                            class="glass flex-shrink-0 rounded-xl p-2.5 sm:rounded-2xl sm:p-4"
                            :class="eliminatedPlayer.is_impostor ? 'border-green-500/30 bg-green-900/30' : 'border-red-500/30 bg-red-900/30'"
                        >
                            <div class="flex items-center gap-3 sm:gap-4">
                                <div
                                    class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg sm:h-12 sm:w-12 sm:rounded-xl"
                                    :class="eliminatedPlayer.is_impostor ? 'bg-green-500/20' : 'bg-red-500/20'"
                                >
                                    <component
                                        :is="eliminatedPlayer.is_impostor ? Check : X"
                                        class="h-5 w-5 sm:h-6 sm:w-6"
                                        :class="eliminatedPlayer.is_impostor ? 'text-green-400' : 'text-red-400'"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-xs font-bold text-white sm:text-sm">Elimination Result</h3>
                                    <p class="text-xs font-bold sm:text-sm" :class="eliminatedPlayer.is_impostor ? 'text-green-400' : 'text-red-400'">
                                        {{ eliminatedPlayer.is_impostor ? 'THE IMPOSTOR!' : 'Not the impostor!' }}
                                    </p>
                                    <p class="text-[10px] text-gray-400 sm:text-xs">
                                        {{ eliminatedPlayer.name }} was
                                        {{
                                            eliminatedPlayer.is_impostor
                                                ? 'eliminated. Crew wins! New round starting...'
                                                : 'eliminated. Their turn is skipped.'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
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

.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.3s ease;
}

.slide-up-enter-from,
.slide-up-leave-to {
    opacity: 0;
    transform: translateY(20px);
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
