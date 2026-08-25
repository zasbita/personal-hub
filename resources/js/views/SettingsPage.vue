<template>
  <div class="space-y-10 max-w-6xl mx-auto">
    <div class="space-y-3">
      <h1 class="text-5xl font-semibold text-on-surface tracking-[-0.02em] leading-[1.1]">Settings</h1>
      <p class="text-lg text-on-surface-variant max-w-2xl leading-relaxed">Informasi akun & budget per kategori.</p>
    </div>
    <div class="bg-surface-container border border-outline-variant/20 rounded-lg backdrop-blur-md p-6 space-y-4 max-w-md">
      <div class="flex items-center gap-3"><Mail class="w-5 h-5 text-primary shrink-0" /><div><p class="text-sm text-on-surface-variant">Email</p><p class="text-on-surface font-medium">{{ user?.email || '-' }}</p></div></div>
      <div class="flex items-center gap-3"><User class="w-5 h-5 text-primary shrink-0" /><div><p class="text-sm text-on-surface-variant">User ID</p><p class="text-on-surface font-medium font-mono text-sm">{{ user?.id || '-' }}</p></div></div>
      <div class="flex items-center justify-between pt-2 border-t border-outline-variant/10">
        <div class="flex items-center gap-2"><Bell class="w-4 h-4 text-primary" /><p class="text-sm text-on-surface">Notifikasi Budget Browser</p></div>
        <button @click="toggleNotify" class="px-3 py-1 text-xs font-semibold rounded-full" :class="notifyEnabled ? 'bg-primary text-on-primary' : 'bg-surface-container-highest text-on-surface-variant'">{{ notifyEnabled ? 'ON' : 'OFF' }}</button>
      </div>
      <button @click="handleLogout" class="w-full flex items-center justify-center gap-2 mt-4 px-4 py-3 text-sm font-semibold bg-error-container text-on-error-container rounded-lg hover:bg-error hover:text-on-error transition-all active:scale-95"><LogOut class="w-4 h-4" /> Logout</button>
    </div>

    <section class="space-y-4 max-w-2xl">
      <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold text-on-surface tracking-[-0.02em]">Budget per Kategori</h2>
        <span class="text-sm text-on-surface-variant">{{ budgets.length }} kategori</span>
      </div>
      <div class="bg-surface-container border border-outline-variant/20 rounded-lg p-4 space-y-3">
        <div class="flex gap-2">
          <input v-model="form.category" placeholder="Kategori (mis. Jajan)" class="flex-1 rounded-lg border border-outline-variant/30 bg-surface px-3 py-2 text-sm text-on-surface placeholder:text-on-surface-variant focus:border-primary focus:outline-none" />
          <input v-model="form.monthly_limit" type="number" placeholder="Limit/bulan" class="w-36 rounded-lg border border-outline-variant/30 bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none" />
          <button @click="handleCreate" :disabled="busy" class="px-4 py-2 text-sm font-semibold bg-primary text-on-primary rounded-lg hover:bg-primary/90 disabled:opacity-50">Simpan</button>
        </div>
        <p v-if="formError" class="text-sm text-error">{{ formError }}</p>
        <div v-if="loadingBudgets" class="text-sm text-on-surface-variant">Loading...</div>
        <div v-else-if="budgets.length === 0" class="text-sm text-on-surface-variant">Belum ada budget kategori</div>
        <div v-else class="divide-y divide-outline-variant/10">
          <div v-for="b in budgets" :key="b.id" class="flex items-center justify-between py-3">
            <div><p class="text-on-surface font-medium">{{ b.category }}</p><p class="text-sm text-on-surface-variant">Rp {{ Number(b.monthly_limit).toLocaleString('id-ID') }} / bulan</p></div>
            <div class="flex items-center gap-2">
              <input v-if="editingId === b.id" v-model="editLimit" type="number" class="w-28 rounded border border-outline-variant/30 bg-surface px-2 py-1 text-sm text-on-surface focus:border-primary focus:outline-none" />
              <template v-if="editingId === b.id">
                <button @click="handleUpdate(b.id)" :disabled="busy" class="p-1.5 rounded text-primary hover:bg-primary/10"><Check class="w-4 h-4" /></button>
                <button @click="editingId = null" class="p-1.5 rounded text-on-surface-variant hover:bg-surface-container-highest"><X class="w-4 h-4" /></button>
              </template>
              <template v-else>
                <button @click="startEdit(b)" class="p-1.5 rounded text-on-surface-variant hover:text-primary hover:bg-primary/10"><Pencil class="w-4 h-4" /></button>
                <button @click="handleDelete(b.id)" :disabled="busy" class="p-1.5 rounded text-on-surface-variant hover:text-tertiary hover:bg-tertiary/10"><Trash2 class="w-4 h-4" /></button>
              </template>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="space-y-4 max-w-2xl">
      <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold text-on-surface tracking-[-0.02em]">Pengeluaran Berulang</h2>
        <span class="text-sm text-on-surface-variant flex items-center gap-1"><Repeat class="w-4 h-4" /> {{ recurrings.length }} jadwal</span>
      </div>
      <div class="bg-surface-container border border-outline-variant/20 rounded-lg p-4 space-y-3">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
          <input v-model="recForm.amount" type="number" placeholder="Nominal" class="rounded-lg border border-outline-variant/30 bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none" />
          <input v-model="recForm.description" placeholder="Deskripsi" class="rounded-lg border border-outline-variant/30 bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none" />
          <input v-model="recForm.category" placeholder="Kategori" class="rounded-lg border border-outline-variant/30 bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none" />
          <input v-model="recForm.day_of_month" type="number" min="1" max="31" placeholder="Tgl 1-31" class="rounded-lg border border-outline-variant/30 bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none" />
        </div>
        <button @click="handleRecCreate" :disabled="busy" class="w-full px-4 py-2 text-sm font-semibold bg-primary text-on-primary rounded-lg hover:bg-primary/90 disabled:opacity-50">Tambah Recurring</button>
        <p v-if="recError" class="text-sm text-error">{{ recError }}</p>
        <div v-if="loadingRec" class="text-sm text-on-surface-variant">Loading...</div>
        <div v-else-if="recurrings.length === 0" class="text-sm text-on-surface-variant">Belum ada pengeluaran berulang</div>
        <div v-else class="divide-y divide-outline-variant/10">
          <div v-for="r in recurrings" :key="r.id" class="flex items-center justify-between py-3">
            <div><p class="text-on-surface font-medium">Rp {{ Number(r.amount).toLocaleString('id-ID') }} - {{ r.description }}</p><p class="text-sm text-on-surface-variant flex items-center gap-1"><Calendar class="w-3 h-3" /> Tgl {{ r.day_of_month }} · {{ r.category }}</p></div>
            <button @click="handleRecDelete(r.id)" :disabled="busy" class="p-1.5 rounded text-on-surface-variant hover:text-tertiary hover:bg-tertiary/10"><Trash2 class="w-4 h-4" /></button>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { categoryBudgets as budgetApi } from '../api/client.js';
import { LogOut, Mail, User, Pencil, Trash2, Check, X, Bell } from '@lucide/vue';
const router = useRouter();
const authStore = useAuthStore();
const user = computed(() => authStore.user);
const notifyEnabled = ref(localStorage.getItem('ph_notify') !== 'off');
const toggleNotify = async () => {
  if (notifyEnabled.value) { notifyEnabled.value = false; localStorage.setItem('ph_notify', 'off'); }
  else {
    if ('Notification' in window) {
      const p = await Notification.requestPermission();
      if (p !== 'granted') return;
    }
    notifyEnabled.value = true; localStorage.removeItem('ph_notify');
    if ('Notification' in window && Notification.permission === 'granted') new Notification('🔔 Notifikasi budget aktif');
  }
};
const handleLogout = async () => { await authStore.logout(); router.push('/login'); };

const budgets = ref([]);
const loadingBudgets = ref(true);
const busy = ref(false);
const form = ref({ category: '', monthly_limit: '' });
const formError = ref('');
const editingId = ref(null);
const editLimit = ref('');

const fetchBudgets = async () => { try { budgets.value = await budgetApi.list() || []; } finally { loadingBudgets.value = false; } };
const handleCreate = async () => {
  formError.value = '';
  if (!form.value.category.trim() || !form.value.monthly_limit) { formError.value = 'Isi kategori & limit'; return; }
  busy.value = true;
  try { await budgetApi.create({ category: form.value.category.trim(), monthly_limit: Number(form.value.monthly_limit) }); form.value = { category: '', monthly_limit: '' }; await fetchBudgets(); } catch (e) { formError.value = e.response?.data?.message || 'Gagal simpan'; } finally { busy.value = false; }
};
const startEdit = (b) => { editingId.value = b.id; editLimit.value = String(b.monthly_limit); };
const handleUpdate = async (id) => { busy.value = true; try { await budgetApi.update(id, { monthly_limit: Number(editLimit.value) }); editingId.value = null; await fetchBudgets(); } finally { busy.value = false; } };
const handleDelete = async (id) => { if (!confirm('Hapus budget ini?')) return; busy.value = true; try { await budgetApi.remove(id); await fetchBudgets(); } finally { busy.value = false; } };

// Recurring — ponytail: same pattern as budgets, no extra abstraction
import { recurringExpenses as recApi } from '../api/client.js';
import { Repeat, Calendar } from '@lucide/vue';
const recurrings = ref([]);
const loadingRec = ref(true);
const recForm = ref({ amount: '', description: '', category: '', day_of_month: '' });
const recError = ref('');
const fetchRec = async () => { try { recurrings.value = await recApi.list() || []; } finally { loadingRec.value = false; } };
const handleRecCreate = async () => {
  recError.value = '';
  if (!recForm.value.amount || !recForm.value.description || !recForm.value.day_of_month) { recError.value = 'Isi nominal, deskripsi, tgl'; return; }
  busy.value = true;
  try { await recApi.create({ amount: Number(recForm.value.amount), description: recForm.value.description.trim(), category: recForm.value.category.trim() || 'General', day_of_month: Number(recForm.value.day_of_month) }); recForm.value = { amount: '', description: '', category: '', day_of_month: '' }; await fetchRec(); } catch (e) { recError.value = e.response?.data?.message || 'Gagal simpan'; } finally { busy.value = false; }
};
const handleRecDelete = async (id) => { if (!confirm('Hapus recurring ini?')) return; busy.value = true; try { await recApi.remove(id); await fetchRec(); } finally { busy.value = false; } };
onMounted(fetchRec);
</script>
