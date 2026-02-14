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
    /** When disabled, show this instead of generic "Eliminated" (e.g. "Impostors can't reroll"). */
    disabledLabel?: string;
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
    <div class="glass-card rounded-2xl p-4 transition-all" :class="isActivated ? 'border-blue-500/30' : ''">
        <div class="mb-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl transition-colors"
                    :class="isActivated ? 'bg-blue-500/20 text-blue-400' : isVoteNow ? 'bg-red-500/20 text-red-400' : 'bg-blue-500/20 text-blue-400'"
                >
                    <component :is="isVoteNow ? Swords : RefreshCw" class="h-5 w-5" />
                </div>
                <div>
                    <h4 class="font-bold text-text-primary">
                        {{ isVoteNow ? 'Vote Now' : 'Reroll Word' }}
                    </h4>
                    <p class="text-xs text-text-tertiary">
                        {{ isVoteNow ? 'Start voting immediately' : 'Get new words' }}
                    </p>
                </div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-black" :class="isActivated ? 'text-blue-400' : 'text-text-primary'">
                    {{ voteData.count }}/{{ voteData.threshold }}
                </div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="relative mb-3 h-2 overflow-hidden rounded-full bg-void-hover">
            <div
                class="absolute top-0 left-0 h-full rounded-full transition-all duration-500"
                :class="
                    isActivated
                        ? 'bg-gradient-to-r from-blue-500 to-blue-400'
                        : isVoteNow
                          ? 'bg-gradient-to-r from-red-500 to-red-400'
                          : 'bg-gradient-to-r from-blue-500 to-blue-400'
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
                    ? 'cursor-default bg-blue-600 text-white'
                    : voteData.has_voted
                      ? 'cursor-default bg-void-hover text-text-secondary'
                      : disabled
                        ? 'cursor-not-allowed bg-void-elevated text-text-tertiary'
                        : isVoteNow
                          ? 'btn-danger-filled text-white'
                          : 'btn-primary text-white',
            ]"
        >
            <Check v-if="isActivated || voteData.has_voted" class="h-4 w-4" />
            <Swords v-else-if="isVoteNow" class="h-4 w-4" />
            <RefreshCw v-else class="h-4 w-4" />

            <span v-if="isActivated">Activated!</span>
            <span v-else-if="voteData.has_voted">Voted</span>
            <span v-else-if="disabled">{{ disabledLabel ?? 'Eliminated' }}</span>
            <span v-else>{{ isVoteNow ? 'Vote Now' : 'Reroll' }}</span>
        </button>

        <!-- Status -->
        <p class="mt-2 text-center text-xs font-medium" :class="isActivated ? 'text-blue-400' : 'text-text-tertiary'">
            <span v-if="isActivated">Threshold reached! Starting soon...</span>
            <span v-else-if="remainingVotes === 1">1 more vote needed</span>
            <span v-else>{{ remainingVotes }} more votes needed</span>
        </p>
    </div>
</template>
