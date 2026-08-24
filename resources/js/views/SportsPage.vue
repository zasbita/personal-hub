<template>
  <div class="space-y-10 max-w-6xl mx-auto">
    <div class="flex items-start justify-between gap-4">
      <div class="space-y-3">
        <h1 class="text-5xl font-semibold text-on-surface tracking-[-0.02em] leading-[1.1]">Sports</h1>
        <p class="text-lg text-on-surface-variant max-w-2xl leading-relaxed">Jadwal pertandingan mendatang dan preferensi notifikasi.</p>
      </div>
      <button @click="showModal = true" class="flex items-center gap-2 px-4 py-2 text-sm font-semibold bg-primary-container text-on-primary-container rounded hover:bg-primary hover:text-on-primary transition-all active:scale-95">
        <Plus class="w-4 h-4" /> Tambah Jadwal
      </button>
    </div>
    <div v-if="loadError" class="p-4 rounded border border-error/40 text-error text-center">Gagal memuat data. Coba muat ulang halaman.</div>
    <section class="space-y-4">
      <h2 class="text-2xl font-semibold text-on-surface tracking-[-0.02em]">Pertandingan Mendatang</h2>
      <div class="bg-surface-container border border-outline-variant/20 rounded-lg backdrop-blur-md">
        <div v-if="matches.length === 0" class="p-6 text-center text-on-surface-variant">Tidak ada pertandingan mendatang</div>
        <div v-else class="divide-y divide-outline-variant/10">
          <div v-for="m in matches" :key="m.id" class="p-4 flex items-center gap-3">
            <Zap class="w-5 h-5 text-primary shrink-0" />
            <div><p class="text-on-surface font-medium capitalize">{{ m.home_team ? (m.away_team ? m.home_team + ' vs ' + m.away_team : m.home_team) : (m.competition || m.sport_type) }}</p>
              <p class="text-sm text-on-surface-variant">{{ m.match_time ? new Date(m.match_time).toLocaleString('id-ID') : '' }}<template v-if="m.competition"> • {{ m.competition }}</template></p></div>
          </div>
        </div>
      </div>
    </section>
    <section class="space-y-4">
      <h2 class="text-2xl font-semibold text-on-surface tracking-[-0.02em]">Preferensi Notifikasi</h2>
      <div class="bg-surface-container border border-outline-variant/20 rounded-lg backdrop-blur-md">
        <div v-if="prefs.length === 0" class="p-6 text-center text-on-surface-variant">Belum ada preferensi</div>
        <div v-else class="divide-y divide-outline-variant/10">
          <div v-for="p in prefs" :key="p.id" class="p-4 flex items-center justify-between">
            <div><p class="text-on-surface font-medium capitalize">{{ p.sport_type }}</p><p class="text-sm text-on-surface-variant">{{ p.entity_name }}</p></div>
            <div class="flex items-center gap-2">
              <button @click="toggleNotif(p)" :disabled="busyId === p.id" class="p-1.5 rounded hover:bg-surface-container-highest disabled:opacity-50">
                <Bell v-if="p.notification_enabled" class="w-5 h-5 text-primary" /><BellOff v-else class="w-5 h-5 text-on-surface-variant" /></button>
              <button @click="handleDeletePref(p.id)" :disabled="busyId === p.id" class="p-1.5 rounded text-on-surface-variant hover:text-tertiary hover:bg-tertiary/10 disabled:opacity-50"><Trash2 class="w-4 h-4" /></button>
            </div>
          </div>
        </div>
      </div>
    </section>
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="w-full max-w-md bg-surface-container border border-outline-variant/30 rounded-xl p-6 backdrop-blur-xl shadow-2xl">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold text-on-surface">Tambah Jadwal Pertandingan</h2>
            <button @click="showModal = false" class="p-1 rounded hover:bg-surface-container-highest"><X class="w-5 h-5 text-on-surface-variant" /></button>
          </div>
          <form @submit.prevent="createMatch" class="space-y-4">
            <div><label class="block text-sm font-medium text-on-surface-variant mb-2">Jenis Olahraga</label>
              <select v-model="form.sport_type" class="w-full rounded-lg border border-outline-variant/30 bg-surface px-4 py-3 text-on-surface focus:border-primary focus:outline-none">
                <option value="volly">Volley</option><option value="football">Sepak Bola</option><option value="basketball">Basket</option><option value="badminton">Bulu Tangkis</option><option value="tennis">Tenis</option></select></div>
            <div><label class="block text-sm font-medium text-on-surface-variant mb-2">Waktu Pertandingan</label>
              <input v-model="form.match_time" type="datetime-local" :min="now" required class="w-full rounded-lg border border-outline-variant/30 bg-surface px-4 py-3 text-on-surface focus:border-primary focus:outline-none" /></div>
            <div><label class="block text-sm font-medium text-on-surface-variant mb-2">Nama Turnamen (opsional)</label>
              <input v-model="form.tournament" type="text" placeholder="Contoh: AVC Challenge Cup" class="w-full rounded-lg border border-outline-variant/30 bg-surface px-4 py-3 text-on-surface focus:border-primary focus:outline-none" /></div>
            <div class="grid grid-cols-2 gap-4">
              <div><label class="block text-sm font-medium text-on-surface-variant mb-2">Tim Kandang</label>
                <input v-model="form.home_team" type="text" required placeholder="Home" class="w-full rounded-lg border border-outline-variant/30 bg-surface px-4 py-3 text-on-surface focus:border-primary focus:outline-none" /></div>
              <div><label class="block text-sm font-medium text-on-surface-variant mb-2">Tim Tandang</label>
                <input v-model="form.away_team" type="text" placeholder="Away" class="w-full rounded-lg border border-outline-variant/30 bg-surface px-4 py-3 text-on-surface focus:border-primary focus:outline-none" /></div>
            </div>
            <div class="flex gap-3 pt-2">
              <button type="button" @click="showModal = false" class="flex-1 px-4 py-3 text-sm font-medium text-on-surface-variant hover:bg-surface-container-highest rounded">Batal</button>
              <button type="submit" :disabled="submitting" class="flex-1 px-4 py-3 text-sm font-semibold bg-primary text-on-primary rounded hover:bg-primary/90 disabled:opacity-50">{{ submitting ? 'Menyimpan...' : 'Simpan' }}</button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { matches as matchApi, preferences as prefApi } from '../api/client.js';
import { Zap, Bell, BellOff, Trash2, Plus, X } from '@lucide/vue';
const matches = ref([]);
const prefs = ref([]);
const loading = ref(true);
const busyId = ref(null);
const loadError = ref(false);
const showModal = ref(false);
const submitting = ref(false);
const now = new Date().toISOString().slice(0, 16);
const form = ref({ sport_type: 'volly', match_time: '', tournament: '', home_team: '', away_team: '' });
const fetchData = async () => { try { const [m, p] = await Promise.all([matchApi.list(), prefApi.list()]); matches.value = m || []; prefs.value = p || []; loadError.value = false; } catch (e) { loadError.value = true; } finally { loading.value = false; } };
const toggleNotif = async (p) => { busyId.value = p.id; try { await prefApi.update(p.id, !p.notification_enabled); await fetchData(); } finally { busyId.value = null; } };
const handleDeletePref = async (id) => { if (!confirm('Berhenti memantau?')) return; busyId.value = id; try { await prefApi.remove(id); await fetchData(); } finally { busyId.value = null; } };
const createMatch = async () => { submitting.value = true; try { await matchApi.create(form.value); showModal.value = false; form.value = { sport_type: 'volly', match_time: '', tournament: '', home_team: '', away_team: '' }; await fetchData(); } catch (e) { alert('Gagal menambahkan jadwal'); } finally { submitting.value = false; } };
onMounted(fetchData);
</script>
