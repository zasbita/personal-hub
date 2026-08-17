<template>
  <div class="space-y-10 max-w-6xl mx-auto">
    <div class="space-y-3">
      <h1 class="text-5xl font-semibold text-on-surface tracking-[-0.02em] leading-[1.1]">Expenses</h1>
      <p class="text-lg text-on-surface-variant max-w-2xl leading-relaxed">Riwayat pengeluaran bulan ini.</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 md:gap-6">
      <StatCard title="Total Bulan Ini" :value="`Rp ${(stats?.total || 0).toLocaleString('id-ID')}`"><template #icon><TrendingUp class="w-6 h-6 md:w-8 md:h-8" /></template></StatCard>
      <StatCard title="Jumlah Transaksi" :value="String(stats?.count || 0)"><template #icon><Receipt class="w-6 h-6 md:w-8 md:h-8" /></template></StatCard>
    </div>
    <div class="bg-surface-container border border-outline-variant/20 rounded-lg backdrop-blur-md">
      <div v-if="loading" class="p-6 text-center text-on-surface-variant">Loading...</div>
      <div v-else-if="expenses.length === 0" class="p-6 text-center text-on-surface-variant">Belum ada data pengeluaran</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full">
          <thead><tr class="border-b border-outline-variant/10">
            <th class="text-left px-6 py-3 text-sm font-semibold text-on-surface">Tanggal</th>
            <th class="text-left px-6 py-3 text-sm font-semibold text-on-surface">Deskripsi</th>
            <th class="text-left px-6 py-3 text-sm font-semibold text-on-surface">Kategori</th>
            <th class="text-right px-6 py-3 text-sm font-semibold text-on-surface">Jumlah</th>
            <th class="text-right px-6 py-3 text-sm font-semibold text-on-surface">Aksi</th>
          </tr></thead>
          <tbody><tr v-for="exp in expenses" :key="exp.id" class="border-b border-outline-variant/10 hover:bg-surface-container-high transition-all group">
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
import { ref, onMounted } from 'vue';
import StatCard from '../components/StatCard.vue';
import { expenses as expApi } from '../api/client.js';
import { TrendingUp, Receipt, Pencil, Trash2, Check, X } from '@lucide/vue';
const expenses = ref([]);
const stats = ref(null);
const loading = ref(true);
const editingId = ref(null);
const draft = ref({ description: '', amount: '', category: '' });
const busyId = ref(null);
const fetchData = async () => { const [list, stat] = await Promise.all([expApi.list(50), expApi.stats()]); expenses.value = list || []; stats.value = stat; loading.value = false; };
const startEdit = (exp) => { editingId.value = exp.id; draft.value = { description: exp.description, amount: String(exp.amount), category: exp.category || '' }; };
const saveEdit = async (id) => { busyId.value = id; try { await expApi.update(id, draft.value); editingId.value = null; await fetchData(); } finally { busyId.value = null; } };
const handleDelete = async (id) => { if (!confirm('Hapus pengeluaran ini?')) return; busyId.value = id; try { await expApi.remove(id); await fetchData(); } finally { busyId.value = null; } };
onMounted(fetchData);
</script>
