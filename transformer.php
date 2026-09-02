<?php require 'check_auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="pageTitle">Solar Plant - Transformer</title>
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
<body class="h-full bg-slate-50 text-slate-800 font-sans">
    <div class="min-h-screen flex relative">
        <div id="overlay" class="fixed inset-0 bg-slate-900 bg-opacity-40 hidden z-30 md:hidden transition-opacity"></div>
        <div id="sidebar-container"></div>
        <main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
            <header class="bg-white p-4 sm:px-6 flex justify-between items-center sticky top-0 z-20 border-b border-slate-200 shadow-sm">
                <div class="flex items-center gap-3">
                    <button id="menuBtn" class="md:hidden text-emerald-600 text-2xl focus:outline-none">&#9776;</button>
                    <div><h2 class="text-xl font-black text-slate-800 tracking-tight">Transformer Monitoring</h2></div>
                </div>
                <div class="flex items-center gap-3 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                    <div id="refreshPulse" class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]"></div>
                    <span class="text-xs font-bold text-slate-600 tracking-widest hidden sm:inline" id="clockDisplay">--:--:--</span>
                </div>
            </header>
            <div class="p-4 sm:p-6 w-full flex flex-col gap-6 max-w-[1600px] mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition duration-300">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-orange-50 rounded-full blur-xl -z-10 group-hover:bg-orange-100 transition"></div>
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Oil Temperature</h3>
                        <p class="font-black text-slate-800 text-3xl" id="oil_temp">-- <span class="text-sm font-bold text-orange-600">degC</span></p>
                        <p class="text-xs text-slate-500 font-medium mt-1" id="oil_device">Transformer-oil</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition duration-300">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-amber-50 rounded-full blur-xl -z-10 group-hover:bg-amber-100 transition"></div>
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Winding Temp</h3>
                        <p class="font-black text-slate-800 text-3xl" id="wind_temp">-- <span class="text-sm font-bold text-amber-600">degC</span></p>
                        <p class="text-xs text-slate-500 font-medium mt-1" id="wind_device">Transformer-winding</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition duration-300">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-emerald-50 rounded-full blur-xl -z-10 group-hover:bg-emerald-100 transition"></div>
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Status</h3>
                        <p class="font-black text-emerald-700 text-3xl" id="trafo_status">Normal</p>
                        <p class="text-xs text-slate-500 font-medium mt-1">Thermal monitoring</p>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Status</p><h2 class="text-xl font-bold text-slate-900">Transformer Health</h2></div>
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700" id="trafo_badge">Normal</span>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3 mb-4">
                        <div class="bg-slate-50 rounded-lg p-4 border border-slate-100">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Oil Temp</p>
                            <p class="mt-3 text-2xl font-black text-slate-800" id="oil_detail">-- <span class="text-sm font-bold text-orange-600">degC</span></p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4 border border-slate-100">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Winding Temp</p>
                            <p class="mt-3 text-2xl font-black text-slate-800" id="wind_detail">-- <span class="text-sm font-bold text-amber-600">degC</span></p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4 border border-slate-100">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Last Update</p>
                            <p class="mt-3 text-2xl font-black text-slate-800" id="last_update">--</p>
                        </div>
                    </div>
                    <div style="height:280px;">
                        <canvas id="trafoChart"></canvas>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const currentPlant = urlParams.get('plant') || 'vinoba-velliyanai';
        const authToken = urlParams.get('token') || sessionStorage.getItem('vs_token') || '';
        const plantNames = { 'vinoba-velliyanai': 'Vinoba Velliyanai', 'makkalpower': 'Makkal Power', 'anushyam': 'Anushyam Plant' };
        document.getElementById('pageTitle').textContent = (plantNames[currentPlant] || currentPlant) + ' - Transformer';
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
                    link.classList.add('!bg-emerald-50', '!text-emerald-700', '!border-emerald-500', 'shadow-emerald-100');
                    const ic = link.querySelector('i');
                    if (ic) ic.classList.add('!text-emerald-600');
                }
            });
            const overlay = document.getElementById('overlay'), sidebar = document.getElementById('sidebar');
            document.getElementById('menuBtn')?.addEventListener('click', () => { sidebar?.classList.remove('-translate-x-full'); overlay?.classList.remove('hidden'); });
            document.getElementById('closeSidebarBtn')?.addEventListener('click', () => { sidebar?.classList.add('-translate-x-full'); overlay?.classList.add('hidden'); });
            overlay?.addEventListener('click', () => { sidebar?.classList.add('-translate-x-full'); overlay.classList.add('hidden'); });
        });
        const tState = { oil: 0, winding: 0 };
        let trafoChart;
        function initTrafoChart() {
            const ctx = document.getElementById('trafoChart').getContext('2d');
            Chart.defaults.color = '#64748b';
            trafoChart = new Chart(ctx, {
                type: 'line',
                data: { labels: [], datasets: [
                    { label: 'Oil Temp (degC)', borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,0.1)', borderWidth: 2, fill: true, tension: 0, pointRadius: 0, data: [] },
                    { label: 'Winding Temp (degC)', borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.1)', borderWidth: 2, fill: true, tension: 0, pointRadius: 0, data: [] }
                ]},
                options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 12, usePointStyle: true, font: { weight: '600' } } } },
                    scales: { x: { grid: { color: '#f1f5f9', drawBorder: false }, ticks: { maxTicksLimit: 12 } },
                        y: { beginAtZero: false, grid: { color: '#f1f5f9', drawBorder: false } } }
                }
            });
        }
        initTrafoChart();
        let lastTrafoPush = 0;
        let trafoHasData = false;
        function pushTrafoPoint() {
            const now = Date.now();
            if (trafoHasData && now - lastTrafoPush < 10000) return;
            lastTrafoPush = now; trafoHasData = true;
            const timeStr = new Date().toLocaleTimeString('en-IN', {hour:'2-digit', minute:'2-digit', hour12:false});
            trafoChart.data.labels.push(timeStr);
            trafoChart.data.datasets[0].data.push(tState.oil);
            trafoChart.data.datasets[1].data.push(tState.winding);
            if (trafoChart.data.labels.length > 50) {
                trafoChart.data.labels.shift();
                trafoChart.data.datasets.forEach(ds => ds.data.shift());
            }
            trafoChart.update('none');
        }
        function updateTrafoStatus() {
            const warn = tState.oil > 80 || tState.winding > 100;
            const statusEl = document.getElementById('trafo_status');
            const badgeEl = document.getElementById('trafo_badge');
            if (statusEl) { statusEl.textContent = warn ? 'Warning' : 'Normal'; statusEl.className = 'font-black text-3xl ' + (warn ? 'text-amber-600' : 'text-emerald-700'); }
            if (badgeEl) { badgeEl.textContent = warn ? 'Check' : 'Normal'; badgeEl.className = 'rounded-full px-3 py-1 text-xs font-bold ' + (warn ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'); }
        }
        function connectWS() {
            const ws = new WebSocket("wss://vinobasolar.scadahub.in:5001");
            ws.onopen = function() {
                document.getElementById('refreshPulse').className = 'w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]';
                ws.send(JSON.stringify({ type: "subscribe", unit_id: currentPlant }));
            };
            ws.onmessage = function(e) {
                try {
                    const d = JSON.parse(e.data); if (d.unit_id !== currentPlant) return;
                    if (d.task !== 'Transformer' || !d.values) return;
                    const v = d.values;
                    if (d.device === 'Transformer-oil') {
                        tState.oil = parseFloat(v["oil-temp"]) || 0;
                        document.getElementById('oil_temp').innerHTML = tState.oil.toFixed(1) + ' <span class="text-sm font-bold text-orange-600">degC</span>';
                        document.getElementById('oil_detail').innerHTML = tState.oil.toFixed(1) + ' <span class="text-sm font-bold text-orange-600">degC</span>';
                    }
                    if (d.device === 'Transformer-winding') {
                        tState.winding = parseFloat(v["winding-temp"]) || 0;
                        document.getElementById('wind_temp').innerHTML = tState.winding.toFixed(1) + ' <span class="text-sm font-bold text-amber-600">degC</span>';
                        document.getElementById('wind_detail').innerHTML = tState.winding.toFixed(1) + ' <span class="text-sm font-bold text-amber-600">degC</span>';
                    }
                    document.getElementById('last_update').textContent = d.time || '--';
                    updateTrafoStatus();
                    pushTrafoPoint();
                } catch(err) { console.error(err); }
            };
            ws.onclose = function() { document.getElementById('refreshPulse').className = 'w-2.5 h-2.5 bg-red-500 rounded-full'; setTimeout(connectWS, 5000); };
        }
        connectWS();
    </script>
</body>
</html>
