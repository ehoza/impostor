<script setup lang="ts">
import { Crown, ArrowRight, User } from 'lucide-vue-next';
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
}>();

defineEmits<{
    (e: 'next-turn'): void;
}>();

const activePlayers = computed(() => {
    return props.players.filter((p) => !p.is_eliminated).sort((a, b) => (a.turn_position || 0) - (b.turn_position || 0));
});

const currentTurnIndex = computed(() => {
    return activePlayers.value.findIndex((p) => p.id === props.currentTurnPlayerId);
});

const currentTurnPlayer = computed(() => {
    return activePlayers.value.find((p) => p.id === props.currentTurnPlayerId);
});

const isMyTurn = computed(() => {
    return props.currentTurnPlayerId === props.currentPlayerId;
});

// Get up to 4 players to display in cardinal positions
const displayPlayers = computed(() => {
    return activePlayers.value.slice(0, 4);
});

// Calculate positions for players in a circle
const getPlayerPosition = (index: number) => {
    const positions = [
        { top: '5%', left: '50%', transform: 'translateX(-50%)' }, // Top
        { top: '50%', right: '5%', transform: 'translateY(-50%)' }, // Right
        { bottom: '5%', left: '50%', transform: 'translateX(-50%)' }, // Bottom
        { top: '50%', left: '5%', transform: 'translateY(-50%)' }, // Left
    ];
    return positions[index % 4];
};

// Calculate arrow rotation based on current turn
const arrowRotation = computed(() => {
    const rotations = [-90, 0, 90, 180]; // Top, Right, Bottom, Left
    return rotations[currentTurnIndex.value % 4] || -90;
});
</script>

<template>
    <div class="relative h-44 w-full">
        <!-- Background Circle -->
        <div
            class="absolute top-1/2 left-1/2 h-32 w-32 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-dashed border-gray-700/50"
        ></div>

        <!-- Central Hub -->
        <div class="absolute top-1/2 left-1/2 z-20 -translate-x-1/2 -translate-y-1/2">
            <!-- Rotating Arrow -->
            <div
                class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 transition-transform duration-700"
                :style="{ transform: `translate(-50%, -50%) rotate(${arrowRotation}deg)` }"
            >
                <div class="relative">
                    <div class="h-0.5 w-14 bg-gradient-to-r from-amber-500 to-orange-500"></div>
                    <div class="absolute top-1/2 right-0 translate-x-0.5 -translate-y-1/2">
                        <ArrowRight class="h-4 w-4 text-amber-500" />
                    </div>
                </div>
            </div>

            <!-- Center Circle -->
            <div
                class="flex h-14 w-14 items-center justify-center rounded-full shadow-lg transition-all"
                :class="
                    isMyTurn
                        ? 'animate-pulse bg-gradient-to-br from-amber-500 to-orange-600 shadow-amber-500/50'
                        : 'border-2 border-gray-700 bg-gray-800'
                "
            >
                <div class="text-center">
                    <div class="text-lg font-black" :class="isMyTurn ? 'text-white' : 'text-amber-400'">
                        {{ currentTurnIndex + 1 }}
                    </div>
                    <div class="text-[10px] tracking-wider uppercase" :class="isMyTurn ? 'text-white/80' : 'text-gray-500'">
                        /{{ activePlayers.length }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Player Positions -->
        <div v-for="(player, index) in displayPlayers" :key="player.id" class="absolute z-10" :style="getPlayerPosition(index)">
            <div
                class="flex flex-col items-center gap-1 transition-all duration-300"
                :class="player.id === currentTurnPlayerId ? 'scale-110' : 'opacity-60'"
            >
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl text-sm font-bold transition-all"
                    :class="[
                        player.id === currentTurnPlayerId
                            ? 'bg-gradient-to-br from-amber-500 to-orange-500 text-white shadow-lg shadow-amber-500/30'
                            : 'glass-light text-gray-300',
                        player.id === currentPlayerId ? 'ring-2 ring-blue-500' : '',
                    ]"
                >
                    {{ player.name.charAt(0).toUpperCase() }}
                </div>
                <div
                    class="rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap"
                    :class="player.id === currentTurnPlayerId ? 'bg-amber-500/20 text-amber-300' : 'bg-gray-800/50 text-gray-400'"
                >
                    {{ player.name.length > 8 ? player.name.slice(0, 8) + '...' : player.name }}
                </div>
            </div>
        </div>

        <!-- Status Badge -->
        <div class="absolute -bottom-1 left-1/2 w-full -translate-x-1/2 translate-y-full text-center">
            <div
                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium"
                :class="isMyTurn ? 'border border-amber-500/30 bg-amber-500/20 text-amber-300' : 'bg-gray-800/50 text-gray-400'"
            >
                <template v-if="isMyTurn">
                    <Crown class="h-3 w-3" />
                    Your Turn!
                </template>
                <template v-else-if="currentTurnPlayer">
                    <User class="h-3 w-3" />
                    {{ currentTurnPlayer.name }}'s turn
                </template>
            </div>
        </div>
    </div>
</template>
