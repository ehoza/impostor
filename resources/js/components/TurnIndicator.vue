<script setup lang="ts">
import { Crown, ArrowRight, User, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

interface Player {
    id: number;
    name: string;
    is_eliminated: boolean;
    turn_position: number;
}

const props = defineProps<{
    players: Player[];
    currentTurnPlayerId: number | null;
    currentPlayerId: number;
    currentRound: number;
    currentTurnIndex: number;
    turnOrderLength: number;
}>();

const emit = defineEmits<{
    (e: 'next-turn'): void;
}>();

const activePlayers = computed(() => {
    return props.players
        .filter((p) => !p.is_eliminated)
        .sort((a, b) => (a.turn_position ?? 999) - (b.turn_position ?? 999));
});

const currentTurnPlayer = computed(() => {
    return activePlayers.value.find((p) => p.id === props.currentTurnPlayerId);
});

const isMyTurn = computed(() => {
    return props.currentTurnPlayerId === props.currentPlayerId;
});

const turnsPlayed = computed(() => {
    const n = Math.max(1, props.turnOrderLength);
    return (props.currentRound - 1) * n + props.currentTurnIndex;
});

const CIRCLE_MAX_PLAYERS = 10;
const circlePlayers = computed(() => activePlayers.value.slice(0, CIRCLE_MAX_PLAYERS));

// Radius in pixels for positioning (container is 280px height, so radius ~110px)
const RADIUS_PX = 105;
const CENTER_SIZE = 64; // w-16 = 64px

function positionOnCircle(index: number, total: number): { left: string; top: string } {
    if (total <= 0) return { left: '50%', top: '50%' };
    // Start from top (-90deg offset) and go clockwise
    const angleDeg = (360 / total) * index - 90;
    const angleRad = (angleDeg * Math.PI) / 180;
    const x = Math.cos(angleRad) * RADIUS_PX;
    const y = Math.sin(angleRad) * RADIUS_PX;
    return { 
        left: `calc(50% + ${x}px)`, 
        top: `calc(50% + ${y}px)` 
    };
}

const currentTurnIndexInCircle = computed(() => {
    if (props.currentTurnPlayerId == null) return -1;
    return circlePlayers.value.findIndex((p) => p.id === props.currentTurnPlayerId);
});

const arrowRotationDeg = computed(() => {
    const n = circlePlayers.value.length;
    const idx = currentTurnIndexInCircle.value;
    if (n <= 0 || idx < 0) return -90; // Point up by default
    // Match the same angle calculation as player positioning
    return (360 / n) * idx - 90;
});

// Arrow should extend from center to the player circle
const arrowLength = computed(() => RADIUS_PX - (CENTER_SIZE / 2) + 10);
</script>

<template>
    <div class="turn-indicator">
        <!-- Turns played counter -->
        <div class="mb-4 flex justify-center">
            <div class="rounded-full border border-gray-600/50 bg-gray-800/90 px-4 py-1.5 text-sm font-bold text-white shadow-lg backdrop-blur-sm">
                <span class="text-gray-400">Turns played:</span>
                <span class="ml-2 text-amber-400">{{ turnsPlayed }}</span>
            </div>
        </div>

        <!-- Circle area -->
        <div class="relative mx-auto w-full max-w-[320px]" style="height: 280px;">
            <!-- Background circle track -->
            <div
                class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-dashed border-gray-600/30"
                :style="{ width: `${RADIUS_PX * 2}px`, height: `${RADIUS_PX * 2}px` }"
            ></div>

            <!-- Rotating Arrow - positioned BEHIND center but IN FRONT of background -->
            <div
                v-if="currentTurnIndexInCircle >= 0 && circlePlayers.length > 0"
                class="absolute left-1/2 top-1/2 z-10 -translate-x-1/2 -translate-y-1/2 origin-center transition-transform duration-500 ease-out"
                :style="{ transform: `rotate(${arrowRotationDeg}deg)` }"
            >
                <!-- Arrow shaft extending from center outward -->
                <div 
                    class="relative flex items-center"
                    :style="{ 
                        width: `${arrowLength}px`,
                        transform: `translateX(${CENTER_SIZE/2}px)` // Start from edge of center circle
                    }"
                >
                    <div class="h-2 w-full rounded-full bg-gradient-to-r from-amber-500 to-orange-500 shadow-lg shadow-amber-500/50"></div>
                    <ArrowRight class="absolute -right-1 h-6 w-6 flex-shrink-0 text-orange-500" stroke-width="3" />
                </div>
            </div>

            <!-- Center Bomb/Indicator -->
            <div class="absolute left-1/2 top-1/2 z-20 -translate-x-1/2 -translate-y-1/2">
                <div
                    class="relative flex h-16 w-16 items-center justify-center rounded-full shadow-xl transition-all duration-300"
                    :class="[
                        isMyTurn
                            ? 'animate-pulse bg-gradient-to-br from-amber-400 to-orange-600 shadow-amber-500/50 ring-4 ring-amber-500/30'
                            : 'border-2 border-gray-500 bg-gray-800 shadow-black/50'
                    ]"
                >
                    <!-- Bomb fuse decoration when it's user's turn -->
                    <div v-if="isMyTurn" class="absolute -inset-1 rounded-full animate-ping bg-amber-400/20"></div>
                    
                    <div class="text-center relative z-10">
                        <div class="text-2xl font-black leading-none" :class="isMyTurn ? 'text-white' : 'text-amber-400'">
                            {{ currentTurnIndexInCircle >= 0 ? currentTurnIndexInCircle + 1 : '–' }}
                        </div>
                        <div class="text-xs font-medium tracking-wider text-gray-500">/{{ circlePlayers.length }}</div>
                    </div>
                </div>
            </div>

            <!-- Players on circle -->
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
                    class="flex flex-col items-center gap-1.5 transition-all duration-300"
                    :class="player.id === currentTurnPlayerId ? 'scale-125' : 'opacity-70 hover:opacity-100'"
                >
                    <!-- Player avatar -->
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl text-base font-bold shadow-lg transition-all duration-300"
                        :class="[
                            player.id === currentTurnPlayerId
                                ? 'bg-gradient-to-br from-amber-400 to-orange-500 text-white shadow-amber-500/40 ring-2 ring-white/50'
                                : 'bg-gray-700/80 text-gray-300 border border-gray-600/50 backdrop-blur-sm',
                            player.id === currentPlayerId ? 'ring-2 ring-blue-400 ring-offset-2 ring-offset-gray-900' : ''
                        ]"
                    >
                        {{ player.name.charAt(0).toUpperCase() }}
                    </div>
                    
                    <!-- Player name -->
                    <span
                        class="max-w-[80px] truncate rounded-full bg-gray-900/90 px-2.5 py-1 text-center text-xs font-bold shadow-lg backdrop-blur-sm"
                        :class="player.id === currentTurnPlayerId ? 'text-amber-300 bg-gray-800' : 'text-gray-400'"
                    >
                        {{ player.name.length > 8 ? player.name.slice(0, 7) + '…' : player.name }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Status + End turn -->
        <div class="mt-6 flex flex-col items-center gap-3">
            <div
                class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold transition-all duration-300"
                :class="[
                    isMyTurn 
                        ? 'bg-amber-500/20 text-amber-300 border border-amber-500/50 shadow-lg shadow-amber-500/20 scale-105' 
                        : 'bg-gray-800/80 text-gray-400 border border-gray-700'
                ]"
            >
                <template v-if="isMyTurn">
                    <Crown class="h-5 w-5 text-amber-400" />
                    <span>Your Turn!</span>
                </template>
                <template v-else-if="currentTurnPlayer">
                    <User class="h-4 w-4" />
                    <span>{{ currentTurnPlayer.name }}'s turn</span>
                </template>
                <template v-else>
                    <span>Waiting...</span>
                </template>
            </div>
            
            <button
                v-if="isMyTurn"
                type="button"
                @click="emit('next-turn')"
                class="group flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 px-8 py-3 text-base font-bold text-white shadow-xl shadow-orange-500/30 transition-all hover:scale-105 hover:shadow-orange-500/50 active:scale-95"
            >
                End My Turn
                <ChevronRight class="h-5 w-5 transition-transform group-hover:translate-x-1" />
            </button>
        </div>
    </div>
</template>

<style scoped>
.turn-indicator {
    min-width: 0;
    font-family: system-ui, -apple-system, sans-serif;
}

/* Smooth transitions for all interactive elements */
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 300ms;
}
</style>