<script setup lang="ts">
import { Crown, User, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';
import { avatarUrl } from '@/lib/avatars';

interface Player {
    id: number;
    name: string;
    is_eliminated: boolean;
    turn_position: number;
    avatar: string | null;
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
    return props.players.filter((p) => !p.is_eliminated).sort((a, b) => (a.turn_position ?? 999) - (b.turn_position ?? 999));
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

// Radius in pixels for positioning (increased to give arrow plenty of visible room)
const RADIUS_PX = 140;
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
        top: `calc(50% + ${y}px)`,
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

// Arrow should stop before the player avatar so it's visible (leave a 16px gap)
const arrowLength = computed(() => RADIUS_PX - CENTER_SIZE / 2 - 16);
</script>

<template>
    <div class="turn-indicator">
        <!-- Turns played counter -->
        <div class="mb-4 flex justify-center">
            <div
                class="rounded-full border border-void-border bg-void-elevated px-4 py-1.5 text-sm font-bold text-text-primary shadow-lg backdrop-blur-sm"
            >
                <span class="text-text-secondary">Turns played:</span>
                <span class="ml-2 text-blue-400">{{ turnsPlayed }}</span>
            </div>
        </div>

        <!-- Circle area -->
        <div class="relative mx-auto w-full max-w-[380px]" style="height: 340px">
            <!-- Background circle track -->
            <div
                class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-dashed border-void-border/50"
                :style="{ width: `${RADIUS_PX * 2}px`, height: `${RADIUS_PX * 2}px` }"
            ></div>

            <!-- Rotating Arrow using SVG assets -->
            <div
                v-if="currentTurnIndexInCircle >= 0 && circlePlayers.length > 0"
                class="absolute top-1/2 left-1/2 z-10 origin-center -translate-x-1/2 -translate-y-1/2 transition-transform duration-500 ease-out"
                :style="{ transform: `rotate(${arrowRotationDeg}deg)` }"
            >
                <div
                    class="relative flex items-center"
                    :style="{
                        width: `${arrowLength + 24}px`,
                        transform: `translateX(${CENTER_SIZE / 2 - 6}px)`,
                    }"
                >
                    <!-- Shaft SVG -->
                    <img
                        src="/images/Shaft.svg"
                        class="h-4 w-full"
                        :class="isMyTurn ? 'brightness-125' : 'opacity-80'"
                        alt=""
                    />
                    <!-- Arrow Head SVG -->
                    <img
                        src="/images/Arrow_head.svg"
                        class="absolute -right-2.5 h-6 w-6 flex-shrink-0"
                        :class="isMyTurn ? 'brightness-125' : 'opacity-80'"
                        alt=""
                    />
                </div>
            </div>

            <!-- Center Indicator using SVG -->
            <div class="absolute top-1/2 left-1/2 z-20 -translate-x-1/2 -translate-y-1/2">
                <div class="relative flex h-16 w-16 items-center justify-center">
                    <!-- Center SVG background -->
                    <img
                        src="/images/Center.svg"
                        class="absolute inset-0 h-full w-full"
                        :class="isMyTurn ? 'drop-shadow-[0_0_10px_rgba(59,130,246,0.6)]' : 'opacity-90'"
                        alt=""
                    />
                    <div v-if="isMyTurn" class="absolute -inset-2 animate-ping rounded-full bg-blue-400/10"></div>
                    <div class="relative z-10 text-center">
                        <div class="text-2xl leading-none font-black text-blue-400">
                            {{ currentTurnIndexInCircle >= 0 ? currentTurnIndexInCircle + 1 : '–' }}
                        </div>
                        <div class="text-[10px] font-medium tracking-wider text-text-tertiary">/{{ circlePlayers.length }}</div>
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
                        class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-xl text-base font-bold shadow-lg transition-all duration-300"
                        :class="[
                            player.id === currentTurnPlayerId
                                ? 'bg-gradient-to-br from-blue-400 to-blue-500 text-white ring-2 shadow-blue-500/40 ring-white/50'
                                : 'border border-void-border bg-void-hover text-text-secondary backdrop-blur-sm',
                            player.id === currentPlayerId ? 'ring-offset-void ring-2 ring-blue-400 ring-offset-2' : '',
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

                    <!-- Player name -->
                    <span
                        class="max-w-[80px] truncate rounded-full bg-void-elevated px-2.5 py-1 text-center text-xs font-bold shadow-lg backdrop-blur-sm"
                        :class="player.id === currentTurnPlayerId ? 'bg-void-hover text-blue-400' : 'text-text-secondary'"
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
                        ? 'scale-105 border border-blue-500/50 bg-blue-500/20 text-blue-300 shadow-lg shadow-blue-500/20'
                        : 'border border-void-border bg-void-elevated text-text-secondary',
                ]"
            >
                <template v-if="isMyTurn">
                    <Crown class="h-5 w-5 text-blue-400" />
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
                class="btn-primary group flex items-center justify-center gap-2 rounded-xl px-8 py-3 text-base font-bold text-white shadow-xl shadow-blue-500/30 transition-all hover:scale-105 hover:shadow-blue-500/50 active:scale-95"
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
    font-family:
        system-ui,
        -apple-system,
        sans-serif;
}

/* Smooth transitions for all interactive elements */
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 300ms;
}
</style>
