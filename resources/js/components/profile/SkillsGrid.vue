<template>
  <section>
    <h2 class="text-xl font-bold text-on-surface mb-4 flex items-center gap-2">
      <Wrench class="w-5 h-5 text-primary" /> Skills
    </h2>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
      <div
        v-for="s in skills"
        :key="s.name"
        class="rounded-2xl border border-outline-variant/20 bg-surface-container px-4 py-3 flex items-center justify-between"
      >
        <span class="text-sm font-medium text-on-surface">{{ s.name }}</span>
        <span
          class="text-[11px] font-bold tracking-widest uppercase rounded-full px-2 py-0.5"
          :class="s.level === 'Expert' ? 'bg-primary/15 text-primary' : 'bg-surface-variant text-on-surface-variant'"
        >{{ s.level }}</span>
      </div>
    </div>
  </section>
</template>

<script setup>
import { Wrench } from '@lucide/vue';
import { skills } from '../../data/profile.js';
</script>
