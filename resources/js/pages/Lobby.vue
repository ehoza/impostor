<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Users,
    Settings,
    LogOut,
    Crown,
    Copy,
    Check,
    Play,
    User,
    Clock,
    ShieldAlert,
    UsersRound,
    Zap,
    Flame,
    ChevronRight,
    Sparkles,
    AlertCircle,
    Save,
    X,
    LogIn,
    ArrowRight,
} from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import { play, start, state } from '@/routes/game';
import { leave, settings } from '@/routes/lobby';

const props = defineProps<{
    lobby: {
        code: string;
        name: string | null;
        status: string;
        settings: {
            impostor_count: number;
            max_players: number;
            discussion_time: number;
            voting_time: number;
            word_difficulty: number;
        };
        player_count: number;
    };
    players: Array<{
        id: number;
        name: string;
        is_host: boolean;
        is_ready: boolean;
    }>;
    current_player: {
        id: number;
        name: string;
        is_host: boolean;
    } | null;
}>();

const polling = ref<number | null>(null);
const showSettings = ref(false);
const copiedCode = ref(false);

const settingsForm = useForm({
    settings: {
        impostor_count: props.lobby?.settings.impostor_count ?? 1,
        max_players: props.lobby?.settings.max_players ?? 10,
        discussion_time: props.lobby?.settings.discussion_time ?? 60,
        voting_time: props.lobby?.settings.voting_time ?? 30,
        word_difficulty: props.lobby?.settings.word_difficulty ?? 3,
    },
});

const joinForm = useForm({
    player_name: '',
});

const lobbyLink = `https://impostor.its-eddy.com/lobby/${props.lobby.code}`;

const copyCode = async () => {
    try {
        await navigator.clipboard.writeText(lobbyLink);
        copiedCode.value = true;
        setTimeout(() => (copiedCode.value = false), 2000);
    } catch {
        copiedCode.value = false;
    }
};

const startGame = () => {
    router.post(start.url(props.lobby.code));
};

const leaveLobby = () => {
    if (confirm('Are you sure you want to leave the lobby?')) {
        router.post(leave.url(props.lobby.code));
    }
};

const updateSettings = () => {
    settingsForm.post(settings.url(props.lobby.code), {
        onSuccess: () => {
            showSettings.value = false;
        },
    });
};

const pollLobby = async () => {
    try {
        await router.reload({ only: ['lobby', 'players'] });

        const response = await axios.get(state.url(props.lobby.code));
        if (response.data.status === 'playing') {
            window.location.href = play.url(props.lobby.code);
        }
    } catch (error) {
        console.error('Polling error:', error);
    }
};

onMounted(() => {
    if (props.current_player) {
        polling.value = window.setInterval(pollLobby, 2000);
    }
});

onUnmounted(() => {
    if (polling.value) {
        clearInterval(polling.value);
    }
});

const getDifficultyLabel = (level: number) => {
    const labels = ['Very Easy', 'Easy', 'Medium', 'Hard', 'Expert'];
    return labels[level - 1] || 'Medium';
};

const getDifficultyColor = (level: number) => {
    const colors = ['text-green-400', 'text-emerald-400', 'text-yellow-400', 'text-orange-400', 'text-red-400'];
    return colors[level - 1] || 'text-yellow-400';
};
</script>

<template>
    <Head :title="`Lobby ${lobby.code}`" />

    <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-gray-950 via-gray-900 to-gray-950 p-4 md:p-6">
        <!-- Join overlay when visiting lobby link directly without being in lobby -->
        <div
            v-if="!current_player"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/95 p-4 backdrop-blur-sm"
        >
            <div class="glass-card w-full max-w-md animate-fade-in-scale rounded-3xl p-6">
                <template v-if="lobby.status === 'waiting'">
                    <div class="mb-6 flex items-center gap-3">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-blue-700 shadow-lg"
                        >
                            <LogIn class="h-6 w-6 text-white" />
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-white">Join Lobby</h2>
                            <p class="text-sm text-gray-400">{{ lobby.name || `Lobby ${lobby.code}` }}</p>
                        </div>
                    </div>
                    <form
                        @submit.prevent="joinForm.post(`/lobby/join/${lobby.code}`, { preserveScroll: true })"
                        class="space-y-5"
                    >
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-300">
                                <span class="flex items-center gap-2">
                                    <User class="h-4 w-4 text-blue-400" />
                                    Your Name
                                </span>
                            </label>
                            <input
                                v-model="joinForm.player_name"
                                type="text"
                                required
                                maxlength="30"
                                class="input-field input-field-blue w-full rounded-xl py-4 px-4 text-white placeholder-gray-500"
                                placeholder="Enter your name"
                            />
                            <p v-if="joinForm.errors.player_name" class="mt-1.5 text-sm text-red-400">
                                {{ joinForm.errors.player_name }}
                            </p>
                            <p v-if="joinForm.errors.code" class="mt-1.5 text-sm text-red-400">
                                {{ joinForm.errors.code }}
                            </p>
                        </div>
                        <button
                            type="submit"
                            :disabled="joinForm.processing"
                            class="btn-secondary flex w-full items-center justify-center gap-2 rounded-xl py-4 font-bold text-white"
                        >
                            <template v-if="joinForm.processing">
                                <div class="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white"></div>
                                Joining...
                            </template>
                            <template v-else>
                                <LogIn class="h-5 w-5" />
                                Join & Connect
                                <ArrowRight class="h-5 w-5" />
                            </template>
                        </button>
                    </form>
                </template>
                <template v-else>
                    <div class="flex flex-col items-center gap-4 py-4 text-center">
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-500/20">
                            <AlertCircle class="h-8 w-8 text-amber-400" />
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-white">
                                {{ lobby.status === 'playing' ? 'Game in progress' : 'Game ended' }}
                            </h2>
                            <p class="mt-1 text-gray-400">
                                {{ lobby.status === 'playing' ? 'This game has already started.' : 'This lobby has finished.' }}
                            </p>
                        </div>
                        <Link
                            href="/"
                            class="btn-glass flex items-center gap-2 rounded-xl px-6 py-3 font-medium text-white"
                        >
                            <ArrowRight class="h-5 w-5 rotate-180" />
                            Back to Home
                        </Link>
                    </div>
                </template>
            </div>
        </div>
        <!-- Background -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="bg-grid-pattern absolute inset-0 opacity-20"></div>
            <div class="absolute top-0 right-0 h-[600px] w-[600px] rounded-full bg-red-600/5 blur-[150px]"></div>
            <div class="absolute bottom-0 left-0 h-[500px] w-[500px] rounded-full bg-blue-600/5 blur-[120px]"></div>
        </div>

        <div class="relative z-10 mx-auto max-w-6xl">
            <!-- Header -->
            <header class="animate-fade-in mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="mb-2 flex items-center gap-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-red-500 to-red-700 shadow-lg shadow-red-500/20"
                        >
                            <ShieldAlert class="h-6 w-6 text-white" />
                        </div>
                        <div>
                            <div class="flex items-center gap-3">
                                <h1 class="text-3xl font-black text-white">Lobby</h1>
                                <button
                                    @click="copyCode"
                                    class="group glass flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 transition-all hover:bg-gray-800/50"
                                >
                                    <span class="font-mono text-2xl font-bold tracking-wider text-amber-400">{{ lobby.code }}</span>
                                    <component
                                        :is="copiedCode ? Check : Copy"
                                        class="h-5 w-5 transition-colors"
                                        :class="copiedCode ? 'text-green-400' : 'text-gray-400 group-hover:text-white'"
                                    />
                                </button>
                            </div>
                            <p v-if="lobby.name" class="mt-0.5 text-sm text-gray-400">{{ lobby.name }}</p>
                        </div>
                    </div>
                    <p v-if="copiedCode" class="animate-fade-in flex items-center gap-1 text-sm text-green-400">
                        <Check class="h-4 w-4" />
                        Link copied to clipboard!
                    </p>
                </div>

                <button
                    @click="leaveLobby"
                    class="btn-glass flex items-center justify-center gap-2 rounded-xl px-5 py-3 font-medium text-red-400 hover:text-red-300"
                >
                    <LogOut class="h-5 w-5" />
                    Leave Lobby
                </button>
            </header>

            <!-- Bento Grid Layout -->
            <div class="grid gap-5 lg:grid-cols-3">
                <!-- Players Card - Spans 2 columns -->
                <div class="glass-card animate-fade-in-scale rounded-3xl p-6 delay-100 lg:col-span-2">
                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-500/20">
                                <UsersRound class="h-5 w-5 text-blue-400" />
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-white">Players</h2>
                                <p class="text-sm text-gray-400">Waiting for everyone to join</p>
                            </div>
                        </div>
                        <div class="glass flex items-center gap-2 rounded-full px-4 py-2">
                            <Users class="h-4 w-4 text-gray-400" />
                            <span class="font-semibold text-white">{{ players.length }}</span>
                            <span class="text-gray-500">/</span>
                            <span class="text-gray-400">{{ lobby.settings.max_players }}</span>
                        </div>
                    </div>

                    <!-- Players Grid -->
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="(player, index) in players"
                            :key="player.id"
                            class="glass-light card-interactive animate-slide-in-left flex items-center gap-3 rounded-xl p-3"
                            :style="{ animationDelay: `${index * 75}ms` }"
                        >
                            <div
                                class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl text-lg font-bold"
                                :class="
                                    player.is_host
                                        ? 'bg-gradient-to-br from-amber-500/30 to-orange-500/20 text-amber-400 ring-1 ring-amber-500/30'
                                        : 'bg-gray-700/50 text-gray-300'
                                "
                            >
                                {{ player.name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="truncate font-medium text-white">{{ player.name }}</span>
                                    <span v-if="player.id === current_player?.id" class="text-xs font-medium text-blue-400">(You)</span>
                                </div>
                                <div class="mt-0.5 flex items-center gap-2">
                                    <span v-if="player.is_host" class="badge badge-host">
                                        <Crown class="h-3 w-3" />
                                        Host
                                    </span>
                                    <span v-if="player.is_ready" class="badge badge-neutral">
                                        <Check class="h-3 w-3" />
                                        Ready
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Empty Slots -->
                        <div
                            v-for="i in Math.max(0, lobby.settings.max_players - players.length)"
                            :key="`empty-${i}`"
                            class="glass-light flex items-center justify-center rounded-xl border-2 border-dashed border-gray-700/50 p-3 opacity-40"
                        >
                            <span class="flex items-center gap-2 text-sm text-gray-500">
                                <User class="h-4 w-4" />
                                Empty slot
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Settings Card -->
                <div class="glass-card animate-fade-in-scale rounded-3xl p-6 delay-200">
                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-500/20">
                                <Settings class="h-5 w-5 text-purple-400" />
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-white">Settings</h2>
                                <p class="text-sm text-gray-400">Game configuration</p>
                            </div>
                        </div>
                        <button
                            v-if="current_player?.is_host"
                            @click="showSettings = !showSettings"
                            class="btn-glass rounded-lg p-2"
                            :class="showSettings ? 'bg-gray-700/50 text-white' : 'text-gray-400'"
                        >
                            <component :is="showSettings ? X : Settings" class="h-5 w-5" />
                        </button>
                    </div>

                    <!-- Settings Display -->
                    <div v-if="!showSettings" class="space-y-3">
                        <div class="glass-light flex items-center justify-between rounded-xl p-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-500/20">
                                    <ShieldAlert class="h-4 w-4 text-red-400" />
                                </div>
                                <span class="text-sm text-gray-300">Impostors</span>
                            </div>
                            <span class="rounded-lg bg-gray-800/50 px-3 py-1 font-bold text-white">{{ lobby.settings.impostor_count }}</span>
                        </div>

                        <div class="glass-light flex items-center justify-between rounded-xl p-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-500/20">
                                    <Users class="h-4 w-4 text-blue-400" />
                                </div>
                                <span class="text-sm text-gray-300">Max Players</span>
                            </div>
                            <span class="rounded-lg bg-gray-800/50 px-3 py-1 font-bold text-white">{{ lobby.settings.max_players }}</span>
                        </div>

                        <div class="glass-light flex items-center justify-between rounded-xl p-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-500/20">
                                    <Clock class="h-4 w-4 text-green-400" />
                                </div>
                                <span class="text-sm text-gray-300">Discussion</span>
                            </div>
                            <span class="rounded-lg bg-gray-800/50 px-3 py-1 font-bold text-white">{{ lobby.settings.discussion_time }}s</span>
                        </div>

                        <div class="glass-light flex items-center justify-between rounded-xl p-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-yellow-500/20">
                                    <Zap class="h-4 w-4 text-yellow-400" />
                                </div>
                                <span class="text-sm text-gray-300">Voting</span>
                            </div>
                            <span class="rounded-lg bg-gray-800/50 px-3 py-1 font-bold text-white">{{ lobby.settings.voting_time }}s</span>
                        </div>

                        <div class="glass-light flex items-center justify-between rounded-xl p-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-500/20">
                                    <Flame class="h-4 w-4 text-purple-400" />
                                </div>
                                <span class="text-sm text-gray-300">Difficulty</span>
                            </div>
                            <span
                                class="rounded-lg bg-gray-800/50 px-3 py-1 text-xs font-bold"
                                :class="getDifficultyColor(lobby.settings.word_difficulty)"
                            >
                                {{ getDifficultyLabel(lobby.settings.word_difficulty) }}
                            </span>
                        </div>
                    </div>

                    <!-- Edit Settings Form -->
                    <form v-else @submit.prevent="updateSettings" class="animate-fade-in space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-xs text-gray-400">Impostors</label>
                                <input
                                    v-model.number="settingsForm.settings.impostor_count"
                                    type="number"
                                    min="1"
                                    max="5"
                                    class="input-field w-full rounded-lg px-3 py-2.5 text-sm text-white"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs text-gray-400">Max Players</label>
                                <input
                                    v-model.number="settingsForm.settings.max_players"
                                    type="number"
                                    min="2"
                                    max="20"
                                    class="input-field w-full rounded-lg px-3 py-2.5 text-sm text-white"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs text-gray-400">Discussion Time (seconds)</label>
                            <input
                                v-model.number="settingsForm.settings.discussion_time"
                                type="number"
                                min="15"
                                max="300"
                                class="input-field w-full rounded-lg px-3 py-2.5 text-sm text-white"
                            />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs text-gray-400">Voting Time (seconds)</label>
                            <input
                                v-model.number="settingsForm.settings.voting_time"
                                type="number"
                                min="15"
                                max="180"
                                class="input-field w-full rounded-lg px-3 py-2.5 text-sm text-white"
                            />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs text-gray-400">Word Difficulty (1-5)</label>
                            <input
                                v-model.number="settingsForm.settings.word_difficulty"
                                type="number"
                                min="1"
                                max="5"
                                class="input-field w-full rounded-lg px-3 py-2.5 text-sm text-white"
                            />
                        </div>
                        <button
                            type="submit"
                            :disabled="settingsForm.processing"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-purple-600 to-purple-700 py-3 font-semibold text-white transition-all hover:from-purple-500 hover:to-purple-600"
                        >
                            <Save class="h-4 w-4" />
                            Save Settings
                        </button>
                    </form>
                </div>

                <!-- Start Game Section - Full Width -->
                <div class="animate-fade-in delay-300 lg:col-span-3">
                    <div
                        v-if="current_player?.is_host && players.length >= 2"
                        class="glass-card relative overflow-hidden rounded-3xl p-8 text-center"
                    >
                        <!-- Background glow -->
                        <div class="absolute inset-0 bg-gradient-to-r from-green-500/10 via-emerald-500/10 to-green-500/10"></div>
                        <div
                            class="absolute top-1/2 left-1/2 h-96 w-96 -translate-x-1/2 -translate-y-1/2 rounded-full bg-green-500/20 blur-[100px]"
                        ></div>

                        <div class="relative z-10">
                            <div
                                class="animate-float mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-green-500 to-emerald-600 shadow-xl shadow-green-500/30"
                            >
                                <Play class="ml-1 h-10 w-10 text-white" fill="currentColor" />
                            </div>
                            <h3 class="mb-2 text-2xl font-bold text-white">Ready to Start?</h3>
                            <p class="mx-auto mb-6 max-w-md text-gray-400">All players are here! Click the button below to begin the game.</p>
                            <button
                                @click="startGame"
                                class="group inline-flex items-center gap-3 rounded-2xl bg-gradient-to-r from-green-600 to-emerald-600 px-12 py-5 text-xl font-black text-white shadow-2xl shadow-green-600/30 transition-all hover:scale-105 hover:from-green-500 hover:to-emerald-500 hover:shadow-green-600/50"
                            >
                                <Sparkles class="h-6 w-6" />
                                START GAME
                                <ChevronRight class="h-6 w-6 transition-transform group-hover:translate-x-1" />
                            </button>
                        </div>
                    </div>

                    <div v-else-if="players.length < 2" class="glass-card flex items-center justify-center gap-4 rounded-3xl p-6">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/20">
                            <AlertCircle class="h-6 w-6 text-amber-400" />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white">Waiting for more players</h3>
                            <p class="text-sm text-gray-400">At least 2 players are required to start the game</p>
                        </div>
                    </div>

                    <div v-else class="glass-card flex items-center justify-center gap-4 rounded-3xl p-6">
                        <div class="flex h-12 w-12 animate-pulse items-center justify-center rounded-xl bg-blue-500/20">
                            <Clock class="h-6 w-6 text-blue-400" />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white">Waiting for host</h3>
                            <p class="text-sm text-gray-400">The host will start the game when everyone is ready</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
