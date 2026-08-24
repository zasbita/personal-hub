<template>
  <div class="space-y-20 max-w-6xl mx-auto">
    <div class="space-y-3">
      <h1 class="text-5xl font-semibold text-on-surface tracking-[-0.02em] leading-[1.1]">Dashboard</h1>
      <p class="text-lg text-on-surface-variant max-w-2xl leading-relaxed">Welcome back! Here's your financial and vehicle status at a glance.</p>
    </div>
    <div v-if="loadError" class="p-4 rounded border border-error/40 text-error text-center">Gagal memuat data. Coba muat ulang halaman.</div>
    <section class="space-y-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
        <StatCard title="Total Expenses (Month)" :value="`Rp ${(stats?.total || 0).toLocaleString('id-ID')}`">
          <template #icon><TrendingUp class="w-6 h-6 md:w-8 md:h-8" /></template>
        </StatCard>
        <StatCard title="Vehicle Status" :value="`${vehicle?.remaining_km || 0} KM`" :subtitle="vehicle?.status || 'No data'">
          <template #icon><Car class="w-6 h-6 md:w-8 md:h-8" /></template>
        </StatCard>
        <StatCard title="Upcoming Matches" :value="String(matchCount)">
          <template #icon><Zap class="w-6 h-6 md:w-8 md:h-8" /></template>
        </StatCard>
      </div>
    </section>
    <section class="space-y-6">
      <div><h2 class="text-2xl md:text-3xl font-semibold text-on-surface tracking-[-0.02em]">Recent Expenses</h2>
        <p class="text-on-surface-variant mt-1 text-sm md:text-base">Your latest transactions</p></div>
      <div class="bg-surface-container border border-outline-variant/20 rounded-lg backdrop-blur-md">
        <div v-if="loading" class="p-6 text-center text-on-surface-variant">Loading...</div>
        <div v-else-if="recentExpenses.length === 0" class="p-6 text-center text-on-surface-variant">No expenses yet</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full">
            <thead><tr class="border-b border-outline-variant/10">
              <th class="text-left px-6 py-3 text-sm font-semibold text-on-surface">Date</th>
              <th class="text-left px-6 py-3 text-sm font-semibold text-on-surface">Description</th>
              <th class="text-left px-6 py-3 text-sm font-semibold text-on-surface">Category</th>
              <th class="text-right px-6 py-3 text-sm font-semibold text-on-surface">Amount</th>
            </tr></thead>
            <tbody><tr v-for="exp in recentExpenses" :key="exp.id" class="border-b border-outline-variant/10 hover:bg-surface-container-high transition-all">
              <td class="px-6 py-4 text-sm text-on-surface-variant whitespace-nowrap">{{ new Date(exp.date).toLocaleDateString('id-ID') }}</td>
              <td class="px-6 py-4 text-sm text-on-surface font-medium">{{ exp.description }}</td>
              <td class="px-6 py-4 text-sm text-on-surface-variant">{{ exp.category || '-' }}</td>
              <td class="px-6 py-4 text-sm text-right text-surface-tint font-semibold">Rp {{ Number(exp.amount).toLocaleString('id-ID') }}</td>
            </tr></tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import StatCard from '../components/StatCard.vue';
import { expenses as expApi, vehicle as vehApi, matches as matchApi } from '../api/client.js';
import { TrendingUp, Car, Zap } from '@lucide/vue';
const stats = ref(null);
const vehicle = ref(null);
const matchCount = ref(0);
const recentExpenses = ref([]);
const loading = ref(true);
const loadError = ref(false);
onMounted(async () => {
  try {
    const [s, v, m, e] = await Promise.all([expApi.stats(), vehApi.get(), matchApi.list(), expApi.list(10)]);
    stats.value = s; vehicle.value = v; matchCount.value = m?.length || 0;
    recentExpenses.value = (e || []).sort((a, b) => new Date(b.date) - new Date(a.date));
  } catch (e) { loadError.value = true; } finally { loading.value = false; }
});
</script>
