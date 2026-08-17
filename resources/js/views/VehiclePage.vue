<template>
  <div class="space-y-10 max-w-6xl mx-auto">
    <div class="flex items-start justify-between">
      <div class="space-y-3">
        <h1 class="text-5xl font-semibold text-on-surface tracking-[-0.02em] leading-[1.1]">Vehicle</h1>
        <p class="text-lg text-on-surface-variant max-w-2xl leading-relaxed">Status servis kendaraan.</p>
      </div>
      <button v-if="!loading && !editing" @click="startEdit" class="flex items-center gap-2 px-4 py-2 text-sm font-semibold bg-primary-container text-on-primary-container rounded hover:bg-primary hover:text-on-primary transition-all active:scale-95">
        <Pencil class="w-4 h-4" /> Edit
      </button>
    </div>
    <div v-if="loading" class="p-6 text-center text-on-surface-variant">Loading...</div>
    <div v-else-if="editing" class="bg-surface-container border border-outline-variant/20 rounded-lg backdrop-blur-md p-6 space-y-4 max-w-md">
      <div><label class="block text-sm font-medium text-on-surface-variant mb-2">KM Terakhir</label>
        <input v-model="draft.last_km" type="number" class="w-full rounded border border-outline-variant/30 bg-surface px-3 py-2 text-on-surface focus:border-primary focus:outline-none" /></div>
      <div><label class="block text-sm font-medium text-on-surface-variant mb-2">Servis Berikutnya (KM)</label>
        <input v-model="draft.next_service_km" type="number" class="w-full rounded border border-outline-variant/30 bg-surface px-3 py-2 text-on-surface focus:border-primary focus:outline-none" /></div>
      <div class="flex gap-2">
        <button @click="save" :disabled="busy" class="flex items-center gap-2 px-4 py-2 text-sm font-semibold bg-primary text-on-primary rounded hover:bg-primary/90 disabled:opacity-50"><Check class="w-4 h-4" /> Simpan</button>
        <button @click="editing = false" :disabled="busy" class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-highest rounded disabled:opacity-50"><X class="w-4 h-4" /> Batal</button>
      </div>
    </div>
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
      <StatCard title="Sisa KM Sebelum Servis" :value="`${vehicle?.remaining_km || 0} KM`" :subtitle="vehicle?.status"><template #icon><Gauge class="w-6 h-6 md:w-8 md:h-8" /></template></StatCard>
      <StatCard title="KM Terakhir" :value="vehicle?.last_km ? `${vehicle.last_km.toLocaleString('id-ID')} KM` : '-'"><template #icon><Car class="w-6 h-6 md:w-8 md:h-8" /></template></StatCard>
      <StatCard title="Servis Berikutnya" :value="vehicle?.next_service_km ? `${vehicle.next_service_km.toLocaleString('id-ID')} KM` : '-'"><template #icon><Wrench class="w-6 h-6 md:w-8 md:h-8" /></template></StatCard>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import StatCard from '../components/StatCard.vue';
import { vehicle as vehApi } from '../api/client.js';
import { Car, Gauge, Wrench, Pencil, Check, X } from '@lucide/vue';
const vehicle = ref(null);
const loading = ref(true);
const editing = ref(false);
const draft = ref({ last_km: '', next_service_km: '' });
const busy = ref(false);
const fetchData = async () => { vehicle.value = await vehApi.get(); loading.value = false; };
const startEdit = () => { draft.value = { last_km: String(vehicle.value?.last_km ?? ''), next_service_km: String(vehicle.value?.next_service_km ?? '') }; editing.value = true; };
const save = async () => { busy.value = true; try { await vehApi.update({ last_km: Number(draft.value.last_km), next_service_km: Number(draft.value.next_service_km) }); editing.value = false; await fetchData(); } finally { busy.value = false; } };
onMounted(fetchData);
</script>
