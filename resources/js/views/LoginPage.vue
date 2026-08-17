<template>
  <div class="flex min-h-screen items-center justify-center bg-surface">
    <div class="w-full max-w-md space-y-6">
      <div class="rounded-2xl border border-outline-variant/30 bg-surface-container/80 backdrop-blur-xl p-8 shadow-2xl">
        <h1 class="mb-8 text-center text-3xl font-bold text-primary">Login</h1>
        <form @submit.prevent="handleSubmit" class="space-y-5">
          <div v-if="error" class="rounded-lg bg-error/10 border border-error/30 p-3 text-sm text-error">{{ error }}</div>
          <div>
            <label class="block text-sm font-medium text-on-surface-variant mb-2">Email</label>
            <input v-model="email" type="email" required :disabled="loading"
              class="w-full rounded-lg border border-outline-variant/30 bg-surface px-4 py-3 text-on-surface placeholder-on-surface-variant/50 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30" placeholder="your@email.com" />
          </div>
          <div>
            <label class="block text-sm font-medium text-on-surface-variant mb-2">Password</label>
            <div class="relative">
              <input v-model="password" :type="showPassword ? 'text' : 'password'" required :disabled="loading"
                class="w-full rounded-lg border border-outline-variant/30 bg-surface px-4 py-3 pr-11 text-on-surface placeholder-on-surface-variant/50 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30" placeholder="••••••••" />
              <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary" tabindex="-1">
                <EyeOff v-if="showPassword" class="w-5 h-5" /><Eye v-else class="w-5 h-5" />
              </button>
            </div>
          </div>
          <button type="submit" :disabled="loading"
            class="w-full rounded-lg bg-primary py-3 font-bold text-on-primary hover:bg-primary/90 disabled:opacity-50 active:scale-95 transition-all">
            {{ loading ? 'Signing in...' : 'Sign In' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { Eye, EyeOff } from '@lucide/vue';
const router = useRouter();
const authStore = useAuthStore();
const email = ref('');
const password = ref('');
const showPassword = ref(false);
const loading = ref(false);
const error = ref('');
const handleSubmit = async () => {
  error.value = '';
  loading.value = true;
  try { await authStore.login(email.value, password.value); router.push('/'); }
  catch (e) { error.value = e.response?.data?.error || 'Login failed'; }
  finally { loading.value = false; }
};
</script>
