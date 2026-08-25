<template>
  <section>
    <h2 class="text-xl font-bold text-on-surface mb-4 flex items-center gap-2">
      <GraduationCap class="w-5 h-5 text-primary" /> Education
    </h2>
    <div class="grid md:grid-cols-2 gap-3">
      <div v-for="(ed, i) in education" :key="i" class="rounded-2xl border border-outline-variant/20 bg-surface-container p-5">
        <h3 class="font-semibold text-on-surface">{{ ed.school }}</h3>
        <p class="text-sm text-primary mt-1">{{ ed.major }}</p>
        <p class="text-xs text-on-surface-variant/60 mt-2">{{ ed.period }} · {{ ed.location }}</p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { GraduationCap } from '@lucide/vue';
import { education } from '../../data/profile.js';
</script>
