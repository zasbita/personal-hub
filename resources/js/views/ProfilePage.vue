<template>
  <div class="min-h-screen bg-surface">
    <!-- nav -->
    <header class="sticky top-0 z-20 border-b border-outline-variant/20 bg-surface/80 backdrop-blur-xl">
      <div class="mx-auto max-w-5xl px-4 md:px-6 h-14 flex items-center justify-between">
        <router-link to="/profile" class="font-bold text-on-surface tracking-tight">IZ<span class="text-primary">.</span></router-link>
        <nav class="hidden md:flex items-center gap-6 text-sm text-on-surface-variant">
          <a href="#experience" class="hover:text-primary transition-colors">Experience</a>
          <a href="#skills" class="hover:text-primary transition-colors">Skills</a>
          <a href="#contact" class="hover:text-primary transition-colors">Contact</a>
        </nav>
        <div class="flex items-center gap-2">
          <a :href="profile.links.email" class="rounded-full bg-primary px-4 py-1.5 text-xs font-bold text-on-primary hover:bg-primary/90">Hire Me</a>
          <router-link to="/login" class="rounded-full border border-outline-variant/20 px-4 py-1.5 text-xs font-semibold text-on-surface-variant hover:text-on-surface">Dashboard</router-link>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 md:px-6 py-8 space-y-8">
      <ProfileHero />
      <div id="skills"><SkillsGrid /></div>
      <Timeline />
      <Education />
      <div id="contact"><ContactCard /></div>
    </main>

    <footer class="border-t border-outline-variant/20 py-6 text-center text-xs text-on-surface-variant/50">
      © {{ new Date().getFullYear() }} {{ profile.name }} · Built with Vue 3 + Tailwind 4 · Deployed on VPS
    </footer>
  </div>
</template>

<script setup>
import ProfileHero from '../components/profile/ProfileHero.vue';
import SkillsGrid from '../components/profile/SkillsGrid.vue';
import Timeline from '../components/profile/Timeline.vue';
import Education from '../components/profile/Education.vue';
import ContactCard from '../components/profile/ContactCard.vue';
import { profile } from '../data/profile.js';
</script>
