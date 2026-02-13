<script setup lang="ts">
import { Sun, Moon } from 'lucide-vue-next';
import { useTheme } from '@/composables/useTheme';

const { currentTheme, toggleTheme } = useTheme();
</script>

<template>
    <button @click="toggleTheme" class="theme-toggle" :title="currentTheme === 'light' ? 'Switch to dark mode' : 'Switch to light mode'">
        <Sun v-if="currentTheme === 'light'" class="h-5 w-5" />
        <Moon v-else class="h-5 w-5" />
    </button>
</template>
