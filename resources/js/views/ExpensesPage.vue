<template>
  <div class="space-y-10 max-w-6xl mx-auto">
    <div class="flex items-start justify-between gap-4">
      <div class="space-y-3">
        <h1 class="text-5xl font-semibold text-on-surface tracking-[-0.02em] leading-[1.1]">Expenses</h1>
        <p class="text-lg text-on-surface-variant max-w-2xl leading-relaxed">Riwayat pengeluaran bulan ini.</p>
      </div>
      <button @click="handleExport" class="flex items-center gap-2 px-4 py-2 text-sm font-semibold bg-primary-container text-on-primary-container rounded hover:bg-primary hover:text-on-primary transition-all active:scale-95">
        <Download class="w-4 h-4" /> Export CSV
      </button>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 md:gap-6">
      <StatCard title="Total Bulan Ini" :value="`Rp ${(stats?.total || 0).toLocaleString('id-ID')}`"><template #icon><TrendingUp class="w-6 h-6 md:w-8 md:h-8" /></template></StatCard>
      <StatCard title="Jumlah Transaksi" :value="String(stats?.count || 0)"><template #icon><Receipt class="w-6 h-6 md:w-8 md:h-8" /></template></StatCard>
    </div>
    <!-- Filters: client-side, ponytail: no BE pagination until >5k rows -->
    <div class="flex flex-col sm:flex-row gap-3">
      <div class="relative flex-1">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-on-surface-variant" />
        <input v-model="searchQuery" placeholder="Cari deskripsi / kategori..." class="w-full rounded-lg border border-outline-variant/30 bg-surface-container pl-9 pr-4 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant focus:border-primary focus:outline-none" />
      </div>
      <select v-model="selectedCategory" class="rounded-lg border border-outline-variant/30 bg-surface-container px-3 py-2.5 text-sm text-on-surface focus:border-primary focus:outline-none">
        <option value="">Semua Kategori</option>
        <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
      </select>
      <input v-model="dateFrom" type="date" class="rounded-lg border border-outline-variant/30 bg-surface-container px-3 py-2.5 text-sm text-on-surface focus:border-primary focus:outline-none" />
      <input v-model="dateTo" type="date" class="rounded-lg border border-outline-variant/30 bg-surface-container px-3 py-2.5 text-sm text-on-surface focus:border-primary focus:outline-none" />
      <button v-if="hasFilter" @click="clearFilters" class="px-3 py-2.5 text-sm font-medium text-on-surface-variant hover:text-on-surface">Reset</button>
    </div>
    <p v-if="!loading && !loadError" class="text-sm text-on-surface-variant">
      Menampilkan {{ filteredExpenses.length }} dari {{ expenses.length }} transaksi
      <span v-if="hasFilter">· Total terfilter: Rp {{ filteredTotal.toLocaleString('id-ID') }}</span>
    </p>
    <div class="bg-surface-container border border-outline-variant/20 rounded-lg backdrop-blur-md">
      <div v-if="loading" class="p-6 text-center text-on-surface-variant">Loading...</div>
      <div v-else-if="loadError" class="p-6 text-center text-error">Gagal memuat pengeluaran. Coba muat ulang halaman.</div>
      <div v-else-if="expenses.length === 0" class="p-6 text-center text-on-surface-variant">Belum ada data pengeluaran</div>
      <div v-else-if="filteredExpenses.length === 0" class="p-6 text-center text-on-surface-variant">Tidak ada hasil untuk filter ini</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full">
          <thead><tr class="border-b border-outline-variant/10">
            <th class="text-left px-6 py-3 text-sm font-semibold text-on-surface">Tanggal</th>
            <th class="text-left px-6 py-3 text-sm font-semibold text-on-surface">Deskripsi</th>
            <th class="text-left px-6 py-3 text-sm font-semibold text-on-surface">Kategori</th>
            <th class="text-right px-6 py-3 text-sm font-semibold text-on-surface">Jumlah</th>
            <th class="text-right px-6 py-3 text-sm font-semibold text-on-surface">Aksi</th>
          </tr></thead>
          <tbody><tr v-for="exp in filteredExpenses" :key="exp.id" class="border-b border-outline-variant/10 hover:bg-surface-container-high transition-all group">
            <td class="px-6 py-4 text-sm text-on-surface-variant whitespace-nowrap">{{ new Date(exp.date).toLocaleDateString('id-ID') }}</td>
            <td class="px-6 py-4 text-sm text-on-surface font-medium">
              <input v-if="editingId === exp.id" v-model="draft.description" class="w-full rounded border border-outline-variant/30 bg-surface px-2 py-1 text-on-surface focus:border-primary focus:outline-none" />
              <span v-else>{{ exp.description }}</span>
            </td>
            <td class="px-6 py-4 text-sm text-on-surface-variant">
              <input v-if="editingId === exp.id" v-model="draft.category" class="w-full rounded border border-outline-variant/30 bg-surface px-2 py-1 text-on-surface focus:border-primary focus:outline-none" />
              <span v-else>{{ exp.category || '-' }}</span>
            </td>
            <td class="px-6 py-4 text-sm text-right text-surface-tint font-semibold">
              <input v-if="editingId === exp.id" v-model="draft.amount" type="number" class="w-28 rounded border border-outline-variant/30 bg-surface px-2 py-1 text-right text-on-surface focus:border-primary focus:outline-none" />
              <span v-else>Rp {{ Number(exp.amount).toLocaleString('id-ID') }}</span>
            </td>
            <td class="px-6 py-4 text-right"><div class="flex items-center justify-end gap-2">
              <template v-if="editingId === exp.id">
                <button @click="saveEdit(exp.id)" :disabled="busyId === exp.id" class="p-1.5 rounded text-primary hover:bg-primary/10 disabled:opacity-50"><Check class="w-4 h-4" /></button>
                <button @click="editingId = null" :disabled="busyId === exp.id" class="p-1.5 rounded text-on-surface-variant hover:bg-surface-container-highest disabled:opacity-50"><X class="w-4 h-4" /></button>
              </template>
              <template v-else>
                <button @click="startEdit(exp)" class="p-1.5 rounded text-on-surface-variant hover:text-primary hover:bg-primary/10"><Pencil class="w-4 h-4" /></button>
                <button @click="handleDelete(exp.id)" :disabled="busyId === exp.id" class="p-1.5 rounded text-on-surface-variant hover:text-tertiary hover:bg-tertiary/10 disabled:opacity-50"><Trash2 class="w-4 h-4" /></button>
              </template>
            </div></td>
          </tr></tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import StatCard from '../components/StatCard.vue';
import { expenses as expApi } from '../api/client.js';
import { TrendingUp, Receipt, Pencil, Trash2, Check, X, Search, Download } from '@lucide/vue';
const expenses = ref([]);
const stats = ref(null);
const loading = ref(true);
const editingId = ref(null);
const draft = ref({ description: '', amount: '', category: '' });
const busyId = ref(null);
const loadError = ref(false);
const searchQuery = ref('');
const selectedCategory = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const categories = computed(() => [...new Set(expenses.value.map(e => e.category).filter(Boolean))].sort());
const hasFilter = computed(() => searchQuery.value || selectedCategory.value || dateFrom.value || dateTo.value);
const filteredExpenses = computed(() => {
  const q = searchQuery.value.toLowerCase().trim();
  return expenses.value.filter(e => {
    if (q && !(`${e.description} ${e.category}`.toLowerCase().includes(q))) return false;
    if (selectedCategory.value && e.category !== selectedCategory.value) return false;
    if (dateFrom.value && e.date < dateFrom.value) return false;
    if (dateTo.value && e.date > dateTo.value) return false;
    return true;
  });
});
const filteredTotal = computed(() => filteredExpenses.value.reduce((s, e) => s + Number(e.amount), 0));
const clearFilters = () => { searchQuery.value = ''; selectedCategory.value = ''; dateFrom.value = ''; dateTo.value = ''; };
const handleExport = () => { window.location.href = '/api/expenses/export'; };
const fetchData = async () => { try { const [list, stat] = await Promise.all([expApi.list(100), expApi.stats()]); expenses.value = (list || []).sort((a,b)=> new Date(b.date)-new Date(a.date)); stats.value = stat; loadError.value = false; } catch (e) { loadError.value = true; } finally { loading.value = false; } };
const startEdit = (exp) => { editingId.value = exp.id; draft.value = { description: exp.description, amount: String(exp.amount), category: exp.category || '' }; };
const saveEdit = async (id) => { busyId.value = id; try { await expApi.update(id, { description: draft.value.description, amount: Number(draft.value.amount), category: draft.value.category }); editingId.value = null; await fetchData(); } catch (e) { alert(e.response?.data?.message || 'Gagal update — cek nominal/kategori'); } finally { busyId.value = null; } };
const handleDelete = async (id) => { if (!confirm('Hapus pengeluaran ini?')) return; busyId.value = id; try { await expApi.remove(id); await fetchData(); } finally { busyId.value = null; } };
onMounted(fetchData);
</script>
