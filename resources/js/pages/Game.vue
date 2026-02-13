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
    vote_now: VoteData;
    reroll: VoteData;
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
});

const votingPhase = ref(false);
const selectedPlayerId = ref<number | null>(null);
const votes = ref<Record<number, number>>({});
const timer = ref(0);
const gameResult = ref<string | null>(null);
const eliminatedPlayer = ref<{ id: number; name: string; is_impostor: boolean } | null>(null);
const showImpostorReveal = ref(false);
const showChat = ref(true);
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

computed(() => {
    return gameState.value.impostor_wins + gameState.value.crew_wins;
});

const fetchGameState = async () => {
    try {
        const response = await axios.get(state.url(props.code));
        const oldVoteNowCount = gameState.value.vote_now?.count || 0;
        const oldRerollCount = gameState.value.reroll?.count || 0;

        gameState.value = response.data;

        if (oldVoteNowCount < gameState.value.vote_now.threshold && gameState.value.vote_now.count >= gameState.value.vote_now.threshold) {
            startVotingPhase();
        }

        if (oldRerollCount < gameState.value.reroll.threshold && gameState.value.reroll.count >= gameState.value.reroll.threshold) {
            showNotification('Word has been rerolled!');
        }

        if (response.data.status === 'finished' && !gameResult.value) {
            checkGameEnd();
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

const checkGameEnd = () => {
    const impostorsRemaining = gameState.value.players.filter((p) => p.is_impostor && !p.is_eliminated).length;
    const crewRemaining = gameState.value.players.filter((p) => !p.is_impostor && !p.is_eliminated).length;

    if (impostorsRemaining === 0) {
        gameResult.value = 'crew_wins';
    } else if (impostorsRemaining >= crewRemaining) {
        gameResult.value = 'impostor_wins';
    }
};

const voteForPlayer = (playerId: number) => {
    if (!canVote.value) return;
    selectedPlayerId.value = playerId;
};

const submitVote = async () => {
    if (selectedPlayerId.value === null) return;
    try {
        await axios.post(vote.url(props.code), {
            target_player_id: selectedPlayerId.value,
        });
        votes.value[selectedPlayerId.value] = (votes.value[selectedPlayerId.value] || 0) + 1;
        selectedPlayerId.value = null;
    } catch (error) {
        console.error('Failed to vote:', error);
    }
};

const endVotingPhase = async () => {
    try {
        const response = await axios.post(endVoting.url(props.code));
        eliminatedPlayer.value = response.data.eliminated;
        gameResult.value = response.data.game_result;

        if (response.data.was_impostor !== undefined) {
            showImpostorReveal.value = true;
        }

        setTimeout(() => {
            votingPhase.value = false;
            showImpostorReveal.value = false;
        }, 3000);
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
    } catch (error) {
        console.error('Failed to vote reroll:', error);
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
            } else {
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
                class="glass fixed top-4 left-1/2 z-50 flex -translate-x-1/2 items-center gap-3 rounded-2xl border border-purple-400/50 bg-purple-600/90 px-6 py-3 text-white shadow-2xl"
            >
                <Sparkles class="h-5 w-5" />
                {{ dmNotificationText }}
            </div>
        </Transition>

        <!-- Header HUD -->
        <header class="glass flex-shrink-0 border-b border-white/5 px-4 py-3">
            <div class="mx-auto flex max-w-7xl items-center justify-between">
                <!-- Logo & Code -->
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-red-500 to-red-700 shadow-lg shadow-red-500/20"
                    >
                        <ShieldAlert class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <h1 class="text-sm font-bold text-white">Round {{ gameState.current_round }}</h1>
                        <p class="font-mono text-xs text-gray-400">{{ code }}</p>
                    </div>
                </div>

                <!-- Scoreboard -->
                <div class="glass flex items-center gap-1 rounded-2xl p-1">
                    <div class="flex items-center gap-2 rounded-xl px-4 py-2" :class="gameResult === 'impostor_wins' ? 'bg-red-500/20' : ''">
                        <Skull class="h-4 w-4 text-red-400" />
                        <div class="text-center">
                            <div class="text-[10px] font-bold tracking-wider text-red-400 uppercase">Impostor</div>
                            <div class="text-lg leading-none font-black text-white">{{ gameState.impostor_wins }}</div>
                        </div>
                    </div>
                    <div class="h-8 w-px bg-gray-700"></div>
                    <div class="flex items-center gap-2 rounded-xl px-4 py-2" :class="gameResult === 'crew_wins' ? 'bg-blue-500/20' : ''">
                        <Trophy class="h-4 w-4 text-blue-400" />
                        <div class="text-center">
                            <div class="text-[10px] font-bold tracking-wider text-blue-400 uppercase">Crew</div>
                            <div class="text-lg leading-none font-black text-white">{{ gameState.crew_wins }}</div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-2">
                    <button
                        @click="showChat = !showChat"
                        class="btn-glass rounded-xl p-2.5"
                        :class="showChat ? 'bg-gray-700/50 text-white' : 'text-gray-400'"
                    >
                        <MessageSquare class="h-5 w-5" />
                    </button>
                    <button @click="leaveGame" class="btn-glass rounded-xl p-2.5 text-red-400 hover:text-red-300">
                        <LogOut class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Game Area -->
        <main class="flex-1 overflow-hidden p-4">
            <div class="mx-auto flex h-full max-w-7xl gap-4">
                <!-- Chat Sidebar -->
                <Transition name="slide-left">
                    <div v-show="showChat" class="h-full w-80 flex-shrink-0">
                        <ChatBox :code="code" :current-player-id="current_player.id" :players="gameState.players" />
                    </div>
                </Transition>

                <!-- Game Content -->
                <div class="flex min-w-0 flex-1 flex-col gap-4">
                    <!-- Victory Banner -->
                    <Transition name="bounce">
                        <div
                            v-if="gameResult"
                            class="glass flex-shrink-0 rounded-2xl p-5 text-center"
                            :class="gameResult === 'crew_wins' ? 'border-blue-500/30 bg-blue-900/30' : 'border-red-500/30 bg-red-900/30'"
                        >
                            <div class="flex items-center justify-center gap-4">
                                <div
                                    class="flex h-16 w-16 items-center justify-center rounded-2xl"
                                    :class="gameResult === 'crew_wins' ? 'bg-blue-500/20' : 'bg-red-500/20'"
                                >
                                    <component
                                        :is="gameResult === 'crew_wins' ? Trophy : Skull"
                                        class="h-8 w-8"
                                        :class="gameResult === 'crew_wins' ? 'text-blue-400' : 'text-red-400'"
                                    />
                                </div>
                                <div class="text-left">
                                    <h2 class="text-2xl font-black text-white">
                                        {{ gameResult === 'crew_wins' ? 'Crew Wins!' : 'Impostor Wins!' }}
                                    </h2>
                                    <p v-if="eliminatedPlayer" class="text-gray-300">
                                        {{ eliminatedPlayer.name }} was {{ eliminatedPlayer.is_impostor ? 'the Impostor' : 'a Crew member' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </Transition>

                    <!-- Top Section: Turn + Word Card -->
                    <div class="flex flex-shrink-0 gap-4">
                        <!-- Turn Indicator -->
                        <div v-if="!gameResult && !votingPhase" class="glass-card w-64 flex-shrink-0 rounded-2xl p-4">
                            <TurnIndicator
                                :players="gameState.players"
                                :current-turn-player-id="gameState.current_turn_player_id"
                                :current-player-id="current_player.id"
                                @next-turn="handleNextTurn"
                            />
                        </div>

                        <!-- Voting Panel -->
                        <div v-else-if="votingPhase" class="glass-card flex-1 rounded-2xl p-4">
                            <div class="mb-3 flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-500/20">
                                    <Target class="h-4 w-4 text-red-400" />
                                </div>
                                <h3 class="font-bold text-white">Vote to Eliminate</h3>
                            </div>
                            <div class="mb-3 grid grid-cols-4 gap-2">
                                <button
                                    v-for="player in activePlayers"
                                    :key="player.id"
                                    @click="voteForPlayer(player.id)"
                                    class="flex items-center gap-2 rounded-xl p-2 transition-all"
                                    :class="selectedPlayerId === player.id ? 'bg-red-600 text-white' : 'glass-light hover:bg-gray-700/50'"
                                >
                                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-700 text-sm font-bold">
                                        {{ player.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <span class="truncate text-sm">{{ player.name }}</span>
                                </button>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    @click="submitVote"
                                    :disabled="selectedPlayerId === null"
                                    class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-red-600 py-2.5 font-bold text-white transition-all hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <Check class="h-4 w-4" />
                                    Confirm Vote
                                </button>
                                <button
                                    v-if="current_player?.is_host"
                                    @click="endVotingPhase"
                                    class="rounded-xl bg-amber-600 px-4 py-2.5 font-bold text-white transition-all hover:bg-amber-700"
                                >
                                    End
                                </button>
                            </div>
                        </div>

                        <!-- Word Card -->
                        <div class="glass-card flex-1 rounded-2xl p-4" :class="gameState.is_impostor ? 'role-card-impostor' : 'role-card-crew'">
                            <div class="mb-3 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <component
                                        :is="gameState.is_impostor ? Skull : ShieldAlert"
                                        class="h-5 w-5"
                                        :class="gameState.is_impostor ? 'text-red-400' : 'text-blue-400'"
                                    />
                                    <span class="text-sm font-bold text-white">Your Secret Word</span>
                                </div>
                                <span class="badge" :class="gameState.is_impostor ? 'badge-impostor' : 'badge-crew'">
                                    {{ gameState.is_impostor ? 'IMPOSTOR' : 'CREW' }}
                                </span>
                            </div>

                            <div class="py-3 text-center">
                                <div class="mb-1 text-3xl font-black text-white" :class="gameState.is_impostor ? 'text-shadow-glow' : ''">
                                    {{ gameState.word || '???' }}
                                </div>
                                <div v-if="gameState.word_category" class="text-xs text-gray-400">
                                    {{ gameState.word_category }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Bar -->
                    <div class="glass flex flex-shrink-0 items-center justify-between rounded-2xl p-3">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2 rounded-xl px-3 py-2" :class="votingPhase ? 'bg-red-500/20' : 'bg-green-500/20'">
                                <component
                                    :is="votingPhase ? Swords : MessageSquare"
                                    class="h-4 w-4"
                                    :class="votingPhase ? 'text-red-400' : 'text-green-400'"
                                />
                                <span class="text-sm font-semibold text-white">
                                    {{ votingPhase ? 'Voting Phase' : 'Discussion Phase' }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2 text-gray-400">
                                <Users class="h-4 w-4" />
                                <span class="text-sm">{{ activePlayers.length }} alive</span>
                            </div>
                        </div>

                        <div class="font-mono text-2xl font-black" :class="votingPhase && timer < 10 ? 'animate-pulse text-red-400' : 'text-white'">
                            {{ formatTimer(votingPhase ? timer : discussionTimeLeft) }}
                        </div>
                    </div>

                    <!-- Vote Buttons -->
                    <div v-if="!votingPhase && !gameResult" class="grid flex-shrink-0 grid-cols-2 gap-4">
                        <VoteButton
                            :vote-data="gameState.vote_now"
                            type="vote-now"
                            :disabled="currentPlayerData?.is_eliminated"
                            @vote="handleVoteNow"
                        />
                        <VoteButton
                            :vote-data="gameState.reroll"
                            type="reroll"
                            :disabled="currentPlayerData?.is_eliminated"
                            @vote="handleVoteReroll"
                        />
                    </div>

                    <!-- Players Grid -->
                    <div class="glass-card flex flex-1 flex-col overflow-hidden rounded-2xl p-4">
                        <div class="mb-3 flex flex-shrink-0 items-center gap-2">
                            <Users class="h-4 w-4 text-gray-400" />
                            <h3 class="text-sm font-bold text-white">Players</h3>
                            <span class="text-xs text-gray-500">({{ activePlayers.length }} active)</span>
                        </div>
                        <div class="grid grid-cols-4 gap-2 overflow-y-auto">
                            <div
                                v-for="player in gameState.players"
                                :key="player.id"
                                class="glass-light flex items-center gap-2 rounded-xl p-2 transition-all"
                                :class="[
                                    player.id === gameState.current_turn_player_id ? 'ring-2 ring-amber-400/50' : '',
                                    player.is_eliminated ? 'opacity-40 grayscale' : '',
                                ]"
                            >
                                <div
                                    class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg text-sm font-bold"
                                    :class="player.is_host ? 'bg-amber-500/20 text-amber-400' : 'bg-gray-700/50 text-gray-300'"
                                >
                                    {{ player.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-white">{{ player.name }}</div>
                                    <div class="flex items-center gap-1">
                                        <span v-if="player.is_host" class="text-[10px] text-amber-400">HOST</span>
                                        <span v-if="player.id === current_player.id" class="text-[10px] text-blue-400">YOU</span>
                                    </div>
                                </div>
                                <div
                                    v-if="player.id === gameState.current_turn_player_id"
                                    class="h-2 w-2 animate-pulse rounded-full bg-amber-400"
                                ></div>
                                <UserX v-if="player.is_eliminated" class="h-4 w-4 text-gray-500" />
                            </div>
                        </div>
                    </div>

                    <!-- End Turn Button -->
                    <Transition name="slide-up">
                        <div v-if="isMyTurn && !votingPhase && !gameResult" class="flex-shrink-0">
                            <button
                                @click="handleNextTurn"
                                class="btn-glass flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-amber-600 to-orange-600 py-4 font-bold text-white shadow-lg shadow-amber-600/20 transition-all hover:from-amber-500 hover:to-orange-500"
                            >
                                <ArrowRight class="h-5 w-5" />
                                End My Turn
                                <ChevronRight class="h-5 w-5" />
                            </button>
                        </div>
                    </Transition>

                    <!-- Impostor Reveal -->
                    <Transition name="bounce">
                        <div
                            v-if="showImpostorReveal && eliminatedPlayer"
                            class="glass flex-shrink-0 rounded-2xl p-4"
                            :class="eliminatedPlayer.is_impostor ? 'border-green-500/30 bg-green-900/30' : 'border-red-500/30 bg-red-900/30'"
                        >
                            <div class="flex items-center gap-4">
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-xl"
                                    :class="eliminatedPlayer.is_impostor ? 'bg-green-500/20' : 'bg-red-500/20'"
                                >
                                    <component
                                        :is="eliminatedPlayer.is_impostor ? Check : X"
                                        class="h-6 w-6"
                                        :class="eliminatedPlayer.is_impostor ? 'text-green-400' : 'text-red-400'"
                                    />
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-white">Elimination Result</h3>
                                    <p class="text-sm font-bold" :class="eliminatedPlayer.is_impostor ? 'text-green-400' : 'text-red-400'">
                                        {{ eliminatedPlayer.name }} was {{ eliminatedPlayer.is_impostor ? 'THE IMPOSTOR!' : 'a Crew Member' }}
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
    transform: translateX(-20px);
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
