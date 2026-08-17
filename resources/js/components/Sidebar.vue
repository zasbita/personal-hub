<template>
  <aside class="w-64 bg-surface-container-low border-r border-outline-variant/10 backdrop-blur-sm flex flex-col">
    <div class="p-6 border-b border-outline-variant/10">
      <h2 class="text-xl font-semibold text-primary tracking-tight">PH</h2>
    </div>
    <nav class="space-y-2 px-4 py-4 flex-1">
      <router-link
        v-for="item in navItems" :key="item.href" :to="item.href"
        :class="['flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all duration-300 group relative overflow-hidden',
          isActive(item.href) ? 'bg-primary-container text-on-primary-container shadow-md shadow-primary/20' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container/50']">
        <div class="absolute left-0 top-0 bottom-0 w-1 bg-primary rounded-r transition-all duration-300"
             :class="isActive(item.href) ? 'opacity-100' : 'opacity-0'" />
        <component :is="item.icon" class="w-5 h-5" :class="isActive(item.href) ? 'text-on-primary-container' : 'text-on-surface-variant group-hover:text-primary'" />
        <span :class="isActive(item.href) ? 'font-semibold' : ''">{{ item.label }}</span>
      </router-link>
    </nav>
  </aside>
</template>

<script setup>
import { useRoute } from 'vue-router';
import { BarChart3, Zap, Car, Target, Settings } from '@lucide/vue';
const route = useRoute();
const navItems = [
  { href: '/', icon: BarChart3, label: 'Overview' },
  { href: '/expenses', icon: Zap, label: 'Expenses' },
  { href: '/vehicle', icon: Car, label: 'Vehicle' },
  { href: '/sports', icon: Target, label: 'Sports' },
  { href: '/settings', icon: Settings, label: 'Settings' },
];
const isActive = (href) => route.path === href;
</script>
