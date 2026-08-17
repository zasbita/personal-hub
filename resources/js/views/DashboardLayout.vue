<template>
  <div class="flex h-screen overflow-hidden">
    <div class="hidden lg:flex flex-shrink-0">
      <Sidebar />
    </div>
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <Navbar />
      <main class="flex-1 overflow-auto p-4 md:p-6 lg:p-8">
        <div class="max-w-6xl mx-auto w-full">
          <router-view />
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import Sidebar from '../components/Sidebar.vue';
import Navbar from '../components/Navbar.vue';
</script>
