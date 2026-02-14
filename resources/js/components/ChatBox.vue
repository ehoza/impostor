<script setup lang="ts">
import axios from 'axios';
import { MessageSquare, Send, Lock, Bell, Users, ChevronDown } from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { avatarUrl } from '@/lib/avatars';

interface Message {
    id: number;
    content: string;
    sender_id: number;
    sender_name: string;
    recipient_id: number | null;
    is_dm: boolean;
    is_read: boolean;
    created_at: string;
}

interface Player {
    id: number;
    name: string;
    is_eliminated: boolean;
    avatar: string | null;
}

const props = defineProps<{
    code: string;
    currentPlayerId: number;
    players: Player[];
}>();

const messages = ref<Message[]>([]);
const newMessage = ref('');
const selectedRecipient = ref<number | null>(null);
const chatContainer = ref<HTMLElement | null>(null);
const isLoading = ref(false);
const unreadCount = ref(0);
const showDMNotification = ref(false);
const dmNotificationSender = ref('');

let pollingInterval: number | null = null;

const publicMessages = computed(() => {
    return messages.value.filter((m) => !m.is_dm);
});

const dmMessages = computed(() => {
    return messages.value.filter((m) => m.is_dm && (m.sender_id === props.currentPlayerId || m.recipient_id === props.currentPlayerId));
});

const activePlayers = computed(() => {
    return props.players.filter((p) => !p.is_eliminated && p.id !== props.currentPlayerId);
});

const scrollToBottom = async () => {
    await nextTick();
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
};

const fetchMessages = async () => {
    try {
        const response = await axios.get(`/game/${props.code}/messages`);
        const newMessages = response.data.messages;

        const previousDMs = messages.value.filter((m) => m.is_dm && m.recipient_id === props.currentPlayerId);
        const newDMs = newMessages.filter(
            (m: Message) => m.is_dm && m.recipient_id === props.currentPlayerId && !previousDMs.some((pm) => pm.id === m.id),
        );

        if (newDMs.length > 0) {
            showDMNotification.value = true;
            dmNotificationSender.value = newDMs[newDMs.length - 1].sender_name;
            setTimeout(() => {
                showDMNotification.value = false;
            }, 3000);
        }

        messages.value = newMessages;

        const currentUnread = newMessages.filter((m: Message) => m.is_dm && m.recipient_id === props.currentPlayerId && !m.is_read).length;
        unreadCount.value = currentUnread;
    } catch (error) {
        console.error('Failed to fetch messages:', error);
    }
};

const sendMessage = async () => {
    if (!newMessage.value.trim()) return;

    isLoading.value = true;
    try {
        await axios.post(`/game/${props.code}/message`, {
            content: newMessage.value.trim(),
            recipient_id: selectedRecipient.value,
        });

        newMessage.value = '';
        await fetchMessages();
        await scrollToBottom();
    } catch (error) {
        console.error('Failed to send message:', error);
    } finally {
        isLoading.value = false;
    }
};

const formatTime = (timestamp: string) => {
    const date = new Date(timestamp);
    return date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
};

const isOwnMessage = (message: Message) => {
    return message.sender_id === props.currentPlayerId;
};

const getPlayerName = (playerId: number) => {
    const player = props.players.find((p) => p.id === playerId);
    return player?.name || 'Unknown';
};

const getPlayerAvatar = (playerId: number) => {
    const player = props.players.find((p) => p.id === playerId);
    return player?.avatar || null;
};

onMounted(() => {
    fetchMessages();
    pollingInterval = window.setInterval(fetchMessages, 2000);
});

onUnmounted(() => {
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
});
</script>

<template>
    <div class="glass-card flex h-full flex-col overflow-hidden rounded-2xl">
        <!-- Header -->
        <div class="flex-shrink-0 border-b border-void-border px-3 py-2 sm:px-4 sm:py-3">
            <div class="mb-2 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-500/20 sm:h-8 sm:w-8">
                        <MessageSquare class="h-3.5 w-3.5 text-blue-400 sm:h-4 sm:w-4" />
                    </div>
                    <h3 class="text-sm font-bold text-text-primary sm:text-base">Chat</h3>
                    <span v-if="unreadCount > 0" class="animate-bounce-in rounded-full bg-red-500 px-2 py-0.5 text-xs font-bold text-white">
                        {{ unreadCount }}
                    </span>
                </div>
            </div>

            <!-- DM Selector -->
            <div class="flex items-center gap-2">
                <span class="text-xs text-text-secondary">To:</span>
                <div class="relative flex-1">
                    <select v-model="selectedRecipient" class="input-field w-full appearance-none rounded-lg px-3 py-1.5 text-xs text-text-primary">
                        <option :value="null">
                            <span class="flex items-center gap-1">
                                <Users class="h-3 w-3" />
                                Everyone
                            </span>
                        </option>
                        <option v-for="player in activePlayers" :key="player.id" :value="player.id">
                            {{ player.name }}
                        </option>
                    </select>
                    <ChevronDown class="pointer-events-none absolute top-1/2 right-2 h-3 w-3 -translate-y-1/2 text-text-secondary" />
                </div>
            </div>
        </div>

        <!-- DM Notification -->
        <Transition name="slide-down">
            <div v-if="showDMNotification" class="flex items-center gap-2 bg-blue-600/90 px-3 py-2 text-xs text-white">
                <Bell class="h-3 w-3" />
                New DM from {{ dmNotificationSender }}
            </div>
        </Transition>

        <!-- Messages -->
        <div ref="chatContainer" class="mobile-chat-scroll min-h-0 flex-1 space-y-2 overflow-y-auto p-2 sm:space-y-3 sm:p-3">
            <div v-for="message in publicMessages" :key="message.id" :class="['flex gap-2', isOwnMessage(message) ? 'flex-row-reverse' : 'flex-row']">
                <!-- Avatar -->
                <div class="flex-shrink-0">
                    <div class="flex h-6 w-6 items-center justify-center overflow-hidden rounded-full bg-void-hover sm:h-8 sm:w-8">
                        <img
                            v-if="getPlayerAvatar(message.sender_id)"
                            :src="avatarUrl(getPlayerAvatar(message.sender_id)!)"
                            :alt="message.sender_name"
                            class="h-full w-full object-cover"
                        />
                        <span v-else class="text-[9px] font-bold text-text-secondary sm:text-[10px]">{{ message.sender_name.charAt(0).toUpperCase() }}</span>
                    </div>
                </div>
                <div
                    :class="[
                        'max-w-[80%] rounded-xl px-2.5 py-1.5 text-xs sm:max-w-[75%] sm:rounded-xl sm:px-3 sm:py-2 sm:text-sm',
                        isOwnMessage(message) ? 'rounded-br-none bg-blue-600 text-white' : 'glass-light rounded-bl-none text-text-primary',
                    ]"
                >
                    <div class="mb-0.5 flex items-center gap-1">
                        <span class="text-[10px] font-medium opacity-70 sm:text-xs">{{ message.sender_name }}</span>
                    </div>
                    <p class="break-words">{{ message.content }}</p>
                    <span class="mt-0.5 block text-[9px] opacity-50 sm:text-xs">{{ formatTime(message.created_at) }}</span>
                </div>
            </div>

            <!-- DM Section -->
            <div v-if="dmMessages.length > 0" class="mt-2 border-t border-blue-500/30 pt-2 sm:mt-3 sm:pt-3">
                <div class="mb-2 flex items-center gap-1 text-[10px] text-blue-400 sm:text-xs">
                    <Lock class="h-3 w-3" />
                    Private Messages
                </div>
                <div
                    v-for="message in dmMessages"
                    :key="message.id"
                    :class="['mt-1 flex gap-2 sm:mt-2', isOwnMessage(message) ? 'flex-row-reverse' : 'flex-row']"
                >
                    <!-- Avatar -->
                    <div class="flex-shrink-0">
                        <div class="flex h-6 w-6 items-center justify-center overflow-hidden rounded-full bg-void-hover sm:h-8 sm:w-8">
                            <img
                                v-if="getPlayerAvatar(message.sender_id)"
                                :src="avatarUrl(getPlayerAvatar(message.sender_id)!)"
                                :alt="message.sender_name"
                                class="h-full w-full object-cover"
                            />
                            <span v-else class="text-[9px] font-bold text-text-secondary sm:text-[10px]">{{ message.sender_name.charAt(0).toUpperCase() }}</span>
                        </div>
                    </div>
                    <div
                        :class="[
                            'max-w-[80%] rounded-xl px-2.5 py-1.5 text-xs sm:max-w-[75%] sm:rounded-xl sm:px-3 sm:py-2 sm:text-sm',
                            isOwnMessage(message)
                                ? 'rounded-br-none bg-blue-600 text-white'
                                : 'rounded-bl-none border border-blue-500/30 bg-blue-900/30 text-blue-100',
                        ]"
                    >
                        <div class="mb-0.5 flex flex-wrap items-center gap-1">
                            <span class="text-[10px] opacity-70 sm:text-xs">{{ message.sender_name }}</span>
                            <span class="text-[9px] opacity-50 sm:text-xs">→ {{ getPlayerName(message.recipient_id!) }}</span>
                        </div>
                        <p class="break-words">{{ message.content }}</p>
                        <span class="mt-0.5 block text-[9px] opacity-50 sm:text-xs">{{ formatTime(message.created_at) }}</span>
                    </div>
                </div>
            </div>

            <div
                v-if="publicMessages.length === 0 && dmMessages.length === 0"
                class="flex h-32 flex-col items-center justify-center text-text-tertiary"
            >
                <MessageSquare class="mb-2 h-8 w-8 opacity-30" />
                <p class="text-xs">No messages yet</p>
                <p class="text-xs opacity-70">Start the conversation!</p>
            </div>
        </div>

        <!-- Input -->
        <div class="flex-shrink-0 border-t border-void-border p-2 sm:p-3">
            <form @submit.prevent="sendMessage" class="flex gap-2">
                <input
                    v-model="newMessage"
                    type="text"
                    :placeholder="selectedRecipient ? 'Private message...' : 'Type a message...'"
                    maxlength="500"
                    :disabled="isLoading"
                    class="input-field flex-1 rounded-xl px-3 py-2 text-xs text-text-primary placeholder-text-tertiary sm:text-sm"
                />
                <button
                    type="submit"
                    :disabled="!newMessage.trim() || isLoading"
                    class="btn-primary flex flex-shrink-0 items-center justify-center rounded-xl px-3 py-2 text-white disabled:opacity-50 sm:px-4"
                >
                    <Send v-if="!isLoading" class="h-4 w-4" />
                    <div v-else class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></div>
                </button>
            </form>
        </div>
    </div>
</template>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.2s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

/* Mobile-optimized scrolling */
.mobile-chat-scroll {
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-y: contain;
}

/* Adjust scrollbar for mobile */
.mobile-chat-scroll::-webkit-scrollbar {
    width: 4px;
}

.mobile-chat-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.mobile-chat-scroll::-webkit-scrollbar-thumb {
    background: rgba(42, 42, 53, 0.6);
    border-radius: 2px;
}
</style>
