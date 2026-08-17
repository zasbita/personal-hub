<template>
  <div class="bg-surface-container border border-outline-variant/20 rounded-lg backdrop-blur-md p-6 transition-all duration-300 hover:shadow-lg hover:shadow-primary/5">
    <div class="flex items-start justify-between">
      <div class="space-y-2">
        <p class="text-sm font-medium text-on-surface-variant">{{ title }}</p>
        <p class="text-3xl font-semibold text-on-surface tracking-tight">{{ value }}</p>
        <p v-if="subtitle" class="text-sm text-on-surface-variant">{{ subtitle }}</p>
      </div>
      <div class="p-3 rounded-lg bg-primary/10 text-primary"><slot name="icon" /></div>
    </div>
  </div>
</template>

<script setup>
defineProps({ title: String, value: String, subtitle: String });
</script>
