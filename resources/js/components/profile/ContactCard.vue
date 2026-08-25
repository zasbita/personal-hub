<template>
  <section class="rounded-3xl border border-primary/20 bg-primary/5 p-8 text-center">
    <h2 class="text-2xl font-bold text-on-surface">Let's build something</h2>
    <p class="mt-2 text-sm text-on-surface-variant max-w-lg mx-auto">Open for freelance, full-time, and collaboration. Reach me via email or WhatsApp — fastest response within 24h.</p>
    <div class="mt-6 flex flex-wrap justify-center gap-3">
      <a :href="profile.links.email" class="inline-flex items-center gap-2 rounded-full bg-primary px-6 py-3 text-sm font-bold text-on-primary hover:bg-primary/90">
        <Mail class="w-4 h-4" /> {{ profile.email }}
      </a>
      <a :href="profile.links.phone" class="inline-flex items-center gap-2 rounded-full border border-outline-variant/30 bg-surface px-6 py-3 text-sm font-semibold text-on-surface">
        <Phone class="w-4 h-4" /> {{ profile.phone }}
      </a>
    </div>
    <p class="mt-4 text-xs text-on-surface-variant/50">{{ profile.location }}</p>
  </section>
</template>

<script setup>
import { Mail, Phone } from '@lucide/vue';
import { profile } from '../../data/profile.js';
</script>
