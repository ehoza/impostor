<script setup lang="ts">
import { Swords, RefreshCw, Check } from 'lucide-vue-next';
import { computed } from 'vue';

interface VoteData {
    count: number;
    threshold: number;
    progress: number;
    has_voted: boolean;
}

const props = defineProps<{
    voteData: VoteData;
    type: 'vote-now' | 'reroll';
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (e: 'vote'): void;
}>();

const isActivated = computed(() => {
    return props.voteData.count >= props.voteData.threshold;
});

const remainingVotes = computed(() => {
    return Math.max(0, props.voteData.threshold - props.voteData.count);
});

const isVoteNow = computed(() => props.type === 'vote-now');
</script>

<template>
    <div class="glass-card rounded-2xl p-4 transition-all" :class="isActivated ? 'border-green-500/30' : ''">
        <div class="mb-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl transition-colors"
                    :class="
                        isActivated ? 'bg-green-500/20 text-green-400' : isVoteNow ? 'bg-red-500/20 text-red-400' : 'bg-blue-500/20 text-blue-400'
                    "
                >
                    <component :is="isVoteNow ? Swords : RefreshCw" class="h-5 w-5" />
                </div>
                <div>
                    <h4 class="font-bold text-white">
                        {{ isVoteNow ? 'Vote Now' : 'Reroll Word' }}
                    </h4>
                    <p class="text-xs text-gray-500">
                        {{ isVoteNow ? 'Start voting immediately' : 'Get new words' }}
                    </p>
                </div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-black" :class="isActivated ? 'text-green-400' : 'text-white'">
                    {{ voteData.count }}/{{ voteData.threshold }}
                </div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="relative mb-3 h-2 overflow-hidden rounded-full bg-gray-800">
            <div
                class="absolute top-0 left-0 h-full rounded-full transition-all duration-500"
                :class="
                    isActivated
                        ? 'bg-gradient-to-r from-green-500 to-emerald-500'
                        : isVoteNow
                          ? 'bg-gradient-to-r from-red-500 to-red-600'
                          : 'bg-gradient-to-r from-blue-500 to-blue-600'
                "
                :style="{ width: `${Math.min(voteData.progress, 100)}%` }"
            ></div>
        </div>

        <!-- Vote Button -->
        <button
            @click="emit('vote')"
            :disabled="voteData.has_voted || isActivated || disabled"
            class="flex w-full items-center justify-center gap-2 rounded-xl py-3 font-semibold transition-all"
            :class="[
                isActivated
                    ? 'cursor-default bg-green-600 text-white'
                    : voteData.has_voted
                      ? 'cursor-default bg-gray-700 text-gray-400'
                      : disabled
                        ? 'cursor-not-allowed bg-gray-800 text-gray-500'
                        : isVoteNow
                          ? 'bg-gradient-to-r from-red-600 to-red-700 text-white shadow-lg shadow-red-600/20 hover:from-red-500 hover:to-red-600'
                          : 'bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-lg shadow-blue-600/20 hover:from-blue-500 hover:to-blue-600',
            ]"
        >
            <Check v-if="isActivated || voteData.has_voted" class="h-4 w-4" />
            <Swords v-else-if="isVoteNow" class="h-4 w-4" />
            <RefreshCw v-else class="h-4 w-4" />

            <span v-if="isActivated">Activated!</span>
            <span v-else-if="voteData.has_voted">Voted</span>
            <span v-else-if="disabled">Eliminated</span>
            <span v-else>{{ isVoteNow ? 'Vote Now' : 'Reroll' }}</span>
        </button>

        <!-- Status -->
        <p class="mt-2 text-center text-xs font-medium" :class="isActivated ? 'text-green-400' : 'text-gray-500'">
            <span v-if="isActivated">Threshold reached! Starting soon...</span>
            <span v-else-if="remainingVotes === 1">1 more vote needed</span>
            <span v-else>{{ remainingVotes }} more votes needed</span>
        </p>
    </div>
</template>
