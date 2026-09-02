<?php
require 'check_auth.php';
require 'config.php';

$plants = [];
try {
    $pRes = $conn->query("SELECT id, name, capacity, location FROM plants ORDER BY name ASC");
    if ($pRes) {
        while ($row = $pRes->fetch_assoc()) $plants[] = $row;
    }
} catch (Exception $e) {}

if (empty($plants)) {
    $plants = [
        ['id'=>'vinoba-velliyanai','name'=>'Vinoba Velliyanai','capacity'=>2.0,'location'=>'Karur'],
        ['id'=>'makkalpower','name'=>'Makkal Power','capacity'=>2.0,'location'=>'Karur'],
        ['id'=>'anushyam','name'=>'Anushyam Plant','capacity'=>2.0,'location'=>'Karur']
    ];
}

$userPlantId = $user['plant_id'] ?? '';
if (isset($_GET['plant'])) {
    $selPlant = $conn->real_escape_string($_GET['plant']);
} elseif ($user['role'] !== 'admin' && $userPlantId) {
    $selPlant = $userPlantId;
} else {
    $selPlant = $plants[0]['id'] ?? '';
}
$pInfo = ['name'=>'Unknown','capacity'=>0,'location'=>'Unknown'];
foreach ($plants as $p) {
    if ($p['id'] === $selPlant) { $pInfo = $p; break; }
}
if (empty($pInfo) && !empty($plants)) $pInfo = $plants[0];

// WebSocket endpoint - switch between local dev and live reverse proxy
$wsUrl = 'wss://vinobasolar.scadahub.in:5001'; // live
// $wsUrl = 'ws://161.97.87.75:5000';          // local / direct backend
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports   Vinoba Solar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="sidebar-control.js?v=3" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .report-table { table-layout: fixed; min-width: 1200px; width: 100%; border-collapse: collapse; }
        .table-hscroll { overflow-x: auto; overflow-y: visible; }
        .report-table th, .report-table td { padding: 4px 2px; text-align: center; overflow: hidden; text-overflow: ellipsis; }
        .report-table td { font-size: 11px; color: #334155; border-bottom: 1px solid #f1f5f9; font-variant-numeric: tabular-nums; }
        .report-table th { color: #475569; font-weight: 600; font-size: 9px; text-transform: uppercase; letter-spacing: 0; border-bottom: 2px solid #e2e8f0; line-height: 1.1; }
        .report-table tbody tr { transition: background-color 0.2s ease; background-color: #ffffff; }
        .report-table tbody tr:hover { background-color: #f8fafc !important; }
        .table-hscroll::-webkit-scrollbar { height: 10px; }
        .table-hscroll::-webkit-scrollbar-track { background: #f8fafc; border-radius: 5px; }
        .table-hscroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 5px; }
        .table-hscroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        /* PDF Mode styles to prevent horizontal clipping */
        .pdf-mode .table-hscroll {
            overflow: visible !important;
            width: 100% !important;
            max-width: none !important;
        }
        .pdf-mode .report-table {
            table-layout: fixed !important;
            width: 100% !important;
        }
        .pdf-mode {
            background: #ffffff !important;
            padding: 15px !important;
        }
    </style>
</head>
<?php if (!empty($dbError)) { echo '<div style="background:#fee2e2;color:#991b1b;padding:12px;text-align:center;font-weight:bold;font-family:sans-serif;">'.htmlspecialchars($dbError).'</div>'; } ?>
<body class="h-full bg-slate-50 text-gray-800 font-sans">
    <div class="min-h-screen flex relative">
        <div id="overlay" class="fixed inset-0 bg-slate-900 bg-opacity-40 hidden z-30 md:hidden transition-opacity"></div>
        <div id="sidebar-container"></div>
        <main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
        <header class="bg-white border-b border-gray-200 p-4 sm:px-6 flex justify-between items-center shadow-sm sticky top-0 z-20">
            <div class="flex items-center gap-3 sm:gap-4">
                <button id="menuBtn" class="md:hidden text-green-700 text-2xl focus:outline-none">&#9776;</button>
                <div>
                    <h2 class="text-lg sm:text-xl font-bold text-gray-800">System Reports</h2>
                    <p class="text-xs text-gray-500 hidden sm:block">Generate, view, and export plant data</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="exportToPDF()" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-3 sm:px-4 rounded-lg shadow-sm transition-colors flex items-center gap-2 text-sm">
                    <i class="fa-solid fa-file-pdf"></i><span class="hidden sm:inline">Export PDF</span>
                </button>
                <button onclick="downloadJson()" id="dlBtn" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-3 sm:px-4 rounded-lg shadow-sm transition-colors flex items-center gap-2 text-sm opacity-50 cursor-not-allowed" disabled>
                    <i class="fa-solid fa-download"></i><span class="hidden sm:inline">JSON</span>
                </button>
            </div>
        </header>

        <div class="p-4 sm:p-6 w-full flex flex-col gap-6 max-w-[1600px] mx-auto">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5 flex flex-col lg:flex-row gap-4 justify-between items-start lg:items-center">
                <div class="flex flex-wrap gap-2" id="tab-container">
                    <button class="px-4 py-2 text-sm font-bold rounded-lg bg-green-50 text-green-700 border border-green-200 transition">Inverter & VCB</button>
                </div>
                <div class="flex items-center gap-2 w-full lg:w-auto flex-wrap">
                    <select id="plantSelect" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 outline-none bg-gray-50 font-medium cursor-pointer"></select>
                    <select id="reportType" onchange="toggleInputs()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 outline-none bg-gray-50 font-medium cursor-pointer">
                        <option value="daily">Daily</option>
                        <option value="monthly">Monthly</option>
                    </select>
                    <input type="date" id="dateSelect" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 outline-none bg-gray-50 font-medium">
                    <input type="month" id="monthSelect" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 outline-none bg-gray-50 font-medium hidden">
                    <button onclick="generateReportData(); startAutoRefresh();" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition flex items-center gap-2 text-sm">
                        <i class="fa-solid fa-eye"></i> View
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm flex-1 flex flex-col">
                <div id="printableReport" class="p-5 bg-white w-full">
                    <div class="border-b-2 border-green-600 pb-4 mb-6">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <h1 class="text-2xl font-black text-gray-900 tracking-tight"><?php echo htmlspecialchars(strtoupper($pInfo['name'] ?? 'SOLAR ENERGY')); ?></h1>
                                <h2 id="reportMainTitle" class="text-lg font-bold text-green-700 mt-1">Inverter & VCB MFM Report</h2>
                            </div>
                        </div>
                        <div class="report-info-grid grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm mt-4 font-medium text-gray-700 bg-gray-50 p-3 rounded-lg border border-gray-100">
                            <div><span class="text-gray-500 uppercase text-xs font-bold block">Plant Location</span><?php echo htmlspecialchars($pInfo['location'] ?? 'Unknown'); ?></div>
                            <div><span class="text-gray-500 uppercase text-xs font-bold block">Plant Capacity</span><?php echo ($pInfo['capacity'] ?? 0) . ' MW'; ?></div>
                            <div><span class="text-gray-500 uppercase text-xs font-bold block" id="reportDateLabel">Report Date</span><span id="displayDate" class="font-bold text-gray-900">--</span><br><span id="liveStatus" class="text-xs font-bold text-emerald-600 mt-0.5 inline-flex items-center gap-1"><span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>Auto-refresh ON</span></div>
                        </div>
                    </div>
                    <div class="table-hscroll w-full">
                        <table class="w-full report-table border-collapse">
                            <thead></thead>
                            <tbody id="reportTableBody">
                                <tr><td colspan="30" class="py-10 text-center text-gray-500"><div class="flex flex-col items-center justify-center"><div class="w-8 h-8 border-3 border-gray-200 border-t-blue-600 rounded-full animate-spin mb-2"></div>Loading live data...</div></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-12 pt-8 flex justify-between text-sm font-bold text-gray-800 border-t border-gray-200">
                        <div class="text-center w-40"><div class="border-b border-gray-400 mb-2 h-8"></div>Operator Signature</div>
                        <div class="text-center w-40"><div class="border-b border-gray-400 mb-2 h-8"></div>Site Engineer</div>
                        <div class="text-center w-40"><div class="border-b border-gray-400 mb-2 h-8"></div>Plant Manager</div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    </div>

    <script>
        if(!sessionStorage.getItem('userRole') && !new URLSearchParams(window.location.search).get('token')) window.location.replace('index.php');
        const userRole = <?php echo json_encode($user['role'] ?? 'user'); ?>;
        const userPlant = <?php echo json_encode($user['plant_id'] ?? ''); ?>;

        const plantSelect = document.getElementById('plantSelect');
        const plantOptions = <?php echo json_encode($plants); ?>;
        const plantMeta = {};
        plantOptions.forEach(p => { plantMeta[p.id] = p; });
        if (userRole === 'admin') {
            const allOpt = document.createElement('option'); allOpt.value = 'all'; allOpt.textContent = 'All Plants'; plantSelect.appendChild(allOpt);
        }
        let hasUserPlant = false;
        plantOptions.forEach(opt => {
            if (userRole === 'admin' || opt.id === userPlant) {
                const option = document.createElement('option'); option.value = opt.id; option.textContent = opt.name; plantSelect.appendChild(option);
                if (opt.id === userPlant) hasUserPlant = true;
            }
        });
        if (userRole !== 'admin' && !hasUserPlant && userPlant) {
            const option = document.createElement('option'); option.value = userPlant; option.textContent = userPlant; plantSelect.appendChild(option);
        }
        plantSelect.value = <?php echo json_encode($selPlant); ?>;
        if (userRole !== 'admin') { plantSelect.style.display = 'none'; }
        plantSelect.addEventListener('change', function() {
            const pid = this.value;
            if (plantMeta[pid]) {
                document.querySelector('#printableReport h1').innerText = plantMeta[pid].name.toUpperCase() + ' SOLAR ENERGY';
                const infoBoxes = document.querySelectorAll('#printableReport .report-info-grid > div');
                if (infoBoxes[0]) infoBoxes[0].innerHTML = '<span class="text-gray-500 uppercase text-xs font-bold block">Plant Location</span>' + (plantMeta[pid].location || 'Unknown');
                if (infoBoxes[1]) infoBoxes[1].innerHTML = '<span class="text-gray-500 uppercase text-xs font-bold block">Plant Capacity</span>' + (plantMeta[pid].capacity || 0) + ' MW';
            }
        });

        let currentReportTab = 'inv_vcb';
        let lastReportData = null;
        let refreshInterval = null;
        let ws = null;
        let wsConnected = false;
        let pendingReportRequest = false;
        let wsReportTimeout = null;
        const token = new URLSearchParams(window.location.search).get('token') || sessionStorage.getItem('vs_token') || '';
        const dateInput = document.getElementById('dateSelect');
        const monthInput = document.getElementById('monthSelect');
        dateInput.value = new Date().toISOString().split('T')[0];
        monthInput.value = new Date().toISOString().slice(0, 7);

        function loadSidebar() {
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
                        link.classList.add('!bg-slate-100', '!text-emerald-700', '!border-emerald-500');
                    }
                });
                document.getElementById('menuBtn')?.addEventListener('click', () => { document.getElementById('sidebar')?.classList.remove('-translate-x-full'); document.getElementById('overlay')?.classList.remove('hidden'); });
                document.getElementById('closeSidebarBtn')?.addEventListener('click', () => { document.getElementById('sidebar')?.classList.add('-translate-x-full'); document.getElementById('overlay')?.classList.add('hidden'); });
                document.getElementById('overlay')?.addEventListener('click', () => { document.getElementById('sidebar')?.classList.add('-translate-x-full'); document.getElementById('overlay')?.classList.add('hidden'); });
            });
        }

        function switchTab(tabName, btnElement) {
            currentReportTab = tabName;
            document.querySelectorAll('#tab-container button').forEach(btn => {
                btn.className = "px-4 py-2 text-sm font-semibold rounded-lg text-gray-600 hover:bg-gray-50 border border-transparent transition";
            });
            btnElement.className = "px-4 py-2 text-sm font-bold rounded-lg bg-green-50 text-green-700 border border-green-200 shadow-sm transition";
            const titles = { inv_vcb: 'Inverter & VCB MFM Report', wms_vcb: 'WMS & VCB Generation Report', smb: 'SMB Generation Report', eb: 'Main EB Generation Report', slot: 'Slot Generation Report' };
            document.getElementById('reportMainTitle').innerText = titles[tabName] || '';
            document.getElementById('reportTableBody').innerHTML = '<tr><td colspan="30" class="py-12 bg-white"><div class="flex flex-col items-center justify-center"><div class="w-10 h-10 border-4 border-gray-200 border-t-green-600 rounded-full animate-spin"></div><p class="mt-3 text-sm font-bold text-gray-600">Loading Data...</p></div></td></tr>';
            document.querySelector('.report-table thead').innerHTML = '';
            generateReportData();
            startAutoRefresh();
        }

        function startAutoRefresh() {
            if (refreshInterval) clearInterval(refreshInterval);
            refreshInterval = setInterval(() => {
                generateReportData();
            }, 30000);
        }

        function stopAutoRefresh() {
            if (refreshInterval) { clearInterval(refreshInterval); refreshInterval = null; }
        }

        function updateLiveStatus() {
            const now = new Date();
            const time = now.toLocaleTimeString('en-IN', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });
            document.getElementById('liveStatus').innerHTML = '<span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>Updated ' + time;
        }

        // Convert report times to 24-hour railway format (HH:mm).
        // Examples: 5:30 AM -> 05:30, 7:30 PM -> 19:30, 18:15:00 -> 18:15.
        function formatRailwayTime(value) {
            const raw = String(value ?? '').trim();
            if (!raw) return '';

            const match = raw.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)?$/i);
            if (!match) return raw;

            let hour = Number(match[1]);
            const minute = Number(match[2]);
            const meridiem = (match[3] || '').toUpperCase();

            if (meridiem) {
                if (hour < 1 || hour > 12 || minute > 59) return raw;
                if (meridiem === 'AM' && hour === 12) hour = 0;
                if (meridiem === 'PM' && hour !== 12) hour += 12;
            } else if (hour > 23 || minute > 59) {
                return raw;
            }

            return String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
        }

        function toggleInputs() {
            const type = document.getElementById('reportType').value;
            if (type === 'daily') { dateInput.classList.remove('hidden'); monthInput.classList.add('hidden'); document.getElementById('reportDateLabel').innerText = "Report Date"; }
            else { dateInput.classList.add('hidden'); monthInput.classList.remove('hidden'); document.getElementById('reportDateLabel').innerText = "Report Month"; }
        }

        const wsUrl = <?php echo json_encode($wsUrl); ?>;
        function connectReportWS() {
            if (ws && (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING)) return;
            try {
                ws = new WebSocket(wsUrl);
                ws.onopen = () => { wsConnected = true; console.log('Reports WS connected'); };
                ws.onmessage = (e) => {
                    try {
                        const d = JSON.parse(e.data);
                        console.log('Reports WS message:', d);
                        const reportTypes = ['report_data','generate_report','generate_report_result','report','report_result','report_generated','daily_data_result','monthly_data_result'];
                        if (reportTypes.includes(d.type) || d.columns || d.rows || d.pageName || d.reportData) {
                            handleWSReportResponse(d);
                        }
                    } catch(err) { console.error('Reports WS parse error', err); }
                };
                ws.onclose = () => { wsConnected = false; setTimeout(connectReportWS, 5000); };
                ws.onerror = (err) => { console.error('Reports WS error', err); wsConnected = false; };
            } catch(err) { console.error('WS connect failed', err); }
        }
        function requestWSReport(type, plant, date) {
            if (!ws || ws.readyState !== WebSocket.OPEN) { connectReportWS(); return false; }
            const pageName = type === 'daily' ? 'inverter&vcb-daily' : 'inverter&vcb-monthly';
            const payload = { type: 'generate_report', unit_id: plant, pageName: pageName, date: date };
            ws.send(JSON.stringify(payload));
            console.log('Sent WS report request:', payload);
            return true;
        }
        function handleWSReportResponse(d) {
            if (!pendingReportRequest) return;
            // Accept report_data type from WS server
            if (d.type !== 'report_data' && !d.columns && !d.rows) return;

            const columns = d.columns || [];
            const rows = d.rows || [];
            const type = document.getElementById('reportType').value;

            if (!rows.length) { console.warn('WS report: no rows returned'); return; }

            // Separate hidden and visible columns, preserving original indices
            const visibleColIndices = [];
            const allColNames = columns.map(c => c.name || '');

            // Find inverter columns (name like INV-1..INV-N)
            const invColIndices = [];
            const invNames = [];
            columns.forEach((col, idx) => {
                if (/^INV-\d+/i.test(col.name) && !col.isHidden) {
                    invColIndices.push(idx);
                    invNames.push(col.name);
                }
            });

            // Find INVERTER TOTAL column index
            const totalColIdx = columns.findIndex(c => /inverter.*total/i.test(c.name));
            // Find VCB column (HT Pannel / VCB)
            const vcbColIdx = columns.findIndex(c => /ht.pannel|vcb/i.test(c.name) && !c.isHidden);
            // Find Transformer Loss column
            const lossColIdx = columns.findIndex(c => /transformer.*loss|loss/i.test(c.name) && !c.isHidden);

            // Build normalized rows in the format renderReportData() expects
            const normalizedRows = rows.map(row => {
                const cells = row.cells || [];
                const nr = { time_label: type === 'daily' ? formatRailwayTime(row.time || '') : (row.time || '') };
                invColIndices.forEach((origIdx, i) => {
                    nr['inv' + (i+1) + '_kwh'] = parseFloat(cells[origIdx]) || 0;
                    nr['inv' + (i+1) + '_kw']  = 0; // daily generation, not instant power
                    nr['inv' + (i+1) + '_temp']= 0;
                });
                nr.inv_total_kwh = totalColIdx >= 0 ? (parseFloat(cells[totalColIdx]) || 0) : 0;
                nr.vcb_kwh  = vcbColIdx  >= 0 ? (parseFloat(cells[vcbColIdx])  || 0) : 0;
                nr.vcb_kw   = 0;
                nr.tx_loss  = lossColIdx >= 0 ? (parseFloat(cells[lossColIdx]) || 0) : 0;
                nr.ot = 0; nr.wt1 = 0; nr.wt2 = 0;
                return nr;
            });

            if (invNames.length === 0) {
                console.warn('No visible inverter columns found in WS response');
                return;
            }

            pendingReportRequest = false;
            if (wsReportTimeout) { clearTimeout(wsReportTimeout); wsReportTimeout = null; }
            lastReportData = { type: type, data: normalizedRows, meta: { inv_names: invNames } };
            renderReportData(type, normalizedRows, invNames);
        }

        // Keep the daily report on a fixed 15-minute solar-day schedule. WebSocket
        // values are retained in their matching slots and missing slots stay at 0.
        function normalizeDailyTimeRows(type, rows) {
            if (type !== 'daily' || !Array.isArray(rows) || rows.length === 0) return rows;

            const timeToMinutes = (label) => {
                const value = String(label || '').trim();
                const match = value.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)?$/i);
                if (!match) return null;

                let hour = Number(match[1]);
                const minute = Number(match[2]);
                const meridiem = (match[3] || '').toUpperCase();

                if (meridiem === 'AM' && hour === 12) hour = 0;
                if (meridiem === 'PM' && hour !== 12) hour += 12;
                if (hour > 23 || minute > 59) return null;
                return hour * 60 + minute;
            };

            const rowsByTime = new Map();
            rows.forEach(row => {
                const minutes = timeToMinutes(row.time_label);
                if (minutes !== null) rowsByTime.set(minutes, row);
            });

            const normalized = [];
            const startMinutes = 5 * 60 + 30;
            const endMinutes = 19 * 60 + 30;
            for (let minutes = startMinutes; minutes <= endMinutes; minutes += 15) {
                const hour = String(Math.floor(minutes / 60)).padStart(2, '0');
                const minute = String(minutes % 60).padStart(2, '0');
                const row = rowsByTime.get(minutes) || {};
                normalized.push({ ...row, time_label: hour + ':' + minute });
            }
            return normalized;
        }

        function renderTableHeaders(type, invNames) {
            const thead = document.querySelector('.report-table thead');
            const n = invNames.length;
            // Column widths
            const timeW = 90;
            const invW  = 110;
            const totW  = 120;
            const vcbW  = 120;
            const lossW = 120;
            let totalW = timeW + n * invW + totW + vcbW + lossW;

            let topRow = `<th rowspan="2" style="width:${timeW}px;min-width:${timeW}px;">${type==='daily'?'Time':'Date'}</th>`;
            invNames.forEach(name => {
                topRow += `<th style="width:${invW}px;min-width:${invW}px;" class="bg-blue-50/60">${name}</th>`;
            });
            topRow += `<th style="width:${totW}px;min-width:${totW}px;" class="bg-indigo-50/70">INV Total</th>`;
            topRow += `<th style="width:${vcbW}px;min-width:${vcbW}px;" class="bg-purple-50/70">HT Panel (VCB)</th>`;
            topRow += `<th style="width:${lossW}px;min-width:${lossW}px;" class="bg-red-50/50">TX Loss</th>`;

            let subRow = '';
            invNames.forEach(() => { subRow += `<th class="bg-blue-50/40">kWh</th>`; });
            subRow += `<th class="bg-indigo-50/50">kWh</th>`;
            subRow += `<th class="bg-purple-50/50">kWh</th>`;
            subRow += `<th class="bg-red-50/30">kWh</th>`;

            thead.innerHTML = `<tr>${topRow}</tr><tr>${subRow}</tr>`;
            document.querySelector('.report-table').style.minWidth = totalW + 'px';
        }

        async function fetchReportFromAPI() {
            const type = document.getElementById('reportType').value;
            const selectedDate = type === 'daily' ? dateInput.value : monthInput.value;
            const plant = plantSelect.value;
            const res = await fetch(`api_reports.php?tab=${currentReportTab}&type=${type}&date=${selectedDate}&plant=${plant}&token=${token}`, { headers: token ? { 'Authorization': 'Bearer ' + token } : {} });
            let result;
            const text = await res.text();
            try { result = JSON.parse(text); } catch (e) { throw new Error('Server returned invalid JSON: ' + text.substring(0,200)); }
            if (!result.success) throw new Error(result.error || result.message || 'Unknown server error');
            lastReportData = result;
            renderReportData(type, result.data, result.meta ? result.meta.inv_names : null);
        }

        function renderReportData(type, rows, invNames) {
            const tbody = document.getElementById('reportTableBody');
            if (!rows || !rows.length) {
                tbody.innerHTML = '<tr><td colspan="30" class="py-10 text-center text-gray-500">No data found for this period.</td></tr>';
                return;
            }
            rows = normalizeDailyTimeRows(type, rows);
            if (!invNames || !invNames.length) {
                invNames = [];
                for (let i = 1; i <= 12; i++) {
                    if (rows.some(r => (r['inv'+i+'_kwh']||0) > 0)) invNames.push('INV-'+i);
                }
                if (!invNames.length) invNames = ['INV-1','INV-2'];
            }
            renderTableHeaders(type, invNames);

            // Totals accumulators
            const totInvKwh = new Array(invNames.length).fill(0);
            let totInvTotal = 0, totVcb = 0, totLoss = 0;

            let html = '';
            rows.forEach((row, ri) => {
                let isFutureSlot = false;
                if (type === 'daily' && dateInput.value) {
                    const now = new Date();
                    const localToday = now.getFullYear() + '-' +
                        String(now.getMonth() + 1).padStart(2, '0') + '-' +
                        String(now.getDate()).padStart(2, '0');
                    const slotMinutes = (() => {
                        const match = String(row.time_label || '').match(/^(\d{1,2}):(\d{2})/);
                        return match ? Number(match[1]) * 60 + Number(match[2]) : null;
                    })();
                    isFutureSlot = dateInput.value === localToday &&
                        slotMinutes !== null &&
                        slotMinutes > now.getHours() * 60 + now.getMinutes();
                }

                const bgClass = ri % 2 === 0 ? 'bg-white' : 'bg-slate-50/60';
                html += `<tr class="${bgClass} hover:bg-blue-50/30 transition-colors">`;
                html += `<td class="font-semibold text-gray-700 bg-gray-50/80 sticky-col">${type === 'daily' ? (formatRailwayTime(row.time_label) || '-') : (row.time_label || '-')}</td>`;

                let rowInvTotal = 0;
                invNames.forEach((_, i) => {
                    const n = i + 1;
                    const kwh = isFutureSlot ? 0 : (row['inv'+n+'_kwh'] || 0);
                    rowInvTotal += kwh;
                    totInvKwh[i] += kwh;
                    html += `<td class="text-blue-700 font-medium">${fmt(kwh)}</td>`;
                });

                // Use inv_total_kwh from WS if present, otherwise sum
                const invTotal = isFutureSlot ? 0 : ((row.inv_total_kwh > 0) ? row.inv_total_kwh : rowInvTotal);
                const vcbKwh  = isFutureSlot ? 0 : (row.vcb_kwh || 0);
                const txLoss  = isFutureSlot ? 0 : ((row.tx_loss !== undefined) ? row.tx_loss : (invTotal - vcbKwh));

                totInvTotal += invTotal;
                totVcb      += vcbKwh;
                totLoss     += txLoss;

                html += `<td class="font-bold text-indigo-700">${fmt(invTotal)}</td>`;
                html += `<td class="font-bold text-purple-700">${fmt(vcbKwh)}</td>`;
                html += `<td class="font-semibold text-red-600">${fmt(txLoss)}</td>`;
                html += '</tr>';
            });

            tbody.innerHTML = html;
            updateLiveStatus();
            document.getElementById('dlBtn').disabled = false;
            document.getElementById('dlBtn').classList.remove('opacity-50','cursor-not-allowed');
        }

        async function generateReportData() {
            const type = document.getElementById('reportType').value;
            const selectedDate = type === 'daily' ? dateInput.value : monthInput.value;
            const plant = plantSelect.value;
            const dateObj = new Date(type==='daily'?selectedDate:selectedDate+'-01');
            const options = type==='daily'?{year:'numeric',month:'long',day:'numeric'}:{year:'numeric',month:'long'};
            document.getElementById('displayDate').innerText = dateObj.toLocaleDateString('en-IN', options);
            const tbody = document.getElementById('reportTableBody');
            tbody.innerHTML = '<tr><td colspan="30" class="py-12 bg-white"><div class="flex flex-col items-center justify-center"><div class="w-10 h-10 border-4 border-gray-200 border-t-blue-600 rounded-full animate-spin"></div><p class="mt-3 text-sm font-bold text-gray-600">Fetching live report...</p></div></td></tr>';

            // Monthly reports are historical aggregates. Fetch them directly from
            // the database API because the live report socket is intended for
            // daily/time-slot reports and does not reliably handle YYYY-MM dates
            // or the synthetic "all" plant subscription.
            if (type === 'monthly') {
                pendingReportRequest = false;
                if (wsReportTimeout) { clearTimeout(wsReportTimeout); wsReportTimeout = null; }
                fetchReportFromAPI().catch(err => {
                    tbody.innerHTML = '<tr><td colspan="30" class="py-10 text-center"><div class="text-red-500 font-bold mb-1">Data Error</div><div class="text-gray-400 text-xs">' + err.message + '</div></td></tr>';
                    console.error(err);
                });
                return;
            }

            pendingReportRequest = true;
            connectReportWS();

            function sendReportRequest() {
                if (!ws || ws.readyState !== WebSocket.OPEN) return false;
                // Subscribe first, then request report
                ws.send(JSON.stringify({ type: 'subscribe', unit_id: plant }));
                const pageName = type === 'daily' ? 'inverter&vcb-daily' : 'inverter&vcb-monthly';
                ws.send(JSON.stringify({ type: 'generate_report', unit_id: plant, pageName: pageName, date: selectedDate }));
                console.log('WS: subscribed + requested report for', plant, pageName, selectedDate);
                return true;
            }

            // Try immediately, or wait 2s if not yet connected
            if (!sendReportRequest()) {
                setTimeout(() => sendReportRequest(), 2000);
            }
            // Fallback to API after 15 seconds if WS didn't respond
            wsReportTimeout = setTimeout(() => {
                if (pendingReportRequest) {
                    console.log('WS report timeout, falling back to API');
                    pendingReportRequest = false;
                    fetchReportFromAPI().catch(err => {
                        tbody.innerHTML = '<tr><td colspan="30" class="py-10 text-center"><div class="text-red-500 font-bold mb-1">Data Error</div><div class="text-gray-400 text-xs">' + err.message + '</div><div class="text-gray-400 text-xs mt-1">Run ws_bridge.php to collect live data</div></td></tr>';
                        console.error(err);
                    });
                }
            }, 15000);
        }

        function fmt(v) { return v !== undefined && v !== null ? Number(v).toFixed(2) : '0.00'; }

        function exportToPDF() {
            const element = document.getElementById('printableReport');
            const table = document.querySelector('.report-table');
            
            // Get actual width of the original table
            const tableWidth = table.getBoundingClientRect().width || 1200;
            
            // Clone the element so we can style it independently off-screen to avoid sidebar/offset clipping
            const clone = element.cloneNode(true);
            clone.classList.add('pdf-mode');
            
            // Style the clone to be wide enough for all columns and positioned off-screen starting at left=0
            clone.style.position = 'absolute';
            clone.style.left = '0';
            clone.style.top = '0';
            clone.style.zIndex = '-9999';
            clone.style.backgroundColor = '#ffffff';
            clone.style.width = (tableWidth + 40) + 'px';
            clone.style.maxWidth = 'none';
            
            // Force container overflow to visible in the clone
            const cloneContainer = clone.querySelector('.table-hscroll');
            if (cloneContainer) {
                cloneContainer.style.overflow = 'visible';
                cloneContainer.style.width = '100%';
                cloneContainer.style.maxWidth = 'none';
            }
            
            document.body.appendChild(clone);
            
            const opt = {
                margin: 10,
                filename: 'vinoba_report.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2, 
                    useCORS: true,
                    width: tableWidth + 40,
                    windowWidth: tableWidth + 100,
                    scrollX: 0,
                    scrollY: 0
                },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            
            html2pdf().set(opt).from(clone).save().then(() => {
                // Remove the clone from DOM
                document.body.removeChild(clone);
            });
        }

        function downloadJson() {
            if (!lastReportData || !lastReportData.data) return;
            const type = document.getElementById('reportType').value;
            const date = type === 'daily' ? dateInput.value : monthInput.value;
            const plant = plantSelect.value;
            const plantName = plantMeta[plant] ? plantMeta[plant].name : plant;
            
            const rows = lastReportData.data;
            let invNames = lastReportData.meta ? lastReportData.meta.inv_names : null;
            if (!invNames || !invNames.length) {
                invNames = [];
                for (let i = 1; i <= 12; i++) {
                    if (rows.some(r => (r['inv'+i+'_kwh']||0) > 0)) invNames.push('INV-'+i);
                }
                if (!invNames.length) invNames = ['INV-1','INV-2'];
            }
            
            // Build columns list
            const columns = [type === 'daily' ? 'Time' : 'Date'];
            invNames.forEach(name => columns.push(name + ' (kWh)'));
            columns.push('Inverter Total (kWh)');
            columns.push('HT Panel VCB (kWh)');
            columns.push('TX Loss (kWh)');
            
            // Build rows and calculate totals
            const totInvKwh = {};
            invNames.forEach(name => totInvKwh[name] = 0);
            let totInvTotal = 0;
            let totVcb = 0;
            let totLoss = 0;
            
            const exportRows = rows.map(row => {
                const timeLabel = type === 'daily' ? formatRailwayTime(row.time_label) : (row.time_label || '');
                const inverters = {};
                let rowInvTotal = 0;
                
                invNames.forEach((name, i) => {
                    const n = i + 1;
                    const kwh = parseFloat(row['inv'+n+'_kwh']) || 0;
                    inverters[name] = parseFloat(kwh.toFixed(2));
                    rowInvTotal += kwh;
                    totInvKwh[name] += kwh;
                });
                
                const invTotal = (row.inv_total_kwh > 0) ? row.inv_total_kwh : rowInvTotal;
                const vcbKwh = row.vcb_kwh || 0;
                const txLoss = (row.tx_loss !== undefined) ? row.tx_loss : (invTotal - vcbKwh);
                
                totInvTotal += invTotal;
                totVcb += vcbKwh;
                totLoss += txLoss;
                
                const exportRow = {
                    [type === 'daily' ? 'time' : 'date']: timeLabel,
                    inverters: inverters,
                    inverter_total: parseFloat(invTotal.toFixed(2)),
                    vcb: parseFloat(vcbKwh.toFixed(2)),
                    tx_loss: parseFloat(txLoss.toFixed(2))
                };
                return exportRow;
            });
            
            // Format totals
            const exportTotals = {
                inverters: {},
                inverter_total: parseFloat(totInvTotal.toFixed(2)),
                vcb: parseFloat(totVcb.toFixed(2)),
                tx_loss: parseFloat(totLoss.toFixed(2))
            };
            invNames.forEach(name => {
                exportTotals.inverters[name] = parseFloat(totInvKwh[name].toFixed(2));
            });
            
            const exportData = {
                plant_id: plant,
                plant_name: plantName,
                report_type: type,
                date: date,
                columns: columns,
                rows: exportRows,
                totals: exportTotals
            };
            
            const filename = `vinoba_${currentReportTab}_${plant}_${date}.json`;
            const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a'); a.href = url; a.download = filename;
            document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
        }

        loadSidebar();
        connectReportWS();
        setTimeout(() => { generateReportData(); startAutoRefresh(); }, 1000);
        window.addEventListener('beforeunload', () => { stopAutoRefresh(); if (ws) ws.close(); });
    </script>
</body>
</html>
