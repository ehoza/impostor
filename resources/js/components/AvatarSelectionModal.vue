<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { AVATARS, avatarUrl } from '@/lib/avatars';

const props = defineProps<{
    show: boolean;
    modelValue: string | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    close: [];
}>();

const select = (avatar: string) => {
    emit('update:modelValue', avatar);
    emit('close');
};
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="show"
                class="fixed inset-0 z-[100] flex items-center justify-center p-4"
                @click.self="emit('close')"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>

                <!-- Modal -->
                <div
                    class="glass-card relative max-h-[85vh] w-full max-w-2xl overflow-hidden rounded-2xl shadow-2xl sm:rounded-3xl"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="avatar-modal-title"
                >
                    <div class="flex items-center justify-between border-b border-void-border px-4 py-3 sm:px-6 sm:py-4">
                        <h2 id="avatar-modal-title" class="text-lg font-bold text-text-primary sm:text-xl">Choose your avatar</h2>
                        <button
                            type="button"
                            class="btn-glass rounded-lg p-2 text-text-secondary transition-colors hover:bg-void-hover hover:text-text-primary"
                            aria-label="Close"
                            @click="emit('close')"
                        >
                            <X class="h-5 w-5 sm:h-6 sm:w-6" />
                        </button>
                    </div>

                    <div class="max-h-[60vh] overflow-y-auto p-4 sm:p-6">
                        <div class="grid grid-cols-5 gap-2 sm:grid-cols-8 sm:gap-3 md:grid-cols-10">
                            <button
                                v-for="avatar in AVATARS"
                                :key="avatar"
                                type="button"
                                class="focus:ring-glow-blue aspect-square overflow-hidden rounded-lg border-2 transition-all hover:scale-105 focus:outline-none focus:ring-2 sm:rounded-xl"
                                :class="
                                    props.modelValue === avatar
                                        ? 'border-blue-500 ring-2 ring-blue-500/50'
                                        : 'border-transparent hover:border-void-border'
                                "
                                @click="select(avatar)"
                            >
                                <img
                                    :src="avatarUrl(avatar)"
                                    :alt="avatar"
                                    class="h-full w-full object-cover"
                                />
                            </button>
                        </div>
                    </div>

                    <div class="border-t border-void-border px-4 py-3 sm:px-6 sm:py-4">
                        <p class="text-center text-xs text-text-secondary sm:text-sm">
                            Click an avatar to select it
                            <span v-if="props.modelValue" class="ml-1 text-blue-400">• Selected</span>
                        </p>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
.modal-enter-active .glass-card,
.modal-leave-active .glass-card {
    transition: transform 0.2s ease;
}
.modal-enter-from .glass-card,
.modal-leave-to .glass-card {
    transform: scale(0.95);
}
</style>
