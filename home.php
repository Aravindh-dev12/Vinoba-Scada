<?php require 'check_auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="pageTitle">Solar Plant – Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="sidebar-control.js?v=3" defer></script>
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f8fafc; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<?php if (!empty($dbError)) { echo '<div style="background:#fee2e2;color:#991b1b;padding:12px;text-align:center;font-weight:bold;font-family:sans-serif;">'.htmlspecialchars($dbError).'</div>'; } ?>
<body class="h-full bg-slate-50 text-slate-800 font-sans">
    <div class="min-h-screen flex relative">
        <div id="overlay" class="fixed inset-0 bg-slate-900 bg-opacity-40 hidden z-30 md:hidden transition-opacity"></div>
        <div id="sidebar-container"></div>
        <main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
            <header class="bg-white p-4 sm:px-6 flex justify-between items-center sticky top-0 z-20 border-b border-slate-200 shadow-sm">
                <div class="flex items-center gap-3">
                    <?php if (($user['role'] ?? '') === 'admin') { ?>
                    <a href="admin.php<?php echo isset($_GET['token']) ? '?token='.urlencode($_GET['token']) : ''; ?>" class="flex items-center gap-2 px-3 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition" title="Back to Plants"><i class="fa-solid fa-arrow-left"></i><span class="text-sm font-bold hidden sm:inline">Back to Dashboard</span></a>
                    <?php } ?>
                    <button id="menuBtn" class="md:hidden text-emerald-600 text-2xl focus:outline-none">&#9776;</button>
                    <div><h2 class="text-xl font-black text-slate-800 tracking-tight">Live Plant Telemetry</h2></div>
                </div>
                <div class="flex items-center gap-3 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                    <div id="refreshPulse" class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]"></div>
                    <span class="text-xs font-bold text-slate-600 tracking-widest hidden sm:inline" id="clockDisplay">--:--:--</span>
                </div>
            </header>
            <div class="p-4 sm:p-6 w-full flex flex-col gap-6 max-w-[1600px] mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-6">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition duration-300">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-emerald-50 rounded-full blur-xl -z-10 group-hover:bg-emerald-100 transition"></div>
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Plant Profile</h3>
                        <p id="profileName" class="font-black text-slate-800 text-xl truncate">--</p>
                        <div class="flex items-baseline gap-2 mt-1">
                            <p id="profileCapacity" class="font-black text-emerald-600 text-2xl">-- <span class="text-sm font-bold">MW</span></p>
                            <p id="profileLocation" class="text-xs text-slate-500 font-medium border-l border-slate-200 pl-2">--</p>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition duration-300">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-50 rounded-full blur-xl -z-10 group-hover:bg-purple-100 transition"></div>
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Today Energy</h3>
                        <p class="font-black text-slate-800 text-3xl" id="vcb_etoday">-- <span class="text-sm font-bold text-purple-600">kWh</span></p>
                        <p class="text-xs text-slate-500 font-medium mt-1">Today Energy</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition duration-300">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-blue-50 rounded-full blur-xl -z-10 group-hover:bg-blue-100 transition"></div>
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Live Active Power</h3>
                        <p class="font-black text-slate-800 text-3xl" id="vcb_active">-- <span class="text-sm font-bold text-blue-600">kW</span></p>
                        <p class="text-xs text-slate-500 font-medium mt-1">Peak Today: <span id="vcb_peak" class="text-slate-700 font-bold">--</span> kW</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col justify-center relative overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-orange-50 rounded-full blur-xl -z-10"></div>
                        <div class="flex justify-between items-center border-b border-slate-50 pb-2 mb-2">
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Radiation</span>
                            <span class="font-bold text-orange-500"><span id="wmos_rad">--</span> <span class="text-[10px]">W/m2</span></span>
                        </div>
                        <div class="flex justify-between items-center border-b border-slate-50 pb-2 mb-2">
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Panel Temp</span>
                            <span class="font-bold text-red-500"><span id="wmos_ptemp">--</span> <span class="text-[10px]">degC</span></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Wind Speed</span>
                            <span class="font-bold text-sky-500"><span id="wmos_wind">--</span> <span class="text-[10px]">m/s</span></span>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                        <h3 class="text-sm font-black text-slate-600 uppercase tracking-widest">
                            <i class="fa-regular fa-clock text-emerald-500 mr-2"></i>Generation Start & End Time
                        </h3>
                        <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Live WebSocket</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="rounded-xl border border-blue-100 bg-blue-50/40 p-4">
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-xs font-black text-blue-700 uppercase tracking-wider">Combined Inverters</p>
                                <span id="inverter_time_status" class="text-[10px] font-bold rounded-full bg-slate-100 text-slate-500 px-2 py-1">WAITING</span>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">Start Time</p><p id="inverter_start_time" class="text-xl font-black text-slate-800 mt-1">--:--</p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">End / Latest</p><p id="inverter_end_time" class="text-xl font-black text-slate-800 mt-1">--:--</p></div>
                            </div>
                        </div>
                        <div class="rounded-xl border border-purple-100 bg-purple-50/40 p-4">
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-xs font-black text-purple-700 uppercase tracking-wider">HT Panel (VCB)</p>
                                <span id="vcb_time_status" class="text-[10px] font-bold rounded-full bg-slate-100 text-slate-500 px-2 py-1">WAITING</span>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">Start Time</p><p id="vcb_start_time" class="text-xl font-black text-slate-800 mt-1">--:--</p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">End / Latest</p><p id="vcb_end_time" class="text-xl font-black text-slate-800 mt-1">--:--</p></div>
                            </div>
                        </div>
                        <div class="rounded-xl border border-orange-100 bg-orange-50/40 p-4">
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-xs font-black text-orange-700 uppercase tracking-wider">Transformer</p>
                                <span id="transformer_time_status" class="text-[10px] font-bold rounded-full bg-slate-100 text-slate-500 px-2 py-1">WAITING</span>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">Start Time</p><p id="transformer_start_time" class="text-xl font-black text-slate-800 mt-1">--:--</p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">End / Latest</p><p id="transformer_end_time" class="text-xl font-black text-slate-800 mt-1">--:--</p></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col h-[400px]">
                    <h3 class="text-sm font-black text-slate-600 uppercase tracking-widest mb-4 flex items-center gap-2 shrink-0">
                        <i class="fa-solid fa-chart-line text-blue-500"></i> Generation Curve (Today)
                    </h3>
                    <div class="relative w-full" style="height:320px;"><canvas id="powerChart"></canvas></div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <h3 class="text-sm font-black text-slate-600 uppercase tracking-widest border-b border-slate-100 pb-3 mb-5">Inverter Field Array <span class="text-[10px] lowercase text-slate-400 font-medium ml-1">(Active Strings & Power)</span></h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" id="inv_grid">
                        <div class="text-sm text-slate-400 italic text-center py-8 col-span-full">Waiting for telemetry data...</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- String Detail Modal -->
    <div id="stringModal" class="fixed inset-0 bg-slate-900 bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden">
            <div class="flex items-center justify-between p-5 border-b border-slate-100 bg-slate-50/50">
                <h3 class="text-lg font-black text-slate-800" id="stringModalTitle">String Details</h3>
                <button onclick="closeStringModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 hover:text-slate-700 transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="p-5 overflow-y-auto" id="stringModalBody">
                <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-8 gap-3" id="stringGrid"></div>
            </div>
        </div>
    </div>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const userRole = <?php echo json_encode($user['role'] ?? 'user'); ?>;
        const userPlantId = <?php echo json_encode($user['plant_id'] ?? ''); ?>;
        const currentPlant = urlParams.get('plant') || userPlantId || 'vinoba-velliyanai';
        const authToken = urlParams.get('token') || sessionStorage.getItem('vs_token') || '';
        const plantConfig = {
            'vinoba-velliyanai': { name: 'Vinoba Velliyanai', capacity: '2.0', location: 'Karur' },
            'makkalpower': { name: 'Makkal Power', capacity: '2.0', location: 'Karur' },
            'anushyam': { name: 'Anushyam Plant', capacity: '2.0', location: 'Karur' }
        };
        const cfg = plantConfig[currentPlant] || { name: currentPlant, capacity: '--', location: '--' };
        document.getElementById('pageTitle').textContent = cfg.name + ' - Home';
        document.getElementById('profileName').textContent = cfg.name;
        document.getElementById('profileCapacity').innerHTML = cfg.capacity + ' <span class="text-sm font-bold">MW</span>';
        document.getElementById('profileLocation').textContent = cfg.location;
        setInterval(() => { document.getElementById('clockDisplay').innerText = new Date().toLocaleTimeString('en-IN', {hour12: false}); }, 1000);
        fetch('sidebar.html', { cache: 'no-store' }).then(r => r.text()).then(html => {
            document.getElementById('sidebar-container').innerHTML = html;

            // Append token to sidebar nav links
            const _token = new URLSearchParams(window.location.search).get('token') || sessionStorage.getItem('vs_token') || '';
            let _plant = new URLSearchParams(window.location.search).get('plant') || '';
            if (!_plant) { try { const _u = JSON.parse(sessionStorage.getItem('vs_user')||'{}'); _plant = _u.plant_id || 'vinoba-velliyanai'; } catch(e) { _plant = 'vinoba-velliyanai'; } }
            document.querySelectorAll('#sidebarNav a').forEach(link => {
                let href = link.getAttribute('href');
                if (!href || href.indexOf('logout') !== -1) return;
                if (href.indexOf('?plant=') === -1) {
                    link.setAttribute('href', href + '?plant=' + encodeURIComponent(_plant) + '&token=' + encodeURIComponent(_token));
                } else if (href.indexOf('token=') === -1) {
                    link.setAttribute('href', href + '&token=' + encodeURIComponent(_token));
                }
            });
            const _pn = document.getElementById('sidebarPlantName');
            if (_pn) {
                const _names = {'vinoba-velliyanai':'Vinoba Velliyanai','makkalpower':'Makkal Power','anushyam':'Anushyam Plant'};
                _pn.textContent = _names[_plant] || _plant;
            }
            if (typeof initSidebar === 'function') initSidebar();
            const curPage = window.location.pathname.split('/').pop() || 'home.php';
            document.querySelectorAll('#sidebarNav a').forEach(link => {
                const dp = link.getAttribute('data-page');
                if (dp && (dp === curPage || dp.replace('.php','.html') === curPage)) {
                    link.classList.add('!bg-emerald-50', '!text-emerald-700', '!border-emerald-500',  'shadow-emerald-100');
                    const ic = link.querySelector('i');
                    if (ic) ic.classList.add('!text-emerald-600');
                }
            });
            const overlay = document.getElementById('overlay');
            const sidebar = document.getElementById('sidebar');
            document.getElementById('menuBtn')?.addEventListener('click', () => { sidebar?.classList.remove('-translate-x-full'); overlay?.classList.remove('hidden'); });
            document.getElementById('closeSidebarBtn')?.addEventListener('click', () => { sidebar?.classList.add('-translate-x-full'); overlay?.classList.add('hidden'); });
            overlay?.addEventListener('click', () => { sidebar?.classList.add('-translate-x-full'); overlay.classList.add('hidden'); });
        });
        const INV_COLORS = ['#3b82f6','#0ea5e9','#f59e0b','#ef4444','#8b5cf6','#10b981'];
        let powerChart;
        function initChart() {
            const ctx = document.getElementById('powerChart').getContext('2d');
            Chart.defaults.color = '#64748b';
            powerChart = new Chart(ctx, {
                type: 'line',
                data: { labels: [], datasets: [
                    { label: 'VCB Total (kW)', borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.1)', borderWidth: 3, fill: true, tension: 0.3, pointRadius: 3, pointHoverRadius: 6, pointBackgroundColor: '#8b5cf6', data: [] }
                ]},
                options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 12, usePointStyle: true, font: { weight: '600' } } } },
                    scales: { x: { grid: { color: '#f1f5f9', drawBorder: false }, ticks: { maxTicksLimit: 15, autoSkip: true, maxRotation: 0 } },
                        y: { beginAtZero: true, grid: { color: '#f1f5f9', drawBorder: false }, border: { dash: [4,4] } } }
                }
            });
        }
        initChart();
        const state = { vcbPower: 0, dailyEnergy: 0, inverters: {}, peakPower: 0, isRunning: false, runSeconds: 0 };
        const localDateKey = () => {
            const d = new Date();
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        };
        const operatingTimesKey = 'vs_operating_times_v5_' + currentPlant + '_' + localDateKey();
        let operatingTimes = {
            inverter: { start: '', end: '', active: false },
            vcb: { start: '', end: '', active: false },
            transformer: { start: '', end: '', active: false }
        };
        let wsInverterHistoryStart = '';
        let wsInverterHistoryEnd = '';
        try {
            const savedOperatingTimes = JSON.parse(localStorage.getItem(operatingTimesKey) || 'null');
            if (savedOperatingTimes) operatingTimes = { ...operatingTimes, ...savedOperatingTimes };
        } catch (e) {}

        function railwayTime(dateValue = new Date()) {
            const d = dateValue instanceof Date ? dateValue : new Date(dateValue);
            if (isNaN(d.getTime())) return railwayTime(new Date());
            return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        }
        function saveAndRenderOperatingTimes() {
            localStorage.setItem(operatingTimesKey, JSON.stringify(operatingTimes));
            ['inverter', 'vcb', 'transformer'].forEach(type => {
                const item = operatingTimes[type];
                document.getElementById(type + '_start_time').textContent = item.start || '--:--';
                document.getElementById(type + '_end_time').textContent = item.end || '--:--';
                const status = document.getElementById(type + '_time_status');
                status.textContent = item.active ? 'LIVE' : (item.start ? 'RECORDED' : 'WAITING');
                status.className = 'text-[10px] font-bold rounded-full px-2 py-1 ' +
                    (item.active ? 'bg-emerald-100 text-emerald-700' : (item.start ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500'));
            });
        }
        function recordOperatingTime(type, active, timestamp = new Date()) {
            if (!operatingTimes[type]) return;
            const time = railwayTime(timestamp);
            if (active) {
                if (!operatingTimes[type].start || time < operatingTimes[type].start) operatingTimes[type].start = time;
                if (!operatingTimes[type].end || time > operatingTimes[type].end) operatingTimes[type].end = time;
            }
            operatingTimes[type].active = active;
            saveAndRenderOperatingTimes();
        }
        function hydrateOperatingTimesFromRows(rows) {
            if (!Array.isArray(rows)) return;
            const activeSlots = { inverter: [], vcb: [], transformer: [] };
            rows.forEach(row => {
                const time = String(row.time_label || '').match(/^(\d{2}:\d{2})/);
                if (!time) return;
                const label = time[1];
                if ((Number(row.inv1_kw) || 0) > 0 || (Number(row.inv2_kw) || 0) > 0) activeSlots.inverter.push(label);
                if ((Number(row.vcb_kw) || 0) > 0) activeSlots.vcb.push(label);
            });
            Object.entries(activeSlots).forEach(([type, times]) => {
                if (!times.length) return;
                times.sort();
                const first = times[0];
                const last = times[times.length - 1];
                if (!operatingTimes[type].start || first < operatingTimes[type].start) operatingTimes[type].start = first;
                if (!operatingTimes[type].end || last > operatingTimes[type].end) operatingTimes[type].end = last;
            });
            saveAndRenderOperatingTimes();
        }
        function hydrateOperatingTimesFromMeta(metaTimes) {
            if (!metaTimes || typeof metaTimes !== 'object') return;
            // Inverter timing is intentionally excluded here: it comes only from
            // the per-inverter WebSocket history and live telemetry.
            ['vcb'].forEach(type => {
                const source = metaTimes[type];
                if (!source) return;
                if (source.start && (!operatingTimes[type].start || source.start < operatingTimes[type].start)) {
                    operatingTimes[type].start = source.start;
                }
                if (source.end && (!operatingTimes[type].end || source.end > operatingTimes[type].end)) {
                    operatingTimes[type].end = source.end;
                }
            });
            saveAndRenderOperatingTimes();
        }
        function hydrateInverterTimesFromWS(d) {
            const readings = d.data || d.records || d.results || d.values || [];
            if (!Array.isArray(readings)) return;
            const activeTimes = [];
            readings.forEach(reading => {
                const values = reading.values || reading.data || reading;
                let power = 0;
                if (values && typeof values === 'object') {
                    for (const key in values) {
                        const lower = key.toLowerCase();
                        if (/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(lower) && !/reactive|apparent|3.phase/.test(lower)) {
                            power = Number(values[key]) || 0;
                            break;
                        }
                    }
                }
                if (power <= 0) return;
                const rawTime = String(reading.timestamp || reading.time || reading.recorded_at || reading.datetime || '');
                const directTime = rawTime.match(/(?:T|\s|^)(\d{1,2}):(\d{2})/);
                if (directTime) {
                    activeTimes.push(String(Number(directTime[1])).padStart(2, '0') + ':' + directTime[2]);
                    return;
                }
                const parsedTime = new Date(rawTime);
                if (!isNaN(parsedTime.getTime())) activeTimes.push(railwayTime(parsedTime));
            });
            if (!activeTimes.length) return;
            activeTimes.sort();
            const first = activeTimes[0];
            const last = activeTimes[activeTimes.length - 1];
            if (!wsInverterHistoryStart || first < wsInverterHistoryStart) wsInverterHistoryStart = first;
            if (!wsInverterHistoryEnd || last > wsInverterHistoryEnd) wsInverterHistoryEnd = last;
            // WebSocket history is the exact source; replace rounded API buckets.
            operatingTimes.inverter.start = wsInverterHistoryStart;
            operatingTimes.inverter.end = wsInverterHistoryEnd;
            saveAndRenderOperatingTimes();
        }
        saveAndRenderOperatingTimes();
        let lastChartPush = 0;
        let chartHasData = false;
        let chartLoadedFromApi = false;
        function setInd(id, active) { const el = document.getElementById(id); if(el) el.className = active ? 'w-3 h-3 rounded-full bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,0.7)]' : 'w-3 h-3 rounded-full bg-slate-300'; }
        function getTotalPower() {
            const invTotal = Object.values(state.inverters).reduce((s, i) => s + i.power, 0);
            return state.vcbPower > 0 ? state.vcbPower : invTotal;
        }
        function pushChartPoint() {
            const now = Date.now();
            if (chartHasData && now - lastChartPush < 30000) return; // 30 second intervals
            const dt = new Date();
            const curHour = dt.getHours();
            if (curHour < 5 || curHour > 19) return; // Only show solar hours 05-19
            lastChartPush = now; chartHasData = true;
            // Use hourly label for consistency with historical data
            const hourLabel = String(curHour).padStart(2,'0') + ':00';
            const totalPower = getTotalPower();
            // Find if this hour already exists in the chart
            const idx = powerChart.data.labels.indexOf(hourLabel);
            if (idx >= 0) {
                // Update existing hour point with latest live value
                powerChart.data.datasets[0].data[idx] = totalPower;
            } else {
                // New hour - add it in order
                powerChart.data.labels.push(hourLabel);
                powerChart.data.datasets[0].data.push(totalPower);
                // Sort labels and data together to maintain hourly order
                const combined = powerChart.data.labels.map((l, i) => ({label: l, val: powerChart.data.datasets[0].data[i]}));
                combined.sort((a, b) => a.label.localeCompare(b.label));
                powerChart.data.labels = combined.map(c => c.label);
                powerChart.data.datasets[0].data = combined.map(c => c.val);
            }
            powerChart.update('none');
        }
        function buildStringDots(inv) {
            if (!inv.strings || !inv.strings.length) return '';
            return `<div class="grid grid-cols-4 sm:grid-cols-8 gap-1 mt-3 mb-3">${inv.strings.map(s =>
                `<div class="w-3 h-3 rounded-full ${s.active ? 'bg-emerald-500' : 'bg-red-400'}" title="String ${s.n}: ${s.curr.toFixed(1)}A / ${s.volt.toFixed(1)}V"></div>`
            ).join('')}</div>`;
        }
        function openStringModal(invName) {
            const inv = state.inverters[invName];
            if (!inv || !inv.strings) return;
            document.getElementById('stringModalTitle').textContent = invName + ' - String Details';
            const grid = document.getElementById('stringGrid');
            grid.innerHTML = inv.strings.map(s => {
                const ok = s.active;
                return `<div class="border ${ok ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50'} rounded-lg p-2 text-center">
                    <p class="text-[10px] font-bold ${ok ? 'text-emerald-700' : 'text-red-700'} uppercase tracking-wider">String ${s.n}</p>
                    <p class="mt-1 text-sm font-black ${ok ? 'text-slate-800' : 'text-red-700'}">${s.curr.toFixed(1)} <span class="text-[10px] text-slate-500">A</span></p>
                    <p class="text-[10px] font-medium text-slate-500">${s.volt.toFixed(1)} V</p>
                </div>`;
            }).join('');
            document.getElementById('stringModal').classList.remove('hidden');
        }
        function closeStringModal() {
            document.getElementById('stringModal').classList.add('hidden');
        }
        document.getElementById('stringModal').addEventListener('click', function(e) {
            if (e.target === this) closeStringModal();
        });
        const PLANT_GREEN_THRESHOLD = {
            'vinoba-velliyanai': 22,
            'makkalpower': 22,
            'anushyam': 22
        };
        function updateInvGrid() {
            const grid = document.getElementById('inv_grid');
            const keys = Object.keys(state.inverters).sort((a,b) => (parseInt(a.replace(/\D/g,''))||0) - (parseInt(b.replace(/\D/g,''))||0));
            if (!keys.length) return;
            const plantThreshold = PLANT_GREEN_THRESHOLD[currentPlant] || null;
            grid.innerHTML = keys.map(k => {
                const inv = state.inverters[k];
                const hasData = inv.total > 0;
                const greenThreshold = plantThreshold !== null ? Math.min(plantThreshold, inv.total) : Math.ceil(inv.total * 0.7);
                const isGreen = hasData && inv.active >= greenThreshold;
                const isYellow = hasData && inv.active > 0 && inv.active < greenThreshold;
                const isRed = hasData && inv.active === 0;
                let cardBg, statusBadge, statusDot;
                if (isGreen) {
                    cardBg = 'bg-emerald-50/40 border-emerald-200';
                    statusBadge = 'bg-emerald-100 text-emerald-800 border-emerald-200';
                    statusDot = 'bg-emerald-500';
                } else if (isRed) {
                    cardBg = 'bg-red-50 border-red-200';
                    statusBadge = 'bg-red-100 text-red-800 border-red-200';
                    statusDot = 'bg-red-500 animate-pulse';
                } else if (isYellow) {
                    cardBg = 'bg-amber-50 border-amber-200';
                    statusBadge = 'bg-amber-100 text-amber-800 border-amber-200';
                    statusDot = 'bg-amber-500';
                } else {
                    cardBg = 'bg-white border-slate-100';
                    statusBadge = 'bg-slate-100 text-slate-600 border-slate-200';
                    statusDot = 'bg-slate-400';
                }
                const statusText = hasData ? `${inv.active}/${inv.total} Strings` : 'WAITING';
                return `<div class="border shadow-sm rounded-xl p-5 ${cardBg} hover:shadow-md transition relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1 ${isGreen ? 'bg-emerald-500' : (isRed ? 'bg-red-500' : (isYellow ? 'bg-amber-500' : 'bg-slate-300'))}"></div>
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">${k}</p>
                            <p class="font-black text-slate-800 text-2xl mt-1">${inv.power.toFixed(1)} <span class="text-sm font-bold text-blue-600">kW</span></p>
                        </div>
                        <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[10px] font-bold uppercase tracking-wide ${statusBadge}">
                            <div class="w-2 h-2 rounded-full ${statusDot}"></div>
                            ${statusText}
                        </div>
                    </div>
                </div>`;
            }).join('');
        }
        function updateDash() {
            const final = getTotalPower();
            document.getElementById('vcb_active').innerHTML = final.toFixed(2) + ' <span class="text-sm font-bold text-blue-600">kW</span>';
            document.getElementById('vcb_etoday').innerHTML = state.dailyEnergy.toFixed(2) + ' <span class="text-sm font-bold text-purple-600">kWh</span>';
            if (final > state.peakPower) state.peakPower = final;
            document.getElementById('vcb_peak').textContent = state.peakPower.toFixed(2);
            updateInvGrid();
            pushChartPoint();
        }
        let wsRef = null;
        const requestedInverterHistory = new Set();
        function connectWS() {
            const ws = new WebSocket("wss://vinobasolar.scadahub.in:5001");
            wsRef = ws;
            ws.onopen = function() {
                document.getElementById('refreshPulse').className = 'w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]';
                ws.send(JSON.stringify({ type: "subscribe", unit_id: currentPlant }));
                // Request today's VCB daily data for the generation curve
                const today = localDateKey();
                ws.send(JSON.stringify({ type: "get_daily_data", unit_id: currentPlant, device: "VCB", date: today }));
            };
            ws.onmessage = function(e) {
                try {
                    const d = JSON.parse(e.data);
                    // Handle daily_data_result response for chart population
                    if (d.type === 'daily_data_result') {
                        console.log('WS daily_data_result:', d);
                        const historyRows = d.data || d.records || d.results || d.values || [];
                        const sampleRow = Array.isArray(historyRows) && historyRows.length ? historyRows[0] : {};
                        const sampleValues = sampleRow.values || sampleRow.data || sampleRow;
                        const historyDevice = String(d.device || d.task || d.pageName || sampleRow.device || '').toLowerCase();
                        const sampleKeys = sampleValues && typeof sampleValues === 'object' ? Object.keys(sampleValues) : [];
                        const looksLikeVcb = historyDevice.includes('vcb') ||
                            sampleKeys.some(key => /3.*phase.*active.*power|phase-n voltage|v12|active total export/i.test(key));
                        const looksLikeInverter = historyDevice.includes('inv') ||
                            (!looksLikeVcb && sampleKeys.some(key => {
                                const lower = key.toLowerCase();
                                return (/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(lower) && !/reactive|apparent|3.phase/.test(lower));
                            }));
                        if (looksLikeInverter) hydrateInverterTimesFromWS(d);
                        else loadChartFromWSData(d);
                        return;
                    }
                    if (d.type === 'device_list') {
                        console.log('WS device_list:', d);
                        return;
                    }
                    if (d.unit_id !== currentPlant) return;
                    console.log('WS device:', d.device, '| keys:', d.values ? Object.keys(d.values) : 'no values');
                    const keys = d.values ? Object.keys(d.values) : [];
                    const taskStr = d.task ? d.task.toString().toLowerCase() : '';
                    const deviceStr = d.device ? d.device.toString().toLowerCase() : '';
                    const isVcbMessage = taskStr === 'vcb' || deviceStr.includes('vcb');
                    const isTransformerMessage = taskStr === 'transformer' || deviceStr.includes('transformer');

                    if (isVcbMessage && d.values && d.values["3 Phase Active Power"] !== undefined) {
                        state.vcbPower = parseFloat(d.values["3 Phase Active Power"]) || 0;
                        recordOperatingTime('vcb', state.vcbPower > 0);
                    }
                    if (d.virtualTags && d.virtualTags["vcb-today"] !== undefined) state.dailyEnergy = parseFloat(d.virtualTags["vcb-today"].value) || 0;
                    if (d.values && d.values["raw data"] !== undefined) document.getElementById('wmos_rad').textContent = d.values["raw data"];
                    if (d.values && d.values["pannel temperature"] !== undefined) document.getElementById('wmos_ptemp').textContent = d.values["pannel temperature"];
                    if (d.values && d.values["windspeed"] !== undefined) document.getElementById('wmos_wind').textContent = d.values["windspeed"];
                    // Detect inverter message: has power or has numbered current keys
                    const hasInvPower = keys.some(pk => {
                        const pkl = pk.toLowerCase();
                        return (/power/.test(pkl) && /active|ac/.test(pkl) && !/reactive|apparent/.test(pkl));
                    });
                    const hasNumberedCurrents = keys.some(k => /\d/.test(k) && /curr|current|amp/i.test(k) && !/phase|3.phase|reactive|apparent|freq|temp/i.test(k.toLowerCase()));
                    if (!isVcbMessage && !isTransformerMessage && (hasInvPower || hasNumberedCurrents || taskStr === 'inverter')) {
                        console.log('=== HOME INV MSG === device:', d.device, 'task:', d.task, 'keys:', keys);
                        const usedKeys = new Set();
                        const byNum = {};
                        for (const k of keys) {
                            const num = k.match(/(\d+)/);
                            if (!num) continue;
                            const n = parseInt(num[1]);
                            if (!byNum[n]) byNum[n] = [];
                            byNum[n].push(k);
                        }
                        const strings = [];
                        let a=0, t=0;
                        for (const n in byNum) {
                            const group = byNum[n];
                            let currKey = '', voltKey = '';
                            for (const k of group) {
                                const kl = k.toLowerCase();
                                if (usedKeys.has(k)) continue;
                                if (/phase|phasa|ph_|r.phase|y.phase|b.phase|a.phase|c.phase|3.phase|three.phase/i.test(kl)) continue;
                                if (/inverter.*curr|inv.*curr|total.*curr|grid.*curr|load.*curr|reactive.*curr|mppt.*curr|dc.*curr/i.test(kl)) continue;
                                if (/freq|temperature|temp|ambient|cosphi|pf.*_/i.test(kl)) continue;
                                if (!currKey && /\b(curr|current|amp|i)\b/i.test(kl) && !/\b(volt|voltage|temp|freq)\b/i.test(kl)) {
                                    currKey = k;
                                }
                                if (!voltKey && /\b(volt|voltage|v)\b/i.test(kl) && !/\b(curr|current|amp|i)\b/i.test(kl)) {
                                    voltKey = k;
                                }
                            }
                            if (currKey) {
                                usedKeys.add(currKey);
                                if (voltKey) usedKeys.add(voltKey);
                                const curr = parseFloat(d.values[currKey]) || 0;
                                const volt = voltKey ? (parseFloat(d.values[voltKey]) || 0) : 0;
                                strings.push({ n: parseInt(n), curr, volt, active: curr > 0.5 });
                                t++;
                                if (curr > 0.5) a++;
                                console.log('  HOME FOUND string', n, 'currKey:', currKey, '=', curr, 'voltKey:', voltKey || 'none', '=', volt);
                            }
                        }
                        strings.sort((x,y) => x.n - y.n);
                        const totalGen = parseFloat(d.values["total generation"]) || 0;
                        const devName = d.device || "Unknown Inverter";
                        if (!requestedInverterHistory.has(devName) && wsRef && wsRef.readyState === WebSocket.OPEN) {
                            requestedInverterHistory.add(devName);
                            wsRef.send(JSON.stringify({
                                type: 'get_daily_data',
                                unit_id: currentPlant,
                                device: devName,
                                date: localDateKey()
                            }));
                        }
                        const existing = state.inverters[devName] || {};
                        let pwr = 0;
                        let powerFound = false;
                        for (const pk in d.values) {
                            const pkl = pk.toLowerCase();
                            if (/active.*power|ac.*power|power.*ac|a\.c\..*power/i.test(pkl) && !/reactive|apparent|3.phase/i.test(pkl)) {
                                pwr = parseFloat(d.values[pk]) || 0;
                                powerFound = true;
                                break;
                            }
                        }
                        state.inverters[devName] = {
                            active: a || existing.active || 0,
                            total: t || existing.total || 0,
                            power: powerFound ? pwr : (existing.power || 0),
                            totalGen: totalGen || existing.totalGen || 0,
                            strings: strings.length ? strings : (existing.strings || [])
                        };
                        const combinedInverterPower = Object.values(state.inverters).reduce((sum, inv) => sum + (inv.power || 0), 0);
                        recordOperatingTime('inverter', combinedInverterPower > 0);
                        console.log('=== HOME STORED', devName, 'strings:', strings.length, 'active:', a, 'total:', t, 'power:', pwr);
                    }
                    updateDash();
                } catch(err) { console.error('WS err', err); }
            };
            ws.onclose = function() { document.getElementById('refreshPulse').className = 'w-2.5 h-2.5 bg-red-500 rounded-full'; setTimeout(connectWS, 5000); };
        }
        function buildHourlyLabels() {
            // Generate all hourly labels from 05:00 to current hour (max 19:00)
            const now = new Date();
            const curHour = now.getHours();
            const endHour = Math.min(curHour, 19);
            const labels = [];
            for (let h = 5; h <= endHour; h++) {
                labels.push(String(h).padStart(2,'0') + ':00');
            }
            return labels;
        }
        function loadChartFromWSData(d) {
            if (!powerChart || chartLoadedFromApi) return;
            // Parse daily_data_result - format may vary, try common structures
            let readings = d.data || d.records || d.results || d.values || [];
            if (!Array.isArray(readings) || readings.length === 0) {
                console.log('WS daily data empty, falling back to API chart data');
                fetchChartData();
                return;
            }
            const hourlyMap = {}; // group by hour, take last value per hour
            const positiveVcbTimes = [];
            readings.forEach(r => {
                // Extract timestamp from various possible fields
                let ts = r.timestamp || r.time || r.recorded_at || r.date || r.datetime || '';
                let vals = r.values || r.data || r;
                // Extract VCB power from various possible key names
                let pwr = 0;
                if (typeof vals === 'object' && !Array.isArray(vals)) {
                    for (const k in vals) {
                        const kl = k.toLowerCase();
                        if (/active.*power|3.*phase.*active|power.*active/i.test(kl) && !/reactive|apparent/i.test(kl)) {
                            pwr = parseFloat(vals[k]) || 0; break;
                        }
                    }
                    if (!pwr) {
                        // Fallback: look for any power key
                        for (const k in vals) {
                            const kl = k.toLowerCase();
                            if (/power/i.test(kl) && !/reactive|apparent|export|import/i.test(kl)) {
                                pwr = parseFloat(vals[k]) || 0; break;
                            }
                        }
                    }
                } else if (typeof vals === 'number') {
                    pwr = vals;
                }
                // Extract hour label
                if (ts) {
                    const directTime = String(ts).match(/(?:T|\s|^)(\d{1,2}):(\d{2})/);
                    const dt = new Date(ts);
                    let hourKey = '';
                    if (directTime) hourKey = String(Number(directTime[1])).padStart(2, '0') + ':00';
                    else if (!isNaN(dt.getTime())) hourKey = String(dt.getHours()).padStart(2,'0') + ':00';
                    if (hourKey) {
                        hourlyMap[hourKey] = pwr;
                        if (pwr > 0) {
                            const historyDate = new Date();
                            historyDate.setHours(Number(hourKey.slice(0, 2)), Number(directTime ? directTime[2] : dt.getMinutes()), 0, 0);
                            positiveVcbTimes.push(historyDate);
                        }
                    }
                }
            });
            if (positiveVcbTimes.length) {
                positiveVcbTimes.sort((a, b) => a - b);
                const firstTime = railwayTime(positiveVcbTimes[0]);
                const latestTime = railwayTime(positiveVcbTimes[positiveVcbTimes.length - 1]);
                if (!operatingTimes.vcb.start || firstTime < operatingTimes.vcb.start) operatingTimes.vcb.start = firstTime;
                if (!operatingTimes.vcb.end || latestTime > operatingTimes.vcb.end) operatingTimes.vcb.end = latestTime;
                saveAndRenderOperatingTimes();
            }
            // Build ALL hourly data points from 05:00 to current hour, fill missing with 0
            const allLabels = buildHourlyLabels();
            if (allLabels.length > 1) {
                const allData = allLabels.map(l => hourlyMap[l] || 0);
                powerChart.data.labels = allLabels;
                powerChart.data.datasets[0].data = allData;
                chartHasData = true;
                chartLoadedFromApi = true;
                powerChart.update('none');
                console.log('Chart loaded from WS daily data:', allLabels.length, 'hourly points');
            } else {
                console.log('WS daily data insufficient, falling back to API chart data');
                fetchChartData();
            }
        }
        function fetchChartData() {
            fetch(`api_reports.php?tab=inv_vcb&type=daily&date=${localDateKey()}&plant=${currentPlant}&chart=1&token=${authToken}`)
                .then(r => r.json()).then(res => {
                    if (!res.success || !res.data.length) return;
                    // Populate chart with today's hourly historical data once
                    if (powerChart && !chartLoadedFromApi) {
                        const labels = [];
                        const data = [];
                        res.data.forEach(row => {
                            const val = (row.vcb_kw > 0) ? row.vcb_kw : ((row.inv1_kw || 0) + (row.inv2_kw || 0));
                            labels.push(row.time_label);
                            data.push(val);
                        });
                        if (labels.length > 1) {
                            powerChart.data.labels = labels;
                            powerChart.data.datasets[0].data = data;
                            chartHasData = true;
                            chartLoadedFromApi = true;
                            powerChart.update('none');
                        }
                    }
                }).catch(() => {});
        }
        function fetchApiFallback() {
            fetch(`api_reports.php?tab=inv_vcb&type=daily&date=${localDateKey()}&plant=${currentPlant}&token=${authToken}`)
                .then(r => r.json()).then(res => {
                    if (!res.success || !res.data.length) return;
                    hydrateOperatingTimesFromMeta(res.meta && res.meta.operating_times);
                    const now = new Date();
                    const currentMinutes = now.getHours() * 60 + now.getMinutes();
                    const elapsedRows = res.data.filter(row => {
                        const match = String(row.time_label || '').match(/^(\d{1,2}):(\d{2})/);
                        return match && Number(match[1]) * 60 + Number(match[2]) <= currentMinutes;
                    });
                    const latest = elapsedRows[elapsedRows.length - 1] || res.data[0];
                    state.vcbPower = latest.vcb_kw || 0;
                    state.dailyEnergy = Math.max(...elapsedRows.map(row => (Number(row.inv1_kwh) || 0) + (Number(row.inv2_kwh) || 0)), 0);
                    document.getElementById('vcb_etoday').innerHTML = state.dailyEnergy.toFixed(2) + ' <span class="text-sm font-bold text-purple-600">kWh</span>';
                    document.getElementById('refreshPulse').className = 'w-2.5 h-2.5 bg-blue-500 rounded-full animate-pulse';
                }).catch(() => {});
        }
        // API fallback for stats
        fetchApiFallback();
        setInterval(fetchApiFallback, 30000);
        // Connect WS first - it will request daily VCB data and load chart
        connectWS();
        // Fallback: if WS hasn't loaded chart data in 8 seconds, use API
        setTimeout(() => { if (!chartLoadedFromApi) { console.log('WS chart timeout, using API fallback'); fetchChartData(); } }, 8000);
    </script>
</body>
</html>
