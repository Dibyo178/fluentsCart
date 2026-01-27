<?php

/**
 * Plugin Name: FluentCart Shipping Restriction
 * Description: Restrict shipping by country/method with professional Vue.js UI and high-accuracy Excel reporting.
 * Version: 1.0.0
 * Author: Sourov Purkayastha
 */

if (!defined('ABSPATH')) exit;

// 1. Setup Admin Menu
add_action('admin_menu', function () {
    add_menu_page('Shipping Rules', 'FC Shipping', 'manage_options', 'fc-shipping-restrictions', 'fc_render_admin_page', 'dashicons-admin-site', 56);
});

// 2. Data Persistence
add_action('wp_ajax_fc_save_shipping_settings', function () {
    check_ajax_referer('fc_shipping_nonce', 'nonce');
    if (isset($_POST['allowed'])) update_option('fc_allowed_countries', json_decode(stripslashes($_POST['allowed']), true));
    if (isset($_POST['excluded'])) update_option('fc_excluded_countries', json_decode(stripslashes($_POST['excluded']), true));
    if (isset($_POST['mode'])) update_option('fc_restriction_mode', sanitize_text_field($_POST['mode']));
    wp_send_json_success(['message' => 'Settings Updated']);
});

// 3. Admin UI
function fc_render_admin_page() {
    global $wpdb;
    
    $shipping_methods = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}fct_shipping_methods");
    $allowed = get_option('fc_allowed_countries', []);
    $excluded = get_option('fc_excluded_countries', []);
    $current_mode = get_option('fc_restriction_mode', '');
    
    // Fetch logs and prepare them for Vue
    $table_name = $wpdb->prefix . 'fct_order_meta';
    $raw_logs = $wpdb->get_results("SELECT order_id, meta_value, created_at FROM $table_name WHERE meta_key = '_fc_shipping_restrictions' ORDER BY created_at DESC LIMIT 100");
    
    $formatted_logs = [];
    foreach($raw_logs as $log) {
        $meta = json_decode($log->meta_value, true);
        $formatted_logs[] = [
            'id' => $log->order_id,
            'country' => $meta['order_country'] ?? 'N/A',
            'allowed' => implode(', ', (array)($meta['allowed_countries'] ?? [])),
            'excluded' => implode(', ', (array)($meta['excluded_countries'] ?? [])),
            'status' => str_replace('●', '', $meta['validation_status'] ?? 'N/A'),
            'date' => $log->created_at
        ];
    }
?>
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <div id="fcApp" class="min-h-screen bg-slate-50 p-6 md:p-12" v-cloak>
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row items-center justify-between mb-8 p-6 bg-white rounded-2xl shadow-sm border border-slate-200 gap-4">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-indigo-50 rounded-xl shadow-lg shadow-indigo-100">
                        <img class="w-10 h-10 object-contain" src="https://i.ibb.co.com/W4cgwDRJ/download.png" alt="Icon">
                    </div>
                    <div>
                        <h1 class="text-2xl font-black text-slate-800">Shipping Zone Setup</h1>
                        <div class="flex items-center gap-3 mt-2">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">System Mode:</span>
                            <select v-model="mode" class="text-xs font-black bg-slate-100 border-none rounded-lg px-3 py-1.5 outline-none text-indigo-600 uppercase cursor-pointer hover:bg-slate-200 transition-all">
                                <option value="" disabled selected>SELECT METHOD</option>
                                <option value="global">GLOBAL</option>
                                <option v-for="method in shippingMethods" :key="method.id" :value="String(method.id)">
                                    PER METHOD: {{ method.title.toUpperCase() }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
                <button @click="saveSettings()" :disabled="saving || !mode" class="bg-indigo-600 hover:bg-indigo-700 text-white px-10 py-3 rounded-xl font-bold transition-all shadow-lg disabled:opacity-50">
                    {{ saving ? 'Process...' : 'Save Configuration' }}
                </button>
            </div>

            <div class="grid md:grid-cols-2 gap-8 mb-12">
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-emerald-50 border-b border-emerald-100 p-5 font-extrabold text-emerald-900 text-sm uppercase">✓ Allowed Countries</div>
                    <div class="p-6">
                        <div class="relative mb-6">
                            <input v-model="newAllowed" @keyup.enter="add('allowed')" placeholder="ADD ISO (e.g. US)" class="w-full pl-4 pr-12 py-3 bg-slate-50 border-2 rounded-2xl outline-none font-bold uppercase">
                            <button @click="add('allowed')" class="absolute right-3 top-2 bg-emerald-500 p-2 rounded-lg text-white">+</button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <div v-for="(c, i) in allowed" :key="i" class="flex items-center gap-2 bg-white px-3 py-1 rounded-lg border border-slate-200 font-bold text-xs uppercase shadow-sm">
                                <span>{{c}}</span>
                                <button @click="remove('allowed', i)" class="text-rose-500">×</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-rose-50 border-b border-rose-100 p-5 font-extrabold text-rose-900 text-sm uppercase">✕ Excluded Countries</div>
                    <div class="p-6">
                        <div class="relative mb-6">
                            <input v-model="newExcluded" @keyup.enter="add('excluded')" placeholder="ADD ISO (e.g. CA)" class="w-full pl-4 pr-12 py-3 bg-slate-50 border-2 rounded-2xl outline-none font-bold uppercase">
                            <button @click="add('excluded')" class="absolute right-3 top-2 bg-rose-500 p-2 rounded-lg text-white">+</button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <div v-for="(c, i) in excluded" :key="i" class="flex items-center gap-2 bg-white px-3 py-1 rounded-lg border border-slate-200 font-bold text-xs uppercase shadow-sm">
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
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        DOWNLOAD EXCEL REPORT
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 text-slate-400 text-[10px] uppercase font-black">
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
                                <td class="p-5 text-[10px] font-black uppercase">
                                    <span :class="log.status.includes('Passed') ? 'text-emerald-500' : 'text-rose-500'">
                                        ● {{ log.status }}
                                    </span>
                                </td>
                                <td class="p-5 text-slate-300 text-xs">{{ log.date }}</td>
                            </tr>
                            <tr v-if="logs.length === 0">
                                <td colspan="6" class="p-10 text-center text-slate-400 font-bold">No history available.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const { createApp } = Vue;
        createApp({
            data() {
                return {
                    allowed: <?php echo json_encode($allowed); ?>,
                    excluded: <?php echo json_encode($excluded); ?>,
                    mode: "<?php echo $current_mode; ?>",
                    shippingMethods: <?php echo json_encode($shipping_methods); ?>,
                    logs: <?php echo json_encode($formatted_logs); ?>,
                    newAllowed: '',
                    newExcluded: '',
                    saving: false
                }
            },
            methods: {
               add(type) {
    let field = type === 'allowed' ? 'newAllowed' : 'newExcluded';
    let oppositeType = type === 'allowed' ? 'excluded' : 'allowed';
    let oppositeLabel = type === 'allowed' ? 'Excluded List' : 'Allowed List';
    
    let val = this[field].toUpperCase().trim();
    if (!val) return;

    // sweetalert show already have into nthis list 
    if (this[type].includes(val)) {
        Swal.fire({ 
            icon: 'info', 
            title: 'Already Added', 
            text: `${val} is already in this list.` 
        });
        this[field] = ''; 
        return;
    }

    //  check the (Allowed/Excluded) 
    if (this[oppositeType].includes(val)) {
        Swal.fire({ 
            icon: 'warning', 
            title: 'Conflict Detected', 
            text: `${val} is already defined in the ${oppositeLabel}. You cannot add it to both.` 
        });
        this[field] = ''; 
        return;
    }

    // if this logic is ok sum all of
    this[type].push(val);
    this[field] = '';
},
                remove(type, i) { this[type].splice(i, 1); },
                async saveSettings() {
                    this.saving = true;
                    const data = new FormData();
                    data.append('action', 'fc_save_shipping_settings');
                    data.append('nonce', "<?php echo wp_create_nonce('fc_shipping_nonce'); ?>");
                    data.append('allowed', JSON.stringify(this.allowed));
                    data.append('excluded', JSON.stringify(this.excluded));
                    data.append('mode', this.mode);
                    try {
                        const res = await axios.post(ajaxurl, data);
                        if (res.data.success) {
                            Swal.fire({ icon: 'success', title: 'Saved!', showConfirmButton: false, timer: 1000 }).then(() => window.location.reload());
                        }
                    } catch (e) { Swal.fire('Error', 'Failed to save', 'error'); }
                    this.saving = false;
                },
               exportToExcel() {
    let csvContent = "\uFEFF"; // Excel UTF-8 for support 
    csvContent += "ORDER,COUNTRY,ALLOWED RULES,EXCLUDED RULES,STATUS,DATE\r\n";
    
    this.logs.forEach(log => {
        
        let shortDate = log.date.split(' ')[0]; 
        
        let row = [
            `"#${log.id}"`,
            `"${log.country}"`,
            `"${log.allowed}"`,
            `"${log.excluded}"`,
            `"${log.status.trim()}"`,
            `"${shortDate}"` // smmal date configure
        ].join(",");
        csvContent += row + "\r\n";
    });

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", "shipping_report_" + new Date().toISOString().slice(0,10) + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
            }
        }).mount('#fcApp');
    </script>
<?php
}

// 4. Frontend Logic
add_action('wp_footer', function () {
    $allowed = (array)get_option('fc_allowed_countries', []);
    $excluded = (array)get_option('fc_excluded_countries', []);
    $systemMode = get_option('fc_restriction_mode', '');
    
    if ($systemMode !== 'global' && is_numeric($systemMode)) {
        echo "<style>
            .fct_shipping_methods_item { display: none !important; }
            .fct_shipping_methods_item:has(input[value='{$systemMode}']),
            .fct_shipping_methods_item:has(#shipping_method_{$systemMode}) { 
                display: block !important; 
            }
        </style>";
    }
?>
    <script type="text/javascript">
        (function() {
            const normAllowed = <?php echo json_encode($allowed); ?>;
            const normExcluded = <?php echo json_encode($excluded); ?>;
            const systemMode = "<?php echo esc_attr($systemMode); ?>";
            const msgId = 'fc-restriction-alert';

            function check() {
                if (!systemMode) return;
                const countryEl = document.querySelector('select[name*="country"], #billing_country, [name="shipping_country"], .fc_country_select');
                const btn = document.querySelector('.fct-checkout-submit, .fc_place_order, button[type="submit"], #place_order');
                const selectedInput = document.querySelector('input[name="fc_shipping_method"]:checked') || 
                                     document.querySelector('input[name="shipping_method"]:checked') ||
                                     document.querySelector('input[name="fc_selected_shipping_method"]');
                if (!countryEl || !btn) return;
                const activeMethodId = selectedInput ? String(selectedInput.value) : null;
                const restrictedMode = String(systemMode);
                if (restrictedMode !== 'global') {
                    const targetRadio = document.querySelector(`input[name="fc_shipping_method"][value="${restrictedMode}"], input[name="shipping_method"][value="${restrictedMode}"]`);
                    if (targetRadio && !targetRadio.checked) {
                        targetRadio.checked = true;
                        targetRadio.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    if (activeMethodId !== restrictedMode) {
                        resetUI(btn); 
                        return; 
                    }
                }
                const country = (countryEl.value || "").toUpperCase().trim();
                let isBlocked = false;
                let msg = "";
                let bgColor = "";
                if (normExcluded.includes(country)) {
                    isBlocked = true; msg = "🚫 We do not ship to this country."; bgColor = "#78350f";
                } else if (normAllowed.length > 0 && !normAllowed.includes(country)) {
                    isBlocked = true; msg = "⚠️ This country is not allowed for shipping."; bgColor = "#000000";
                }
                if (isBlocked) {
                    let alert = document.getElementById(msgId);
                    if (!alert) {
                        alert = document.createElement('div');
                        alert.id = msgId;
                        btn.parentNode.insertBefore(alert, btn);
                    }
                    alert.innerText = msg;
                    alert.style.cssText = `background:${bgColor}; color:#ffffff; padding:12px; margin:10px 0; border-radius:10px; text-align:center; font-weight:bold; font-size:14px;`;
                    btn.disabled = true; btn.style.opacity = '0.5'; btn.style.pointerEvents = 'none';
                } else {
                    resetUI(btn);
                }
            }
            function resetUI(btn) {
                const alert = document.getElementById(msgId);
                if (alert) alert.remove();
                if (btn) {
                    btn.disabled = false; btn.style.opacity = '1'; btn.style.pointerEvents = 'auto';
                }
            }
            document.addEventListener('change', (e) => {
                if(e.target.name === 'fc_shipping_method' || e.target.name === 'shipping_method' || e.target.name.includes('country')) {
                    setTimeout(check, 150);
                }
            });
            setInterval(check, 1000); 
        })();
    </script>
<?php
}, 999);

// 5. Database Logging
add_action('fluent_cart/order_created', function ($data) {
    global $wpdb;
    if (!isset($data['order'])) return;
    $order = $data['order'];
    $order_id = intval($order->id);
    $order_country = strtoupper(trim($order->billing_address['country'] ?? ''));
    $allowed = (array)get_option('fc_allowed_countries', []);
    $excluded = (array)get_option('fc_excluded_countries', []);
    $db_mode = get_option('fc_restriction_mode', '');
    $status = 'Passed';
    if (in_array($order_country, $excluded)) $status = 'Flagged: Excluded';
    elseif (!empty($allowed) && !in_array($order_country, $allowed)) $status = 'Flagged: Unauthorized';
    $restrictions = json_encode([
        'order_country' => $order_country,
        'validation_status' => $status,
        'applied_method_name' => is_numeric($db_mode) ? 'Method ID: '.$db_mode : strtoupper($db_mode),
        'allowed_countries' => $allowed,
        'excluded_countries' => $excluded
    ]);
    $wpdb->insert($wpdb->prefix . 'fct_order_meta', [
        'order_id'   => $order_id,
        'meta_key'   => '_fc_shipping_restrictions',
        'meta_value' => $restrictions,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ]);
}, 20, 1);