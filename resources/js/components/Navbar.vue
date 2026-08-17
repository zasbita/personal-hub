<template>
  <nav class="bg-surface-container/50 border-b border-outline-variant/10 px-6 py-4 backdrop-blur-xl flex items-center justify-between sticky top-0 z-40">
    <div class="flex items-center gap-3">
      <h1 class="text-2xl font-semibold text-on-surface tracking-tight">Personal Hub</h1>
      <span class="text-xs text-on-surface-variant font-mono opacity-60">Dashboard</span>
    </div>
    <div class="flex items-center gap-3">
      <button @click="$router.go(0)" class="px-4 py-2 text-sm font-medium text-on-surface-variant hover:text-on-surface hover:bg-surface-container rounded transition-all">Refresh</button>
      <button @click="handleLogout" class="flex items-center gap-2 px-4 py-2 text-sm font-semibold bg-primary-container text-on-primary-container rounded hover:bg-primary hover:text-on-primary transition-all">
        <LogOut class="w-4 h-4" /> Logout
      </button>
    </div>
  </nav>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { LogOut } from '@lucide/vue';
const router = useRouter();
const authStore = useAuthStore();
const handleLogout = async () => { await authStore.logout(); router.push('/login'); };
</script>
