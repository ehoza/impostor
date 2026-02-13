<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Users, Plus, LogIn, User, Building2, Sparkles, ShieldAlert, ArrowRight, Copy, Gamepad2 } from 'lucide-vue-next';
import { ref } from 'vue';

const createForm = useForm({
    player_name: '',
    lobby_name: '',
});

const joinForm = useForm({
    player_name: '',
    code: '',
});

const activeTab = ref<'create' | 'join'>('create');
</script>

<template>
    <Head title="Impostor" />
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-gray-950 via-gray-900 to-gray-950 p-4">
        <!-- Animated Background -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <!-- Grid Pattern -->
            <div class="bg-grid-pattern absolute inset-0 opacity-30"></div>

            <!-- Floating Orbs -->
            <div class="animate-float-slow absolute top-1/4 left-1/4 h-96 w-96 rounded-full bg-red-600/10 blur-[100px]"></div>
            <div
                class="animate-float absolute right-1/4 bottom-1/4 h-[500px] w-[500px] rounded-full bg-blue-600/10 blur-[120px]"
                style="animation-delay: -2s"
            ></div>
            <div
                class="absolute top-1/2 left-1/2 h-[600px] w-[600px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-purple-600/5 blur-[150px]"
            ></div>

            <!-- Accent Lines -->
            <div class="absolute top-0 left-0 h-px w-full bg-gradient-to-r from-transparent via-red-500/30 to-transparent"></div>
            <div class="absolute bottom-0 left-0 h-px w-full bg-gradient-to-r from-transparent via-blue-500/30 to-transparent"></div>
        </div>

        <div class="relative z-10 w-full max-w-lg">
            <!-- Hero Section -->
            <div class="animate-fade-in-blur mb-10 text-center">
                <!-- Logo -->
                <div class="relative mb-6 inline-block">
                    <div class="animate-pulse-glow absolute inset-0 rounded-3xl bg-red-500/20 blur-2xl"></div>
                    <div
                        class="animate-float relative mx-auto flex h-24 w-24 items-center justify-center rounded-3xl bg-gradient-to-br from-red-500 via-red-600 to-red-700 shadow-2xl shadow-red-500/30"
                    >
                        <ShieldAlert class="h-12 w-12 text-white" stroke-width="{1.5}" />
                    </div>
                    <!-- Floating accent -->
                    <div
                        class="animate-bounce-in absolute -top-2 -right-2 flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-amber-400 to-orange-500 shadow-lg delay-500"
                    >
                        <Sparkles class="h-4 w-4 text-white" />
                    </div>
                </div>

                <h1 class="gradient-text text-shadow mb-3 text-6xl font-black tracking-tight">IMPOSTOR</h1>
                <p class="flex items-center justify-center gap-2 text-lg text-gray-400">
                    <Gamepad2 class="h-5 w-5 text-red-400" />
                    Find the impostor among your friends
                    <Gamepad2 class="h-5 w-5 text-blue-400" />
                </p>
            </div>

            <!-- Main Card -->
            <div class="glass-card animate-fade-in-scale rounded-3xl p-2 delay-200">
                <!-- Tab Switcher -->
                <div class="mb-4 flex gap-1 rounded-2xl bg-gray-900/50 p-1.5">
                    <button
                        @click="activeTab = 'create'"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-3.5 font-semibold transition-all duration-300"
                        :class="
                            activeTab === 'create'
                                ? 'bg-gradient-to-r from-red-600 to-red-700 text-white shadow-lg shadow-red-600/25'
                                : 'text-gray-400 hover:bg-gray-800/50 hover:text-white'
                        "
                    >
                        <Plus class="h-5 w-5" />
                        Create Lobby
                    </button>
                    <button
                        @click="activeTab = 'join'"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-3.5 font-semibold transition-all duration-300"
                        :class="
                            activeTab === 'join'
                                ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-lg shadow-blue-600/25'
                                : 'text-gray-400 hover:bg-gray-800/50 hover:text-white'
                        "
                    >
                        <LogIn class="h-5 w-5" />
                        Join Game
                    </button>
                </div>

                <!-- Create Lobby Form -->
                <div v-if="activeTab === 'create'" class="animate-fade-in p-5">
                    <form @submit.prevent="createForm.post('/lobby/create')" class="space-y-5">
                        <!-- Player Name -->
                        <div class="animate-slide-in-up delay-100">
                            <label class="mb-2.5 block flex items-center gap-2 text-sm font-medium text-gray-300">
                                <User class="h-4 w-4 text-red-400" />
                                Your Name
                            </label>
                            <div class="group relative">
                                <div
                                    class="absolute inset-0 rounded-xl bg-red-500/10 opacity-0 blur transition-opacity group-focus-within:opacity-100"
                                ></div>
                                <div class="relative">
                                    <User
                                        class="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-gray-400 transition-colors group-focus-within:text-red-400"
                                    />
                                    <input
                                        v-model="createForm.player_name"
                                        type="text"
                                        required
                                        maxlength="30"
                                        class="input-field w-full rounded-xl py-4 pr-4 pl-12 text-white placeholder-gray-500"
                                        placeholder="Enter your name"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Lobby Name -->
                        <div class="animate-slide-in-up delay-200">
                            <label class="mb-2.5 block flex items-center gap-2 text-sm font-medium text-gray-300">
                                <Building2 class="h-4 w-4 text-red-400" />
                                Lobby Name
                                <span class="text-xs font-normal text-gray-500">(optional)</span>
                            </label>
                            <div class="group relative">
                                <div
                                    class="absolute inset-0 rounded-xl bg-red-500/10 opacity-0 blur transition-opacity group-focus-within:opacity-100"
                                ></div>
                                <div class="relative">
                                    <Building2
                                        class="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-gray-400 transition-colors group-focus-within:text-red-400"
                                    />
                                    <input
                                        v-model="createForm.lobby_name"
                                        type="text"
                                        maxlength="50"
                                        class="input-field w-full rounded-xl py-4 pr-4 pl-12 text-white placeholder-gray-500"
                                        placeholder="Give your lobby a name"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            :disabled="createForm.processing"
                            class="btn-primary animate-slide-in-up flex w-full items-center justify-center gap-2 rounded-xl py-4 font-bold text-white delay-300"
                        >
                            <template v-if="createForm.processing">
                                <div class="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white"></div>
                                Creating...
                            </template>
                            <template v-else>
                                <Sparkles class="h-5 w-5" />
                                Create Lobby
                                <ArrowRight class="h-5 w-5" />
                            </template>
                        </button>
                    </form>
                </div>

                <!-- Join Lobby Form -->
                <div v-else class="animate-fade-in p-5">
                    <form @submit.prevent="joinForm.post(`/lobby/join/${joinForm.code.toUpperCase()}`)" class="space-y-5">
                        <!-- Player Name -->
                        <div class="animate-slide-in-up delay-100">
                            <label class="mb-2.5 block flex items-center gap-2 text-sm font-medium text-gray-300">
                                <User class="h-4 w-4 text-blue-400" />
                                Your Name
                            </label>
                            <div class="group relative">
                                <div
                                    class="absolute inset-0 rounded-xl bg-blue-500/10 opacity-0 blur transition-opacity group-focus-within:opacity-100"
                                ></div>
                                <div class="relative">
                                    <User
                                        class="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-gray-400 transition-colors group-focus-within:text-blue-400"
                                    />
                                    <input
                                        v-model="joinForm.player_name"
                                        type="text"
                                        required
                                        maxlength="30"
                                        class="input-field input-field-blue w-full rounded-xl py-4 pr-4 pl-12 text-white placeholder-gray-500"
                                        placeholder="Enter your name"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Lobby Code -->
                        <div class="animate-slide-in-up delay-200">
                            <label class="mb-2.5 block flex items-center gap-2 text-sm font-medium text-gray-300">
                                <Copy class="h-4 w-4 text-blue-400" />
                                Lobby Code
                            </label>
                            <div class="group relative">
                                <div
                                    class="absolute inset-0 rounded-xl bg-blue-500/10 opacity-0 blur transition-opacity group-focus-within:opacity-100"
                                ></div>
                                <div class="relative">
                                    <Copy
                                        class="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-gray-400 transition-colors group-focus-within:text-blue-400"
                                    />
                                    <input
                                        v-model="joinForm.code"
                                        type="text"
                                        required
                                        maxlength="6"
                                        class="input-field input-field-blue w-full rounded-xl py-4 pr-4 pl-12 text-center font-mono text-2xl font-bold tracking-[0.4em] text-white uppercase placeholder-gray-500"
                                        placeholder="XXXXXX"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            :disabled="joinForm.processing"
                            class="btn-secondary animate-slide-in-up flex w-full items-center justify-center gap-2 rounded-xl py-4 font-bold text-white delay-300"
                        >
                            <template v-if="joinForm.processing">
                                <div class="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white"></div>
                                Joining...
                            </template>
                            <template v-else>
                                <LogIn class="h-5 w-5" />
                                Join Lobby
                                <ArrowRight class="h-5 w-5" />
                            </template>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Errors -->
            <div
                v-if="createForm.errors.player_name || createForm.errors.code || joinForm.errors.player_name || joinForm.errors.code"
                class="glass animate-bounce-in mt-5 rounded-2xl border border-red-500/30 bg-red-900/20 p-4"
            >
                <div class="flex items-start gap-3">
                    <ShieldAlert class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-400" />
                    <div class="space-y-1">
                        <p v-if="createForm.errors.player_name" class="text-sm text-red-300">{{ createForm.errors.player_name }}</p>
                        <p v-if="createForm.errors.code" class="text-sm text-red-300">{{ createForm.errors.code }}</p>
                        <p v-if="joinForm.errors.player_name" class="text-sm text-red-300">{{ joinForm.errors.player_name }}</p>
                        <p v-if="joinForm.errors.code" class="text-sm text-red-300">{{ joinForm.errors.code }}</p>
                    </div>
                </div>
            </div>

            <!-- Feature Highlights -->
            <div class="animate-fade-in mt-8 grid grid-cols-3 gap-3 delay-500">
                <div class="glass-light card-interactive rounded-xl p-3 text-center">
                    <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-red-500/20">
                        <Users class="h-5 w-5 text-red-400" />
                    </div>
                    <p class="text-xs font-medium text-gray-300">4-20 Players</p>
                </div>
                <div class="glass-light card-interactive rounded-xl p-3 text-center">
                    <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500/20">
                        <Sparkles class="h-5 w-5 text-blue-400" />
                    </div>
                    <p class="text-xs font-medium text-gray-300">Word Guessing</p>
                </div>
                <div class="glass-light card-interactive rounded-xl p-3 text-center">
                    <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500/20">
                        <ShieldAlert class="h-5 w-5 text-amber-400" />
                    </div>
                    <p class="text-xs font-medium text-gray-300">Find Impostor</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="animate-fade-in mt-8 text-center delay-600">
                <p class="text-sm text-gray-500">A social deduction game for friends</p>
            </div>
        </div>
    </div>
</template>
