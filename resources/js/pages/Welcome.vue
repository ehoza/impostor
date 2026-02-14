<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Users, Plus, LogIn, User, Building2, Sparkles, ShieldAlert, ArrowRight, Copy, Gamepad2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const createForm = useForm({
    player_name: '',
    lobby_name: '',
});

const joinForm = useForm({
    player_name: '',
    code: '',
});

/** Server can return validation errors for keys not in the form type. */
const createFormErrors = computed(() => createForm.errors as Record<string, string>);
const joinFormErrors = computed(() => joinForm.errors as Record<string, string>);

const activeTab = ref<'create' | 'join'>('create');
</script>

<template>
    <Head title="Impostor" />
    <div class="bg-void relative flex min-h-screen items-center justify-center overflow-hidden p-3 sm:p-4">
        <!-- Animated Background -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <!-- Grid Pattern -->
            <div class="bg-grid-pattern absolute inset-0 opacity-50"></div>

            <!-- Floating Orbs -->
            <div
                class="animate-float-slow absolute top-1/4 left-1/4 h-64 w-64 rounded-full bg-blue-600/5 blur-[80px] sm:h-96 sm:w-96 sm:blur-[100px]"
            ></div>
            <div
                class="animate-float absolute right-1/4 bottom-1/4 h-80 w-80 rounded-full bg-blue-600/5 blur-[100px] sm:h-[500px] sm:w-[500px] sm:blur-[120px]"
                style="animation-delay: -2s"
            ></div>
            <div
                class="absolute top-1/2 left-1/2 h-[400px] w-[400px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-blue-600/3 blur-[100px] sm:h-[600px] sm:w-[600px] sm:blur-[150px]"
            ></div>

            <!-- Accent Lines -->
            <div class="absolute top-0 left-0 h-px w-full bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
            <div class="absolute bottom-0 left-0 h-px w-full bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
        </div>

        <div class="relative z-10 w-full max-w-lg">
            <!-- Hero Section -->
            <div class="animate-fade-in-blur mb-6 text-center sm:mb-10">
                <!-- Logo -->
                <div class="relative mb-4 inline-block sm:mb-6">
                    <div class="animate-pulse-glow-blue absolute inset-0 rounded-2xl bg-blue-500/20 blur-xl sm:rounded-3xl sm:blur-2xl"></div>
                    <div
                        class="animate-float relative mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 via-blue-600 to-blue-700 shadow-2xl shadow-blue-500/30 sm:h-24 sm:w-24 sm:rounded-3xl"
                    >
                        <ShieldAlert class="h-8 w-8 text-white sm:h-12 sm:w-12" stroke-width="{1.5}" />
                    </div>
                    <!-- Floating accent -->
                    <div
                        class="animate-bounce-in absolute -top-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-gradient-to-br from-blue-400 to-blue-500 shadow-lg delay-500 sm:-top-2 sm:-right-2 sm:h-8 sm:w-8"
                    >
                        <Sparkles class="h-3 w-3 text-white sm:h-4 sm:w-4" />
                    </div>
                </div>

                <h1 class="gradient-text-blue text-shadow mb-2 text-4xl font-black tracking-tight sm:mb-3 sm:text-6xl">IMPOSTOR</h1>
                <p class="flex items-center justify-center gap-1 px-2 text-sm text-text-secondary sm:gap-2 sm:text-lg">
                    <Gamepad2 class="h-4 w-4 text-blue-400 sm:h-5 sm:w-5" />
                    <span class="hidden sm:inline">Find the impostor among your friends</span>
                    <span class="sm:hidden">Find the impostor</span>
                    <Gamepad2 class="h-4 w-4 text-blue-400 sm:h-5 sm:w-5" />
                </p>
            </div>

            <!-- Main Card -->
            <div class="glass-card animate-fade-in-scale rounded-2xl p-1.5 delay-200 sm:rounded-3xl sm:p-2">
                <!-- Tab Switcher -->
                <div class="mb-3 flex gap-1 rounded-xl bg-void-hover/50 p-1 sm:mb-4 sm:rounded-2xl sm:p-1.5">
                    <button
                        @click="activeTab = 'create'"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-sm font-semibold transition-all duration-300 sm:gap-2 sm:rounded-xl sm:px-4 sm:py-3.5"
                        :class="activeTab === 'create' ? 'btn-primary text-white' : 'text-text-secondary hover:bg-void-hover hover:text-text-primary'"
                    >
                        <Plus class="h-4 w-4 sm:h-5 sm:w-5" />
                        <span class="hidden sm:inline">Create Lobby</span>
                        <span class="sm:hidden">Create</span>
                    </button>
                    <button
                        @click="activeTab = 'join'"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-sm font-semibold transition-all duration-300 sm:gap-2 sm:rounded-xl sm:px-4 sm:py-3.5"
                        :class="
                            activeTab === 'join'
                                ? 'btn-secondary border-blue-500 bg-blue-500/10 text-blue-400'
                                : 'text-text-secondary hover:bg-void-hover hover:text-text-primary'
                        "
                    >
                        <LogIn class="h-4 w-4 sm:h-5 sm:w-5" />
                        <span class="hidden sm:inline">Join Game</span>
                        <span class="sm:hidden">Join</span>
                    </button>
                </div>

                <!-- Create Lobby Form -->
                <div v-if="activeTab === 'create'" class="animate-fade-in p-3 sm:p-5">
                    <form @submit.prevent="createForm.post('/lobby/create')" class="space-y-4 sm:space-y-5">
                        <!-- Player Name -->
                        <div class="animate-slide-in-up delay-100">
                            <label class="mb-2 block flex items-center gap-1.5 text-xs font-medium text-text-secondary sm:gap-2 sm:text-sm">
                                <User class="h-3.5 w-3.5 text-blue-400 sm:h-4 sm:w-4" />
                                Your Name
                            </label>
                            <div class="group relative">
                                <div
                                    class="absolute inset-0 rounded-lg bg-blue-500/10 opacity-0 blur transition-opacity group-focus-within:opacity-100 sm:rounded-xl"
                                ></div>
                                <div class="relative">
                                    <User
                                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-text-tertiary transition-colors group-focus-within:text-blue-400 sm:left-4 sm:h-5 sm:w-5"
                                    />
                                    <input
                                        v-model="createForm.player_name"
                                        type="text"
                                        required
                                        maxlength="30"
                                        class="input-field w-full rounded-lg py-3 pr-3 pl-9 text-sm text-text-primary placeholder-text-tertiary sm:rounded-xl sm:py-4 sm:pr-4 sm:pl-12"
                                        placeholder="Enter your name"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Lobby Name -->
                        <div class="animate-slide-in-up delay-200">
                            <label class="mb-2 block flex items-center gap-1.5 text-xs font-medium text-text-secondary sm:gap-2 sm:text-sm">
                                <Building2 class="h-3.5 w-3.5 text-blue-400 sm:h-4 sm:w-4" />
                                Lobby Name
                                <span class="text-[10px] font-normal text-text-tertiary sm:text-xs">(optional)</span>
                            </label>
                            <div class="group relative">
                                <div
                                    class="absolute inset-0 rounded-lg bg-blue-500/10 opacity-0 blur transition-opacity group-focus-within:opacity-100 sm:rounded-xl"
                                ></div>
                                <div class="relative">
                                    <Building2
                                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-text-tertiary transition-colors group-focus-within:text-blue-400 sm:left-4 sm:h-5 sm:w-5"
                                    />
                                    <input
                                        v-model="createForm.lobby_name"
                                        type="text"
                                        maxlength="50"
                                        class="input-field w-full rounded-lg py-3 pr-3 pl-9 text-sm text-text-primary placeholder-text-tertiary sm:rounded-xl sm:py-4 sm:pr-4 sm:pl-12"
                                        placeholder="Give your lobby a name"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            :disabled="createForm.processing"
                            class="btn-primary animate-slide-in-up flex w-full items-center justify-center gap-1.5 rounded-lg py-3 text-sm font-bold text-white delay-300 sm:gap-2 sm:rounded-xl sm:py-4"
                        >
                            <template v-if="createForm.processing">
                                <div class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white sm:h-5 sm:w-5"></div>
                                <span class="sm:hidden">Creating...</span>
                                <span class="hidden sm:inline">Creating...</span>
                            </template>
                            <template v-else>
                                <Sparkles class="h-4 w-4 sm:h-5 sm:w-5" />
                                <span class="sm:hidden">Create</span>
                                <span class="hidden sm:inline">Create Lobby</span>
                                <ArrowRight class="h-4 w-4 sm:h-5 sm:w-5" />
                            </template>
                        </button>
                    </form>
                </div>

                <!-- Join Lobby Form -->
                <div v-else class="animate-fade-in p-3 sm:p-5">
                    <form @submit.prevent="joinForm.post(`/lobby/join/${joinForm.code.toUpperCase()}`)" class="space-y-4 sm:space-y-5">
                        <!-- Player Name -->
                        <div class="animate-slide-in-up delay-100">
                            <label class="mb-2 block flex items-center gap-1.5 text-xs font-medium text-text-secondary sm:gap-2 sm:text-sm">
                                <User class="h-3.5 w-3.5 text-blue-400 sm:h-4 sm:w-4" />
                                Your Name
                            </label>
                            <div class="group relative">
                                <div
                                    class="absolute inset-0 rounded-lg bg-blue-500/10 opacity-0 blur transition-opacity group-focus-within:opacity-100 sm:rounded-xl"
                                ></div>
                                <div class="relative">
                                    <User
                                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-text-tertiary transition-colors group-focus-within:text-blue-400 sm:left-4 sm:h-5 sm:w-5"
                                    />
                                    <input
                                        v-model="joinForm.player_name"
                                        type="text"
                                        required
                                        maxlength="30"
                                        class="input-field w-full rounded-lg py-3 pr-3 pl-9 text-sm text-text-primary placeholder-text-tertiary sm:rounded-xl sm:py-4 sm:pr-4 sm:pl-12"
                                        placeholder="Enter your name"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Lobby Code -->
                        <div class="animate-slide-in-up delay-200">
                            <label class="mb-2 block flex items-center gap-1.5 text-xs font-medium text-text-secondary sm:gap-2 sm:text-sm">
                                <Copy class="h-3.5 w-3.5 text-blue-400 sm:h-4 sm:w-4" />
                                Lobby Code
                            </label>
                            <div class="group relative">
                                <div
                                    class="absolute inset-0 rounded-lg bg-blue-500/10 opacity-0 blur transition-opacity group-focus-within:opacity-100 sm:rounded-xl"
                                ></div>
                                <div class="relative">
                                    <Copy
                                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-text-tertiary transition-colors group-focus-within:text-blue-400 sm:left-4 sm:h-5 sm:w-5"
                                    />
                                    <input
                                        v-model="joinForm.code"
                                        type="text"
                                        required
                                        maxlength="6"
                                        class="input-field w-full rounded-lg py-3 pr-3 pl-9 text-center font-mono text-xl font-bold tracking-[0.3em] text-text-primary uppercase placeholder-text-tertiary sm:rounded-xl sm:py-4 sm:pr-4 sm:pl-12 sm:text-2xl sm:tracking-[0.4em]"
                                        placeholder="XXXXXX"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            :disabled="joinForm.processing"
                            class="btn-secondary animate-slide-in-up flex w-full items-center justify-center gap-1.5 rounded-lg py-3 text-sm font-bold delay-300 sm:gap-2 sm:rounded-xl sm:py-4"
                            :class="joinForm.processing ? 'cursor-not-allowed opacity-70' : ''"
                        >
                            <template v-if="joinForm.processing">
                                <div class="h-4 w-4 animate-spin rounded-full border-2 border-blue-400/30 border-t-blue-400 sm:h-5 sm:w-5"></div>
                                <span class="text-blue-400">Joining...</span>
                            </template>
                            <template v-else>
                                <LogIn class="h-4 w-4 text-blue-400 sm:h-5 sm:w-5" />
                                <span class="text-blue-400 sm:hidden">Join</span>
                                <span class="hidden text-blue-400 sm:inline">Join Lobby</span>
                                <ArrowRight class="h-4 w-4 text-blue-400 sm:h-5 sm:w-5" />
                            </template>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Errors -->
            <div
                v-if="createForm.errors.player_name || createFormErrors.code || joinForm.errors.player_name || joinFormErrors.code"
                class="glass animate-bounce-in mt-4 rounded-xl border border-red-500/30 bg-red-900/20 p-3 sm:mt-5 sm:rounded-2xl sm:p-4"
            >
                <div class="flex items-start gap-2 sm:gap-3">
                    <ShieldAlert class="mt-0.5 h-4 w-4 flex-shrink-0 text-red-400 sm:h-5 sm:w-5" />
                    <div class="space-y-0.5 sm:space-y-1">
                        <p v-if="createForm.errors.player_name" class="text-xs text-red-300 sm:text-sm">{{ createForm.errors.player_name }}</p>
                        <p v-if="createFormErrors.code" class="text-xs text-red-300 sm:text-sm">{{ createFormErrors.code }}</p>
                        <p v-if="joinForm.errors.player_name" class="text-xs text-red-300 sm:text-sm">{{ joinForm.errors.player_name }}</p>
                        <p v-if="joinFormErrors.code" class="text-xs text-red-300 sm:text-sm">{{ joinFormErrors.code }}</p>
                    </div>
                </div>
            </div>

            <!-- Feature Highlights -->
            <div class="animate-fade-in mt-6 grid grid-cols-3 gap-2 px-1 delay-500 sm:mt-8 sm:gap-3 sm:px-0">
                <div class="glass-light card-interactive rounded-lg p-2 text-center sm:rounded-xl sm:p-3">
                    <div
                        class="mx-auto mb-1.5 flex h-8 w-8 items-center justify-center rounded-md bg-blue-500/20 sm:mb-2 sm:h-10 sm:w-10 sm:rounded-lg"
                    >
                        <Users class="h-4 w-4 text-blue-400 sm:h-5 sm:w-5" />
                    </div>
                    <p class="text-[10px] font-medium text-text-secondary sm:text-xs">4-20 Players</p>
                </div>
                <div class="glass-light card-interactive rounded-lg p-2 text-center sm:rounded-xl sm:p-3">
                    <div
                        class="mx-auto mb-1.5 flex h-8 w-8 items-center justify-center rounded-md bg-blue-500/20 sm:mb-2 sm:h-10 sm:w-10 sm:rounded-lg"
                    >
                        <Sparkles class="h-4 w-4 text-blue-400 sm:h-5 sm:w-5" />
                    </div>
                    <p class="text-[10px] font-medium text-text-secondary sm:text-xs">Word Guessing</p>
                </div>
                <div class="glass-light card-interactive rounded-lg p-2 text-center sm:rounded-xl sm:p-3">
                    <div
                        class="mx-auto mb-1.5 flex h-8 w-8 items-center justify-center rounded-md bg-blue-500/20 sm:mb-2 sm:h-10 sm:w-10 sm:rounded-lg"
                    >
                        <ShieldAlert class="h-4 w-4 text-blue-400 sm:h-5 sm:w-5" />
                    </div>
                    <p class="text-[10px] font-medium text-text-secondary sm:text-xs">Find Impostor</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="animate-fade-in mt-6 text-center delay-600 sm:mt-8">
                <p class="text-xs text-text-tertiary sm:text-sm">A social deduction game for friends</p>
            </div>
        </div>
    </div>
</template>
