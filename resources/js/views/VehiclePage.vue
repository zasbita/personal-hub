<template>
  <div class="space-y-10 max-w-6xl mx-auto">
    <div class="flex items-start justify-between">
      <div class="space-y-3">
        <h1 class="text-5xl font-semibold text-on-surface tracking-[-0.02em] leading-[1.1]">Vehicle</h1>
        <p class="text-lg text-on-surface-variant max-w-2xl leading-relaxed">Status servis kendaraan.</p>
      </div>
      <button v-if="!loading && !editing && vehicles.length === 0" @click="startEdit" class="flex items-center gap-2 px-4 py-2 text-sm font-semibold bg-primary-container text-on-primary-container rounded hover:bg-primary hover:text-on-primary transition-all active:scale-95">
        <Pencil class="w-4 h-4" /> Edit
      </button>
    </div>
    <div v-if="loading" class="p-6 text-center text-on-surface-variant">Loading...</div>
    <div v-else-if="loadError" class="p-6 text-center text-error">Gagal memuat data kendaraan. Coba muat ulang halaman.</div>
    <template v-else>
      <!-- Legacy single vehicle (when no multi) -->
      <div v-if="vehicles.length === 0">
        <div v-if="editing" class="bg-surface-container border border-outline-variant/20 rounded-lg backdrop-blur-md p-6 space-y-4 max-w-md">
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

      <!-- Multi-vehicle -->
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-2xl font-semibold text-on-surface">Daftar Kendaraan</h2>
          <button @click="showCreate = !showCreate" class="flex items-center gap-2 px-4 py-2 text-sm font-semibold bg-primary-container text-on-primary-container rounded hover:bg-primary hover:text-on-primary"><Plus class="w-4 h-4" /> Tambah</button>
        </div>
        <div v-if="showCreate" class="bg-surface-container border border-outline-variant/20 rounded-lg p-4 space-y-3 max-w-xl">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <input v-model="createForm.name" placeholder="Nama (Beat, NMax)" class="rounded-lg border border-outline-variant/30 bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none" />
            <input v-model="createForm.last_km" type="number" placeholder="KM sekarang" class="rounded-lg border border-outline-variant/30 bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none" />
            <input v-model="createForm.next_service_km" type="number" placeholder="Target servis KM" class="rounded-lg border border-outline-variant/30 bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none" />
          </div>
          <div class="flex gap-2">
            <button @click="handleCreate" :disabled="busy" class="px-4 py-2 text-sm font-semibold bg-primary text-on-primary rounded hover:bg-primary/90 disabled:opacity-50">Simpan</button>
            <button @click="showCreate = false" class="px-4 py-2 text-sm font-medium text-on-surface-variant hover:bg-surface-container-highest rounded">Batal</button>
          </div>
          <p v-if="createError" class="text-sm text-error">{{ createError }}</p>
        </div>
        <div v-if="vehicles.length === 0" class="text-sm text-on-surface-variant">Belum ada kendaraan multi — pakai form di atas untuk tambah. Data lama tetap di atas.</div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-for="v in vehicles" :key="v.id" class="bg-surface-container border border-outline-variant/20 rounded-lg p-4 space-y-3">
            <div class="flex items-start justify-between">
              <div><p class="text-on-surface font-semibold">{{ v.name }}</p><p class="text-sm text-on-surface-variant">{{ v.last_km?.toLocaleString('id-ID') }} KM → {{ v.next_service_km?.toLocaleString('id-ID') }} KM · sisa {{ Math.max(0, v.next_service_km - v.last_km).toLocaleString('id-ID') }} KM</p></div>
              <button @click="handleDeleteVehicle(v.id)" class="p-1.5 rounded text-on-surface-variant hover:text-tertiary hover:bg-tertiary/10"><Trash2 class="w-4 h-4" /></button>
            </div>
            <div class="flex gap-2">
              <input v-model="editKm[v.id]" type="number" placeholder="Update KM" class="flex-1 rounded border border-outline-variant/30 bg-surface px-2 py-1 text-sm text-on-surface focus:border-primary focus:outline-none" />
              <button @click="handleUpdateKm(v.id)" :disabled="busy" class="px-3 py-1 text-sm font-semibold bg-primary text-on-primary rounded hover:bg-primary/90 disabled:opacity-50">Update</button>
            </div>
            <div class="h-2 rounded-full bg-surface-container-highest overflow-hidden">
              <div class="h-full bg-primary rounded-full" :style="{ width: Math.min(100, Math.max(0, 100 - Math.max(0, v.next_service_km - v.last_km) / (v.service_interval || 2000) * 100)) + '%' }"></div>
            </div>
            <!-- Fuel tracking — ponytail: km/L = (maxKm-minKm)/totalLiters, simple but useful -->
            <div class="pt-2 border-t border-outline-variant/10 space-y-2">
              <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-on-surface flex items-center gap-1"><Fuel class="w-4 h-4" /> BBM</p>
                <button @click="showFuel[v.id] = !showFuel[v.id]" class="text-xs text-primary hover:underline">{{ showFuel[v.id] ? 'Tutup' : 'Catat' }}</button>
              </div>
              <div v-if="fuelLogsByVehicle[v.id]?.length" class="text-xs text-on-surface-variant">
                <span v-if="fuelLogsByVehicle[v.id].length >= 2">
                  {{ (() => { const logs=[...fuelLogsByVehicle[v.id]].sort((a,b)=>a.km-b.km); const totL=logs.reduce((s,l)=>s+Number(l.liters),0); const dist=logs[logs.length-1].km - logs[0].km; const eff=totL? (dist/totL).toFixed(1):'-'; return `Efisiensi: ${eff} km/L · ${logs.length} catatan`; })() }}
                </span>
                <span v-else>{{ fuelLogsByVehicle[v.id].length }} catatan</span>
              </div>
              <div v-if="showFuel[v.id]" class="space-y-2">
                <div class="grid grid-cols-3 gap-1">
                  <input :value="fuelForm[v.id]?.km ?? ''" @input="setFuel(v.id,'km',$event.target.value)" type="number" placeholder="KM" class="rounded border border-outline-variant/30 bg-surface px-2 py-1 text-xs text-on-surface focus:border-primary focus:outline-none" />
                  <input :value="fuelForm[v.id]?.liters ?? ''" @input="setFuel(v.id,'liters',$event.target.value)" type="number" step="0.1" placeholder="Liter" class="rounded border border-outline-variant/30 bg-surface px-2 py-1 text-xs text-on-surface focus:border-primary focus:outline-none" />
                  <input :value="fuelForm[v.id]?.cost ?? ''" @input="setFuel(v.id,'cost',$event.target.value)" type="number" placeholder="Rp" class="rounded border border-outline-variant/30 bg-surface px-2 py-1 text-xs text-on-surface focus:border-primary focus:outline-none" />
                </div>
                <button @click="handleFuelSave(v.id)" :disabled="busy" class="w-full px-2 py-1 text-xs font-semibold bg-primary text-on-primary rounded hover:bg-primary/90 disabled:opacity-50">Simpan BBM</button>
                <div v-if="fuelLogsByVehicle[v.id]?.length" class="max-h-28 overflow-y-auto space-y-1">
                  <div v-for="log in fuelLogsByVehicle[v.id].slice(0,5)" :key="log.id" class="flex justify-between text-xs text-on-surface-variant">
                    <span><Droplets class="w-3 h-3 inline" /> {{ log.km.toLocaleString('id-ID') }}KM · {{ log.liters }}L<span v-if="log.cost"> · Rp{{ Number(log.cost).toLocaleString('id-ID') }}</span></span>
                    <button @click="handleFuelDelete(v.id, log.id)" class="text-tertiary hover:underline">hapus</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import StatCard from '../components/StatCard.vue';
import { vehicle as vehApi, fuelLogs as fuelApi } from '../api/client.js';
import { Car, Gauge, Wrench, Pencil, Check, X, Plus, Trash2, Fuel, Droplets } from '@lucide/vue';
const vehicle = ref(null);
const vehicles = ref([]);
const loading = ref(true);
const editing = ref(false);
const draft = ref({ last_km: '', next_service_km: '' });
const busy = ref(false);
const loadError = ref(false);
const showCreate = ref(false);
const createForm = ref({ name: '', last_km: '', next_service_km: '' });
const createError = ref('');
const editKm = ref({});
const fuelLogsByVehicle = ref({});
const fuelForm = ref({}); // vehicleId -> {km, liters, cost}
const showFuel = ref({});
const fetchFuelLogs = async (vehicleId) => {
  try { const logs = await fuelApi.list(vehicleId); fuelLogsByVehicle.value[vehicleId] = logs || []; } catch (e) { fuelLogsByVehicle.value[vehicleId] = []; }
};
const fetchData = async () => {
  try {
    const [single, list] = await Promise.all([vehApi.get().catch(() => null), vehApi.list().catch(() => [])]);
    vehicle.value = single;
    vehicles.value = Array.isArray(list) ? list : [];
    for (const v of vehicles.value) { fetchFuelLogs(v.id); }
    loadError.value = false;
  } catch (e) { loadError.value = true; } finally { loading.value = false; }
};
const startEdit = () => { draft.value = { last_km: String(vehicle.value?.last_km ?? ''), next_service_km: String(vehicle.value?.next_service_km ?? '') }; editing.value = true; };
const save = async () => { busy.value = true; try { await vehApi.update({ last_km: Number(draft.value.last_km), next_service_km: Number(draft.value.next_service_km) }); editing.value = false; await fetchData(); } finally { busy.value = false; } };
const handleCreate = async () => {
  createError.value = '';
  if (!createForm.value.name.trim() || !createForm.value.last_km || !createForm.value.next_service_km) { createError.value = 'Isi nama & KM'; return; }
  busy.value = true;
  try { await vehApi.create({ name: createForm.value.name.trim(), last_km: Number(createForm.value.last_km), next_service_km: Number(createForm.value.next_service_km) }); createForm.value = { name: '', last_km: '', next_service_km: '' }; showCreate.value = false; await fetchData(); } catch (e) { createError.value = e.response?.data?.message || 'Gagal tambah'; } finally { busy.value = false; }
};
const handleUpdateKm = async (id) => { const km = Number(editKm.value[id]); if (!km && km !== 0) return; busy.value = true; try { await vehApi.updateOne(id, { last_km: km }); editKm.value[id] = ''; await fetchData(); } finally { busy.value = false; } };
const handleDeleteVehicle = async (id) => { if (!confirm('Hapus kendaraan ini?')) return; busy.value = true; try { await vehApi.removeOne(id); await fetchData(); } finally { busy.value = false; } };
const setFuel = (vid, field, val) => { fuelForm.value[vid] = { ...(fuelForm.value[vid]||{}), [field]: val }; };
const handleFuelSave = async (vid) => { const f=fuelForm.value[vid]; if(!f?.km || !f?.liters) return; busy.value=true; try{ await fuelApi.create(vid, {km:Number(f.km), liters:Number(f.liters), cost: f.cost?Number(f.cost):undefined}); fuelForm.value[vid]={}; await fetchFuelLogs(vid); } finally{ busy.value=false; } };
const handleFuelDelete = async (vid, lid) => { busy.value=true; try{ await fuelApi.remove(vid, lid); await fetchFuelLogs(vid); } finally{ busy.value=false; } };
onMounted(fetchData);
</script>
