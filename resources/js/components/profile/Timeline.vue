<template>
  <section id="experience">
    <h2 class="text-xl font-bold text-on-surface mb-6 flex items-center gap-2">
      <Briefcase class="w-5 h-5 text-primary" /> Experience
    </h2>
    <div class="relative">
      <div class="absolute left-3 top-2 bottom-2 w-px bg-outline-variant/30 hidden md:block" />
      <div class="space-y-4">
        <div
          v-for="(e, i) in experiences"
          :key="i"
          class="relative flex gap-4 rounded-2xl border border-outline-variant/20 bg-surface-container p-5 md:pl-10 hover:border-primary/20 transition-colors"
        >
          <div class="hidden md:flex absolute left-0 top-6 h-6 w-6 -translate-x-1/2 items-center justify-center rounded-full border-2 border-primary bg-surface">
            <span class="h-2 w-2 rounded-full bg-primary" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <h3 class="font-semibold text-on-surface leading-tight">{{ e.project }}</h3>
              <span class="shrink-0 rounded-full bg-surface-variant px-2.5 py-1 text-xs font-medium text-on-surface-variant">{{ e.period }}</span>
            </div>
            <p class="mt-1 text-sm text-primary font-medium">{{ e.role }} — {{ e.company }}</p>
            <p class="mt-1 text-xs text-on-surface-variant/70 flex items-center gap-1"><MapPin class="w-3 h-3" /> {{ e.location }}</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { Briefcase, MapPin } from '@lucide/vue';
import { experiences } from '../../data/profile.js';
</script>
