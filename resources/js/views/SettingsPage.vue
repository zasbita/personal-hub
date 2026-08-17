<template>
  <div class="space-y-10 max-w-6xl mx-auto">
    <div class="space-y-3">
      <h1 class="text-5xl font-semibold text-on-surface tracking-[-0.02em] leading-[1.1]">Settings</h1>
      <p class="text-lg text-on-surface-variant max-w-2xl leading-relaxed">Informasi akun.</p>
    </div>
    <div class="bg-surface-container border border-outline-variant/20 rounded-lg backdrop-blur-md p-6 space-y-4 max-w-md">
      <div class="flex items-center gap-3"><Mail class="w-5 h-5 text-primary shrink-0" /><div><p class="text-sm text-on-surface-variant">Email</p><p class="text-on-surface font-medium">{{ user?.email || '-' }}</p></div></div>
      <div class="flex items-center gap-3"><User class="w-5 h-5 text-primary shrink-0" /><div><p class="text-sm text-on-surface-variant">User ID</p><p class="text-on-surface font-medium font-mono text-sm">{{ user?.id || '-' }}</p></div></div>
      <button @click="handleLogout" class="w-full flex items-center justify-center gap-2 mt-4 px-4 py-3 text-sm font-semibold bg-error-container text-on-error-container rounded-lg hover:bg-error hover:text-on-error transition-all active:scale-95"><LogOut class="w-4 h-4" /> Logout</button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { LogOut, Mail, User } from '@lucide/vue';
const router = useRouter();
const authStore = useAuthStore();
const user = computed(() => authStore.user);
const handleLogout = async () => { await authStore.logout(); router.push('/login'); };
</script>
