<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
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
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import AvatarSelectionModal from '@/components/AvatarSelectionModal.vue';
import { avatarUrl } from '@/lib/avatars';
import { play, start } from '@/routes/game';
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
            language: 'en' | 'ro';
        };
        player_count: number;
    };
    players: Array<{
        id: number;
        name: string;
        avatar: string | null;
        is_host: boolean;
        is_ready: boolean;
    }>;
    current_player: {
        id: number;
        name: string;
        avatar: string | null;
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
        language: props.lobby?.settings.language ?? 'en',
    },
});

const joinForm = useForm({
    player_name: '',
    avatar: '' as string | null,
});

const showJoinAvatarModal = ref(false);

/** Server can return validation errors for keys like 'code' (e.g. lobby not found). */
const joinFormErrors = computed(() => joinForm.errors as Record<string, string>);

const lobbyLink = `${window.location.origin}/lobby/${props.lobby.code}`;

const copyCode = async () => {
    const text = lobbyLink;
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
        } else {
            const el = document.createElement('textarea');
            el.value = text;
            el.setAttribute('readonly', '');
            el.style.position = 'absolute';
            el.style.left = '-9999px';
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
        }
        copiedCode.value = true;
        setTimeout(() => (copiedCode.value = false), 2000);
    } catch {
        copiedCode.value = false;
    }
};

const startGameLoading = ref(false);
const startGameError = ref<string | null>(null);

const startGame = async () => {
    startGameError.value = null;
    startGameLoading.value = true;
    try {
        await axios.post(start.url(props.lobby.code));
        router.visit(play.url(props.lobby.code));
    } catch (err: unknown) {
        const msg = axios.isAxiosError(err) && err.response?.data?.error ? String(err.response.data.error) : 'Failed to start game';
        startGameError.value = msg;
    } finally {
        startGameLoading.value = false;
    }
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
        const statusResponse = await axios.get(`/lobby/${props.lobby.code}/status`);
        if (statusResponse.data.status === 'playing') {
            if (polling.value) {
                clearInterval(polling.value);
                polling.value = null;
            }
            router.visit(play.url(props.lobby.code));
            return;
        }
        await router.reload({ only: ['lobby', 'players'] });
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 404) {
            return;
        }
        console.error('Polling error:', error);
    }
};

const handleVisibilityChange = () => {
    if (document.visibilityState === 'visible' && props.current_player) {
        pollLobby();
    }
};

const POLL_INTERVAL_MS = 500;

const redirectToGameIfStarted = () => {
    if (props.lobby?.status === 'playing' && props.current_player) {
        if (polling.value) {
            clearInterval(polling.value);
            polling.value = null;
        }
        router.visit(play.url(props.lobby.code));
    }
};

watch(
    () => props.lobby?.status,
    (status) => {
        if (status === 'playing') {
            redirectToGameIfStarted();
        }
    },
    { immediate: true }
);

onMounted(() => {
    redirectToGameIfStarted();
    if (props.current_player) {
        pollLobby();
        polling.value = window.setInterval(pollLobby, POLL_INTERVAL_MS);
        document.addEventListener('visibilitychange', handleVisibilityChange);
    }
});

onUnmounted(() => {
    if (polling.value) {
        clearInterval(polling.value);
    }
    document.removeEventListener('visibilitychange', handleVisibilityChange);
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

    <div class="bg-void relative min-h-screen overflow-hidden p-3 sm:p-4 md:p-6">
        <!-- Join overlay when visiting lobby link directly without being in lobby -->
        <div v-if="!current_player" class="bg-void/95 fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
            <div class="glass-card animate-fade-in-scale w-full max-w-md rounded-2xl p-5 sm:rounded-3xl sm:p-6">
                <template v-if="lobby.status === 'waiting'">
                    <div class="mb-6 flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 shadow-lg sm:h-12 sm:w-12 sm:rounded-2xl"
                        >
                            <LogIn class="h-5 w-5 text-white sm:h-6 sm:w-6" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-text-primary sm:text-xl">Join Lobby</h2>
                            <p class="text-xs text-text-secondary sm:text-sm">{{ lobby.name || `Lobby ${lobby.code}` }}</p>
                        </div>
                    </div>
                    <form @submit.prevent="joinForm.post(`/lobby/join/${lobby.code}`, { preserveScroll: true })" class="space-y-4 sm:space-y-5">
                        <div>
                            <label class="mb-2 block text-xs font-medium text-text-secondary sm:text-sm">
                                <span class="flex items-center gap-2">
                                    <User class="h-4 w-4 text-blue-400" />
                                    Avatar
                                    <span class="text-red-400">*</span>
                                </span>
                            </label>
                            <button
                                type="button"
                                @click="showJoinAvatarModal = true"
                                class="mb-4 flex w-full items-center gap-3 overflow-hidden rounded-lg border-2 border-dashed border-void-border bg-transparent px-4 py-3 transition-colors hover:border-blue-500/50 hover:bg-void-hover/50 sm:rounded-xl sm:py-4"
                                :class="joinForm.avatar ? 'bg-void-hover/30' : ''"
                            >
                                <div
                                    class="flex h-12 w-12 flex-shrink-0 items-center justify-center overflow-hidden rounded-lg bg-void-hover sm:h-14 sm:w-14"
                                >
                                    <img
                                        v-if="joinForm.avatar"
                                        :src="avatarUrl(joinForm.avatar)"
                                        alt="Selected avatar"
                                        class="h-full w-full object-cover"
                                    />
                                    <User v-else class="h-6 w-6 text-text-tertiary sm:h-7 sm:w-7" />
                                </div>
                                <span class="text-sm font-medium" :class="joinForm.avatar ? 'text-text-primary' : 'text-text-tertiary'">
                                    {{ joinForm.avatar ? 'Change avatar' : 'Choose avatar (required)' }}
                                </span>
                            </button>
                            <p v-if="joinForm.errors.avatar" class="mt-1.5 text-xs text-red-400 sm:text-sm">
                                {{ joinForm.errors.avatar }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-medium text-text-secondary sm:text-sm">
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
                                class="input-field w-full rounded-lg px-4 py-3 text-sm text-text-primary placeholder-text-tertiary sm:rounded-xl sm:py-4"
                                placeholder="Enter your name"
                            />
                            <p v-if="joinForm.errors.player_name" class="mt-1.5 text-xs text-red-400 sm:text-sm">
                                {{ joinForm.errors.player_name }}
                            </p>
                            <p v-if="joinFormErrors.code" class="mt-1.5 text-xs text-red-400 sm:text-sm">
                                {{ joinFormErrors.code }}
                            </p>
                        </div>
                        <button
                            type="submit"
                            :disabled="joinForm.processing || !joinForm.avatar"
                            class="btn-primary flex w-full items-center justify-center gap-2 rounded-lg py-3.5 text-sm font-bold text-white sm:rounded-xl sm:py-4"
                        >
                            <template v-if="joinForm.processing">
                                <div class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white sm:h-5 sm:w-5"></div>
                                Joining...
                            </template>
                            <template v-else>
                                <LogIn class="h-4 w-4 sm:h-5 sm:w-5" />
                                Join & Connect
                                <ArrowRight class="h-4 w-4 sm:h-5 sm:w-5" />
                            </template>
                        </button>
                    </form>
                    <AvatarSelectionModal
                        :show="showJoinAvatarModal"
                        :model-value="joinForm.avatar"
                        @update:model-value="joinForm.avatar = $event"
                        @close="showJoinAvatarModal = false"
                    />
                </template>
                <template v-else>
                    <div class="flex flex-col items-center gap-4 py-4 text-center">
                        <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-blue-500/20 sm:h-16 sm:w-16 sm:rounded-2xl">
                            <AlertCircle class="h-7 w-7 text-blue-400 sm:h-8 sm:w-8" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-text-primary sm:text-xl">
                                {{ lobby.status === 'playing' ? 'Game in progress' : 'Game ended' }}
                            </h2>
                            <p class="mt-1 text-sm text-text-secondary">
                                {{ lobby.status === 'playing' ? 'This game has already started.' : 'This lobby has finished.' }}
                            </p>
                        </div>
                        <Link
                            href="/"
                            class="btn-glass flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-medium text-text-secondary hover:text-text-primary sm:rounded-xl sm:px-6 sm:py-3"
                        >
                            <ArrowRight class="h-4 w-4 rotate-180 sm:h-5 sm:w-5" />
                            Back to Home
                        </Link>
                    </div>
                </template>
            </div>
        </div>
        <!-- Background -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="bg-grid-pattern absolute inset-0 opacity-30"></div>
            <div
                class="absolute top-0 right-0 h-[300px] w-[300px] rounded-full bg-blue-600/5 blur-[100px] sm:h-[600px] sm:w-[600px] sm:blur-[150px]"
            ></div>
            <div
                class="absolute bottom-0 left-0 h-[250px] w-[250px] rounded-full bg-blue-600/5 blur-[80px] sm:h-[500px] sm:w-[500px] sm:blur-[120px]"
            ></div>
        </div>

        <div class="relative z-10 mx-auto max-w-6xl">
            <!-- Header -->
            <header class="animate-fade-in mb-4 flex flex-col gap-3 sm:mb-8 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <div>
                    <div class="mb-2 flex items-center gap-3">
                        <div
                            class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-xl bg-void-elevated shadow-lg shadow-blue-500/20 sm:h-24 sm:w-24 sm:rounded-2xl"
                        >
                            <img src="/images/Logo.png" alt="Word Impostor" class="h-12 w-auto object-contain sm:h-16" />
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                                <h1 class="text-2xl font-black text-text-primary sm:text-3xl">Lobby</h1>
                                <button
                                    @click="copyCode"
                                    class="group glass flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 transition-all hover:bg-void-hover sm:gap-2 sm:rounded-xl sm:px-4 sm:py-2"
                                >
                                    <span class="font-mono text-lg font-bold tracking-wider text-blue-400 sm:text-2xl">{{ lobby.code }}</span>
                                    <component
                                        :is="copiedCode ? Check : Copy"
                                        class="h-4 w-4 transition-colors sm:h-5 sm:w-5"
                                        :class="copiedCode ? 'text-green-400' : 'text-text-secondary group-hover:text-text-primary'"
                                    />
                                </button>
                            </div>
                            <p v-if="lobby.name" class="mt-0.5 text-xs text-text-secondary sm:text-sm">{{ lobby.name }}</p>
                        </div>
                    </div>
                    <p v-if="copiedCode" class="animate-fade-in flex items-center gap-1 text-xs text-green-400 sm:text-sm">
                        <Check class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                        Link copied to clipboard!
                    </p>
                </div>

                <button
                    @click="leaveLobby"
                    class="btn-danger flex items-center justify-center gap-1.5 rounded-lg px-4 py-2.5 text-sm font-medium sm:gap-2 sm:rounded-xl sm:px-5 sm:py-3"
                >
                    <LogOut class="h-4 w-4 sm:h-5 sm:w-5" />
                    <span class="sm:hidden">Leave</span>
                    <span class="hidden sm:inline">Leave Lobby</span>
                </button>
            </header>

            <!-- Bento Grid Layout -->
            <div class="grid gap-3 sm:gap-5 lg:grid-cols-3">
                <!-- Players Card - Spans 2 columns on desktop -->
                <div class="glass-card animate-fade-in-scale rounded-2xl p-4 delay-100 sm:rounded-3xl sm:p-6 lg:col-span-2">
                    <div class="mb-4 flex items-center justify-between sm:mb-6">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-500/20 sm:h-10 sm:w-10 sm:rounded-xl">
                                <UsersRound class="h-4 w-4 text-blue-400 sm:h-5 sm:w-5" />
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-text-primary sm:text-xl">Players</h2>
                                <p class="text-xs text-text-secondary sm:text-sm">Waiting for everyone to join</p>
                            </div>
                        </div>
                        <div class="glass flex items-center gap-1.5 rounded-full px-3 py-1.5 sm:gap-2 sm:px-4 sm:py-2">
                            <Users class="h-3.5 w-3.5 text-text-secondary sm:h-4 sm:w-4" />
                            <span class="text-sm font-semibold text-text-primary sm:text-base">{{ players.length }}</span>
                            <span class="text-text-tertiary">/</span>
                            <span class="text-xs text-text-secondary sm:text-sm">{{ lobby.settings.max_players }}</span>
                        </div>
                    </div>

                    <!-- Players Grid -->
                    <div class="grid gap-2 sm:grid-cols-2 sm:gap-3">
                        <div
                            v-for="(player, index) in players"
                            :key="player.id"
                            class="glass-light card-interactive animate-slide-in-left flex items-center gap-2 rounded-lg p-2.5 sm:gap-3 sm:rounded-xl sm:p-3"
                            :style="{ animationDelay: `${index * 75}ms` }"
                        >
                            <div
                                class="flex h-10 w-10 flex-shrink-0 items-center justify-center overflow-hidden rounded-lg text-base font-bold sm:h-12 sm:w-12 sm:rounded-xl sm:text-lg"
                                :class="
                                    player.is_host
                                        ? 'bg-gradient-to-br from-amber-500/30 to-orange-500/20 text-amber-400 ring-1 ring-amber-500/30'
                                        : 'bg-void-hover text-text-secondary'
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
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 sm:gap-2">
                                    <span class="truncate text-sm font-medium text-text-primary">{{ player.name }}</span>
                                    <span v-if="player.id === current_player?.id" class="text-[10px] font-medium text-blue-400 sm:text-xs"
                                        >(You)</span
                                    >
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1 sm:gap-2">
                                    <span v-if="player.is_host" class="badge badge-host text-[10px] sm:text-xs">
                                        <Crown class="h-2.5 w-2.5 sm:h-3 sm:w-3" />
                                        Host
                                    </span>
                                    <span v-if="player.is_ready" class="badge badge-neutral text-[10px] sm:text-xs">
                                        <Check class="h-2.5 w-2.5 sm:h-3 sm:w-3" />
                                        Ready
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Empty Slots -->
                        <div
                            v-for="i in Math.max(0, lobby.settings.max_players - players.length)"
                            :key="`empty-${i}`"
                            class="glass-light flex items-center justify-center rounded-lg border-2 border-dashed border-void-border p-2.5 opacity-40 sm:rounded-xl sm:p-3"
                        >
                            <span class="flex items-center gap-1.5 text-xs text-text-tertiary sm:gap-2 sm:text-sm">
                                <User class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                Empty slot
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Settings Card -->
                <div class="glass-card animate-fade-in-scale rounded-2xl p-4 delay-200 sm:rounded-3xl sm:p-6">
                    <div class="mb-4 flex items-center justify-between sm:mb-6">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-500/20 sm:h-10 sm:w-10 sm:rounded-xl">
                                <Settings class="h-4 w-4 text-blue-400 sm:h-5 sm:w-5" />
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-text-primary sm:text-xl">Settings</h2>
                                <p class="text-xs text-text-secondary sm:text-sm">Game configuration</p>
                            </div>
                        </div>
                        <button
                            v-if="current_player?.is_host"
                            @click="showSettings = !showSettings"
                            class="btn-glass rounded-md p-1.5 sm:rounded-lg sm:p-2"
                            :class="showSettings ? 'bg-void-hover text-text-primary' : 'text-text-secondary'"
                        >
                            <component :is="showSettings ? X : Settings" class="h-4 w-4 sm:h-5 sm:w-5" />
                        </button>
                    </div>

                    <!-- Settings Display -->
                    <div v-if="!showSettings" class="space-y-2 sm:space-y-3">
                        <div class="glass-light flex items-center justify-between rounded-lg p-2.5 sm:rounded-xl sm:p-3">
                            <div class="flex items-center gap-2 sm:gap-3">
                                <div class="flex h-6 w-6 items-center justify-center rounded-md bg-red-500/20 sm:h-8 sm:w-8 sm:rounded-lg">
                                    <ShieldAlert class="h-3 w-3 text-red-400 sm:h-4 sm:w-4" />
                                </div>
                                <span class="text-xs text-text-secondary sm:text-sm">Impostors</span>
                            </div>
                            <span class="rounded-md bg-void-hover px-2 py-0.5 text-sm font-bold text-text-primary sm:rounded-lg sm:px-3 sm:py-1">{{
                                lobby.settings.impostor_count
                            }}</span>
                        </div>

                        <div class="glass-light flex items-center justify-between rounded-lg p-2.5 sm:rounded-xl sm:p-3">
                            <div class="flex items-center gap-2 sm:gap-3">
                                <div class="flex h-6 w-6 items-center justify-center rounded-md bg-blue-500/20 sm:h-8 sm:w-8 sm:rounded-lg">
                                    <Users class="h-3 w-3 text-blue-400 sm:h-4 sm:w-4" />
                                </div>
                                <span class="text-xs text-text-secondary sm:text-sm">Max Players</span>
                            </div>
                            <span class="rounded-md bg-void-hover px-2 py-0.5 text-sm font-bold text-text-primary sm:rounded-lg sm:px-3 sm:py-1">{{
                                lobby.settings.max_players
                            }}</span>
                        </div>

                        <div class="glass-light flex items-center justify-between rounded-lg p-2.5 sm:rounded-xl sm:p-3">
                            <div class="flex items-center gap-2 sm:gap-3">
                                <div class="flex h-6 w-6 items-center justify-center rounded-md bg-green-500/20 sm:h-8 sm:w-8 sm:rounded-lg">
                                    <Clock class="h-3 w-3 text-green-400 sm:h-4 sm:w-4" />
                                </div>
                                <span class="text-xs text-text-secondary sm:text-sm">Discussion</span>
                            </div>
                            <span class="rounded-md bg-void-hover px-2 py-0.5 text-sm font-bold text-text-primary sm:rounded-lg sm:px-3 sm:py-1"
                                >{{ lobby.settings.discussion_time }}s</span
                            >
                        </div>

                        <div class="glass-light flex items-center justify-between rounded-lg p-2.5 sm:rounded-xl sm:p-3">
                            <div class="flex items-center gap-2 sm:gap-3">
                                <div class="flex h-6 w-6 items-center justify-center rounded-md bg-yellow-500/20 sm:h-8 sm:w-8 sm:rounded-lg">
                                    <Zap class="h-3 w-3 text-yellow-400 sm:h-4 sm:w-4" />
                                </div>
                                <span class="text-xs text-text-secondary sm:text-sm">Voting</span>
                            </div>
                            <span class="rounded-md bg-void-hover px-2 py-0.5 text-sm font-bold text-text-primary sm:rounded-lg sm:px-3 sm:py-1"
                                >{{ lobby.settings.voting_time }}s</span
                            >
                        </div>

                        <div class="glass-light flex items-center justify-between rounded-lg p-2.5 sm:rounded-xl sm:p-3">
                            <div class="flex items-center gap-2 sm:gap-3">
                                <div class="flex h-6 w-6 items-center justify-center rounded-md bg-blue-500/20 sm:h-8 sm:w-8 sm:rounded-lg">
                                    <Flame class="h-3 w-3 text-blue-400 sm:h-4 sm:w-4" />
                                </div>
                                <span class="text-xs text-text-secondary sm:text-sm">Difficulty</span>
                            </div>
                            <span
                                class="rounded-md bg-void-hover px-2 py-0.5 text-[10px] font-bold sm:rounded-lg sm:px-3 sm:py-1 sm:text-xs"
                                :class="getDifficultyColor(lobby.settings.word_difficulty)"
                            >
                                {{ getDifficultyLabel(lobby.settings.word_difficulty) }}
                            </span>
                        </div>

                        <div class="glass-light flex items-center justify-between rounded-lg p-2.5 sm:rounded-xl sm:p-3">
                            <div class="flex items-center gap-2 sm:gap-3">
                                <div class="flex h-6 w-6 items-center justify-center rounded-md bg-blue-500/20 sm:h-8 sm:w-8 sm:rounded-lg">
                                    <span class="text-sm font-bold text-blue-400 sm:text-base">{{
                                        lobby.settings.language === 'en' ? 'EN' : 'RO'
                                    }}</span>
                                </div>
                                <span class="text-xs text-text-secondary sm:text-sm">Language</span>
                            </div>
                            <span class="rounded-md bg-void-hover px-2 py-0.5 text-sm font-bold text-text-primary sm:rounded-lg sm:px-3 sm:py-1">{{
                                lobby.settings.language === 'en' ? 'English' : 'Română'
                            }}</span>
                        </div>
                    </div>

                    <!-- Edit Settings Form -->
                    <form v-else @submit.prevent="updateSettings" class="animate-fade-in space-y-3 sm:space-y-4">
                        <div class="grid grid-cols-2 gap-2 sm:gap-3">
                            <div>
                                <label class="mb-1 block text-[10px] text-text-secondary sm:mb-1.5 sm:text-xs">Impostors</label>
                                <input
                                    v-model.number="settingsForm.settings.impostor_count"
                                    type="number"
                                    min="1"
                                    max="5"
                                    class="input-field w-full rounded-md px-2 py-2 text-xs text-text-primary sm:rounded-lg sm:px-3 sm:py-2.5"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] text-text-secondary sm:mb-1.5 sm:text-xs">Max Players</label>
                                <input
                                    v-model.number="settingsForm.settings.max_players"
                                    type="number"
                                    min="2"
                                    max="20"
                                    class="input-field w-full rounded-md px-2 py-2 text-xs text-text-primary sm:rounded-lg sm:px-3 sm:py-2.5"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] text-text-secondary sm:mb-1.5 sm:text-xs">Discussion Time (seconds)</label>
                            <input
                                v-model.number="settingsForm.settings.discussion_time"
                                type="number"
                                min="15"
                                max="300"
                                class="input-field w-full rounded-md px-2 py-2 text-xs text-text-primary sm:rounded-lg sm:px-3 sm:py-2.5"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] text-text-secondary sm:mb-1.5 sm:text-xs">Voting Time (seconds)</label>
                            <input
                                v-model.number="settingsForm.settings.voting_time"
                                type="number"
                                min="15"
                                max="180"
                                class="input-field w-full rounded-md px-2 py-2 text-xs text-text-primary sm:rounded-lg sm:px-3 sm:py-2.5"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] text-text-secondary sm:mb-1.5 sm:text-xs">Word Difficulty (1-5)</label>
                            <input
                                v-model.number="settingsForm.settings.word_difficulty"
                                type="number"
                                min="1"
                                max="5"
                                class="input-field w-full rounded-md px-2 py-2 text-xs text-text-primary sm:rounded-lg sm:px-3 sm:py-2.5"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] text-text-secondary sm:mb-1.5 sm:text-xs">Language</label>
                            <select
                                v-model="settingsForm.settings.language"
                                class="input-field w-full rounded-md px-2 py-2 text-xs text-text-primary sm:rounded-lg sm:px-3 sm:py-2.5"
                            >
                                <option value="en">English</option>
                                <option value="ro">Română</option>
                            </select>
                        </div>
                        <button
                            type="submit"
                            :disabled="settingsForm.processing"
                            class="btn-primary flex w-full items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-semibold text-white sm:rounded-xl sm:py-3"
                        >
                            <Save class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                            Save Settings
                        </button>
                    </form>
                </div>

                <!-- Start Game Section - Full Width -->
                <div class="animate-fade-in delay-300 lg:col-span-3">
                    <div
                        v-if="current_player?.is_host && players.length >= 2"
                        class="glass-card relative overflow-hidden rounded-2xl p-5 text-center sm:rounded-3xl sm:p-8"
                    >
                        <!-- Background glow -->
                        <div class="absolute inset-0 bg-gradient-to-r from-blue-500/10 via-blue-500/10 to-blue-500/10"></div>
                        <div
                            class="absolute top-1/2 left-1/2 h-48 w-48 -translate-x-1/2 -translate-y-1/2 rounded-full bg-blue-500/20 blur-[60px] sm:h-96 sm:w-96 sm:blur-[100px]"
                        ></div>

                        <div class="relative z-10">
                            <div
                                class="animate-float mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 shadow-xl shadow-blue-500/30 sm:mb-4 sm:h-20 sm:w-20 sm:rounded-2xl"
                            >
                                <Play class="ml-0.5 h-7 w-7 text-white sm:ml-1 sm:h-10 sm:w-10" fill="currentColor" />
                            </div>
                            <h3 class="mb-1 text-xl font-bold text-text-primary sm:mb-2 sm:text-2xl">Ready to Start?</h3>
                            <p class="mx-auto mb-4 max-w-md text-xs text-text-secondary sm:mb-6 sm:text-base">
                                All players are here! Click the button below to begin the game.
                            </p>
                            <p v-if="startGameError" class="mb-3 text-sm text-red-400">{{ startGameError }}</p>
                            <button
                                @click="startGame"
                                :disabled="startGameLoading"
                                class="group inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-blue-600 px-8 py-3 text-lg font-black text-white shadow-2xl shadow-blue-600/30 transition-all hover:scale-105 hover:from-blue-500 hover:to-blue-500 hover:shadow-blue-600/50 disabled:cursor-not-allowed disabled:opacity-70 sm:gap-3 sm:rounded-2xl sm:px-12 sm:py-5 sm:text-xl"
                            >
                                <template v-if="startGameLoading">
                                    <div class="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white sm:h-6 sm:w-6"></div>
                                    <span>Starting...</span>
                                </template>
                                <template v-else>
                                    <Sparkles class="h-5 w-5 sm:h-6 sm:w-6" />
                                    <span class="sm:hidden">START</span>
                                    <span class="hidden sm:inline">START GAME</span>
                                    <ChevronRight class="h-5 w-5 transition-transform group-hover:translate-x-1 sm:h-6 sm:w-6" />
                                </template>
                            </button>
                        </div>
                    </div>

                    <div v-else-if="players.length < 2" class="glass-card flex items-center gap-3 rounded-2xl p-4 sm:gap-4 sm:rounded-3xl sm:p-6">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-blue-500/20 sm:h-12 sm:w-12 sm:rounded-xl">
                            <AlertCircle class="h-5 w-5 text-blue-400 sm:h-6 sm:w-6" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-text-primary sm:text-lg">Waiting for more players</h3>
                            <p class="text-xs text-text-secondary sm:text-sm">At least 2 players are required to start the game</p>
                        </div>
                    </div>

                    <div v-else class="glass-card flex items-center gap-3 rounded-2xl p-4 sm:gap-4 sm:rounded-3xl sm:p-6">
                        <div
                            class="flex h-10 w-10 flex-shrink-0 animate-pulse items-center justify-center rounded-lg bg-blue-500/20 sm:h-12 sm:w-12 sm:rounded-xl"
                        >
                            <Clock class="h-5 w-5 text-blue-400 sm:h-6 sm:w-6" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-text-primary sm:text-lg">Waiting for host</h3>
                            <p class="text-xs text-text-secondary sm:text-sm">The host will start the game when everyone is ready</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
