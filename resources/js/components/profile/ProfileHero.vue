<template>
  <section class="relative overflow-hidden rounded-3xl border border-outline-variant/20 bg-gradient-to-br from-surface-container-low to-surface-container p-8 md:p-12">
    <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-primary/10 blur-3xl" />
    <div class="absolute -left-20 -bottom-20 h-64 w-64 rounded-full bg-primary/5 blur-3xl" />
    <div class="relative">
      <p class="mb-3 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-semibold tracking-widest text-primary uppercase">
        <span class="h-2 w-2 rounded-full bg-primary animate-pulse" /> Available for new projects
      </p>
      <h1 class="text-4xl md:text-5xl font-bold tracking-tight text-on-surface">
        {{ profile.name }}
      </h1>
      <p class="mt-3 text-lg text-primary font-semibold">{{ profile.title }} — {{ profile.company }}</p>
      <p class="mt-1 text-sm text-on-surface-variant flex items-center gap-1.5">
        <MapPin class="w-4 h-4" /> {{ profile.location }}
      </p>
      <p class="mt-6 max-w-2xl text-[15px] leading-7 text-on-surface-variant">
        {{ profile.summary }}
      </p>
      <div class="mt-8 flex flex-wrap gap-3">
        <a :href="profile.links.email" class="inline-flex items-center gap-2 rounded-full bg-primary px-6 py-3 text-sm font-bold text-on-primary hover:bg-primary/90 transition-colors">
          <Mail class="w-4 h-4" /> Contact Me
        </a>
        <a :href="profile.links.whatsapp" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full border border-outline-variant/30 bg-surface px-6 py-3 text-sm font-semibold text-on-surface hover:border-primary/30 hover:text-primary transition-colors">
          <Phone class="w-4 h-4" /> WhatsApp
        </a>
        <a href="#experience" class="inline-flex items-center gap-2 rounded-full border border-outline-variant/20 px-6 py-3 text-sm font-semibold text-on-surface-variant hover:text-on-surface transition-colors">
          View Experience <ArrowDown class="w-4 h-4" />
        </a>
      </div>
    </div>
  </section>
</template>

<script setup>
import { MapPin, Mail, Phone, ArrowDown } from '@lucide/vue';
import { profile } from '../../data/profile.js';
</script>
