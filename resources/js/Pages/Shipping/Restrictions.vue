<template>
    <div class="min-h-screen bg-slate-50 p-6 md:p-12">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row items-center justify-between mb-8 p-6 bg-white rounded-2xl shadow-sm border border-slate-200 gap-4">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-indigo-50 rounded-xl shadow-lg shadow-indigo-100">
                        <img class="w-10 h-10 object-contain" src="https://i.ibb.co.com/W4cgwDRJ/download.png" alt="Icon">
                    </div>
                    <div>
                        <h1 class="text-2xl font-black text-slate-800">Shipping Zone Setup</h1>
                        <div class="flex items-center gap-3 mt-2">
                            <span class="text-[10px] font-bold text-slate-400 ">System Mode:</span>
                            <select v-model="form.mode"
                                class="text-xs font-black bg-slate-100 border-none rounded-lg px-3 py-1.5 outline-none text-indigo-600 cursor-pointer hover:bg-slate-200 transition-all">
                                <option value="" disabled>SELECT METHOD</option>
                                <option value="global">GLOBAL</option>
                                <option v-for="method in shippingMethods" :key="method.id" :value="String(method.id)">
                                    {{ method.title }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
                <button @click="saveSettings" :disabled="saving || !form.mode"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-10 py-3 rounded-xl font-bold transition-all shadow-lg disabled:opacity-50">
                    {{ saving ? 'Process...' : 'Save Configuration' }}
                </button>
            </div>

            <div class="grid md:grid-cols-2 gap-8 mb-12">
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-emerald-50 border-b border-emerald-100 p-5 font-extrabold text-emerald-900 text-sm">✓ Allowed Countries</div>
                    <div class="p-6">
                        <div class="relative mb-6">
                            <input v-model="newAllowed" @keyup.enter="add('allowed')" placeholder="ADD ISO (e.g. US)"
                                class="w-full pl-4 pr-12 py-3 bg-slate-50 border-2 rounded-2xl outline-none font-bold ">
                            <button @click="add('allowed')" class="absolute right-3 top-2 bg-emerald-500 p-2 rounded-lg text-white">+</button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <div v-for="(c, i) in form.allowed" :key="i" class="flex items-center gap-2 bg-white px-3 py-1 rounded-lg border border-slate-200 font-bold text-xs shadow-sm">
                                <span>{{c}}</span>
                                <button @click="remove('allowed', i)" class="text-rose-500">×</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-rose-50 border-b border-rose-100 p-5 font-extrabold text-rose-900 text-sm">✕ Excluded Countries</div>
                    <div class="p-6">
                        <div class="relative mb-6">
                            <input v-model="newExcluded" @keyup.enter="add('excluded')" placeholder="ADD ISO (e.g. CA)"
                                class="w-full pl-4 pr-12 py-3 bg-slate-50 border-2 rounded-2xl outline-none font-bold ">
                            <button @click="add('excluded')" class="absolute right-3 top-2 bg-rose-500 p-2 rounded-lg text-white">+</button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <div v-for="(c, i) in form.excluded" :key="i" class="flex items-center gap-2 bg-white px-3 py-1 rounded-lg border border-slate-200 font-bold text-xs shadow-sm">
                                <span>{{c}}</span>
                                <button @click="remove('excluded', i)" class="text-rose-500">×</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-slate-800">Applied Restriction Logs</h2>
                    <button @click="exportToExcel" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-xl text-xs font-bold transition-all shadow-md flex items-center gap-2">
                        DOWNLOAD EXCEL REPORT
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 text-slate-400 text-[10px] font-black">
                            <tr>
                                <th class="p-5">Order</th>
                                <th class="p-5">Country</th>
                                <th class="p-5">Allowed Rules</th>
                                <th class="p-5">Excluded Rules</th>
                                <th class="p-5">Status</th>
                                <th class="p-5">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="log in logs" :key="log.id" class="hover:bg-slate-50 transition-colors">
                                <td class="p-5 font-bold text-indigo-600">#{{ log.id }}</td>
                                <td class="p-5"><span class="bg-slate-900 text-white px-2 py-1 rounded text-[10px] font-bold">{{ log.country }}</span></td>
                                <td class="p-5 text-[9px] font-bold text-emerald-600">{{ log.allowed }}</td>
                                <td class="p-5 text-[9px] font-bold text-rose-600">{{ log.excluded }}</td>
                                <td class="p-5 text-[10px] font-black ">
                                    <span :class="log.status.includes('Passed') ? 'text-emerald-500' : 'text-rose-500'">● {{ log.status }}</span>
                                </td>
                                <td class="p-5 text-slate-300 text-xs">{{ log.date }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    allowed: Array,
    excluded: Array,
    mode: String,
    shippingMethods: Array,
    logs: Array,
    ajax_url: String,
    nonce: String
});

const form = reactive({
    mode: props.mode || 'global',
    allowed: [],
    excluded: []
});

// ✅ পেজ লোড হওয়া মাত্র ডেটা ইনজেক্ট করা
onMounted(() => {
    form.allowed = Array.isArray(props.allowed) ? [...props.allowed] : [];
    form.excluded = Array.isArray(props.excluded) ? [...props.excluded] : [];
    console.log('✅ Initial Data Loaded:', form.allowed, form.excluded);
});

const newAllowed = ref('');
const newExcluded = ref('');
const saving = ref(false);

// ✅ মোড চেঞ্জ করলে ডেটা ফেচ করার ফাংশন
const fetchMethodSettings = async (selectedMode) => {
    if (!selectedMode) return;

    try {
        const res = await axios.get(props.ajax_url, {
            params: {
                action: 'fc_get_method_settings',
                nonce: props.nonce,
                mode: selectedMode
            }
        });

        console.log('[FC FETCH DATA]', selectedMode, res.data);

        // ✅ এখানে ডাবল 'data.data' চেক করতে হবে আপনার API রেসপন্স অনুযায়ী
        const responseData = res.data?.data?.data ? res.data.data.data : (res.data?.data || {});

        if (res.data?.success) {
            form.allowed = Array.isArray(responseData.allowed) ? responseData.allowed : [];
            form.excluded = Array.isArray(responseData.excluded) ? responseData.excluded : [];
            console.log('✅ UI updated with:', form.allowed);
        } else {
            form.allowed = [];
            form.excluded = [];
        }
    } catch (e) {
        console.error('FETCH ERROR', e);
    }
};

// ✅ ড্রপডাউন চেঞ্জ ডিটেক্ট করা (সিম্পল ওয়াচ)
watch(() => form.mode, (newMode) => {
    console.log('🔄 Mode Changed to:', newMode);
    fetchMethodSettings(newMode);
});

// ---------------- ADD / REMOVE / SAVE ----------------
const add = (type) => {
    const field = type === 'allowed' ? newAllowed : newExcluded;
    const opposite = type === 'allowed' ? form.excluded : form.allowed;
    const val = field.value.toUpperCase().trim();

    if (!val) return;
    if (form[type].includes(val)) {
        Swal.fire('Already Added', `${val} already exists.`, 'info');
        field.value = '';
        return;
    }
    if (opposite.includes(val)) {
        Swal.fire('Conflict', `${val} cannot be in both lists.`, 'warning');
        field.value = '';
        return;
    }

    form[type].push(val);
    field.value = '';
};

const remove = (type, index) => {
    form[type].splice(index, 1);
};

const saveSettings = async () => {
    if (!form.mode) return;
    saving.value = true;

    const data = new FormData();
    data.append('action', 'fc_save_shipping_settings');
    data.append('nonce', props.nonce);
    data.append('mode', form.mode);
    data.append('allowed', JSON.stringify(form.allowed));
    data.append('excluded', JSON.stringify(form.excluded));

    try {
        const res = await axios.post(props.ajax_url, data);
        if (res.data?.success) {
            Swal.fire({ icon: 'success', title: 'Saved!', timer: 1200, showConfirmButton: false });
        }
    } catch (e) {
        Swal.fire('Error', 'Save failed', 'error');
    } finally {
        saving.value = false;
    }
};

const exportToExcel = () => {
    let csv = "\uFEFFORDER,COUNTRY,ALLOWED,EXCLUDED,STATUS,DATE\r\n";
    props.logs.forEach(log => {
        csv += `"#${log.id}","${log.country}","${log.allowed}","${log.excluded}","${log.status}","${log.date}"\r\n`;
    });
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `shipping_report_${Date.now()}.csv`;
    link.click();
};
</script>

