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
    <!-- Charts: 0-dep, CSS bars — ponytail: chart.js if tooltip/zoom needed -->
    <section v-if="stats?.byCategory && Object.keys(stats.byCategory).length" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-surface-container border border-outline-variant/20 rounded-lg p-6 space-y-4">
        <h3 class="text-lg font-semibold text-on-surface">Per Kategori (bulan ini)</h3>
        <div class="space-y-3">
          <div v-for="(val, cat) in stats.byCategory" :key="cat" class="space-y-1">
            <div class="flex justify-between text-sm">
              <span class="text-on-surface-variant">{{ cat }}</span>
              <span class="font-medium" :class="(stats.budgets?.[cat] && val > stats.budgets[cat]) ? 'text-error' : 'text-on-surface'">
                Rp {{ Number(val).toLocaleString('id-ID') }}<span v-if="stats.budgets?.[cat]" class="text-on-surface-variant font-normal"> / Rp {{ Number(stats.budgets[cat]).toLocaleString('id-ID') }}</span>
                <span v-if="stats.budgets?.[cat]" class="ml-1 text-xs">{{ Math.round(val / stats.budgets[cat] * 100) }}%</span>
              </span>
            </div>
            <div class="h-2 rounded-full bg-surface-container-highest overflow-hidden">
              <div class="h-full rounded-full transition-all" :class="(stats.budgets?.[cat] && val > stats.budgets[cat]) ? 'bg-error' : (stats.budgets?.[cat] && val / stats.budgets[cat] >= 0.8 ? 'bg-amber-400' : 'bg-primary')" :style="{ width: Math.min(val / (stats.budgets?.[cat] || maxCategory) * 100, 100) + '%' }"></div>
            </div>
          </div>
        </div>
      </div>
      <div class="bg-surface-container border border-outline-variant/20 rounded-lg p-6 space-y-4">
        <h3 class="text-lg font-semibold text-on-surface">Tren Harian</h3>
        <div v-if="!stats.daily?.length" class="text-sm text-on-surface-variant">Belum ada data harian</div>
        <div v-else class="flex items-end gap-1 h-32">
          <div v-for="d in stats.daily" :key="d.date" class="flex-1 flex flex-col items-center gap-1">
            <div class="w-full bg-primary/80 hover:bg-primary rounded-t transition-all" :style="{ height: (d.total / maxDaily * 96) + 'px', minHeight: d.total ? '4px' : '0' }" :title="`${d.date}: Rp ${Number(d.total).toLocaleString('id-ID')}`"></div>
            <span class="text-[10px] text-on-surface-variant hidden sm:block">{{ d.date.slice(5) }}</span>
          </div>
        </div>
        <p class="text-xs text-on-surface-variant">Hover bar untuk detail nominal</p>
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
import { ref, computed, onMounted } from 'vue';
import StatCard from '../components/StatCard.vue';
import { expenses as expApi, vehicle as vehApi, matches as matchApi } from '../api/client.js';
import { TrendingUp, Car, Zap } from '@lucide/vue';
const stats = ref(null);
const vehicle = ref(null);
const matchCount = ref(0);
const recentExpenses = ref([]);
const loading = ref(true);
const loadError = ref(false);
const maxCategory = computed(() => Math.max(...Object.values(stats.value?.byCategory || {0:1})));
const maxDaily = computed(() => Math.max(...(stats.value?.daily || []).map(d=>d.total), 1));
const notifyBudget = (budgets, byCat) => {
  if (!budgets || !byCat || localStorage.getItem('ph_notify') === 'off') return;
  for (const [cat, spent] of Object.entries(byCat)) {
    const limit = budgets[cat];
    if (!limit) continue;
    const pct = spent / limit;
    const key = `ph_notified_${cat}_${new Date().toISOString().slice(0,7)}_${pct>=1?'100':pct>=0.8?'80':''}`;
    if ((pct >= 1 || pct >= 0.8) && !localStorage.getItem(key)) {
      const msg = pct >= 1 ? `🚨 Budget ${cat} lewat 100% (Rp ${Number(spent).toLocaleString('id-ID')} / ${Number(limit).toLocaleString('id-ID')})` : `⚠️ Budget ${cat} 80% (Rp ${Number(spent).toLocaleString('id-ID')} / ${Number(limit).toLocaleString('id-ID')})`;
      if ('Notification' in window) {
        if (Notification.permission === 'granted') new Notification(msg);
        else if (Notification.permission !== 'denied') Notification.requestPermission().then((p) => { if (p === 'granted') new Notification(msg); });
      }
      localStorage.setItem(key, '1');
    }
  }
};
onMounted(async () => {
  try {
    const [s, v, m, e] = await Promise.all([expApi.stats(), vehApi.get(), matchApi.list(), expApi.list(10)]);
    stats.value = s; vehicle.value = v; matchCount.value = m?.length || 0;
    recentExpenses.value = (e || []).sort((a, b) => new Date(b.date) - new Date(a.date));
    notifyBudget(s?.budgets, s?.byCategory);
  } catch (e) { loadError.value = true; } finally { loading.value = false; }
});
</script>
