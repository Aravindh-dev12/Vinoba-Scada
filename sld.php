<?php require 'check_auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="pageTitle">Solar Plant - SLD</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                    <div><h2 class="text-xl font-black text-slate-800 tracking-tight">Single Line Diagram</h2></div>
                </div>
                <div class="flex items-center gap-3 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                    <div id="refreshPulse" class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]"></div>
                    <span class="text-xs font-bold text-slate-600 tracking-widest hidden sm:inline" id="clockDisplay">--:--:--</span>
                </div>
            </header>
            <div class="p-4 sm:p-6 w-full flex flex-col gap-6 max-w-[1600px] mx-auto">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-5">
                        <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Plant SLD</p><h2 class="text-xl font-bold text-slate-900">Power Flow Overview</h2></div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Live view</span>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-3 mb-6">
                        <div class="bg-slate-50 rounded-lg p-5 border border-slate-100 text-center">
                            <p class="text-sm font-black text-slate-800">PV Array (DC)</p>
                            <p class="mt-3 text-3xl font-black text-slate-900" id="sld_pv">-- <span class="text-lg text-slate-500">kW</span></p>
                            <p class="mt-2 text-xs text-slate-500">Total inverter DC input</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-5 border border-slate-100 text-center">
                            <p class="text-sm font-black text-slate-800">Inverter AC Output</p>
                            <p class="mt-3 text-3xl font-black text-slate-900" id="sld_inv">-- <span class="text-lg text-slate-500">kW</span></p>
                            <p class="mt-2 text-xs text-slate-500">Combined AC active power</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-5 border border-slate-100 text-center">
                            <p class="text-sm font-black text-slate-800">Grid Export (VCB)</p>
                            <p class="mt-3 text-3xl font-black text-slate-900" id="sld_grid">-- <span class="text-lg text-slate-500">kW</span></p>
                            <p class="mt-2 text-xs text-slate-500">3 Phase Active Power</p>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-6">
                        <div class="h-[420px] rounded-xl bg-slate-900 text-white flex items-center justify-center text-sm">
                            <div class="text-center space-y-4">
                                <p class="text-lg font-bold text-emerald-400">SLD Power Flow</p>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-8 text-sm">
                                    <div>
                                        <p class="text-slate-400 mb-1">PV Array</p>
                                        <p class="text-2xl font-black text-yellow-400" id="sld_pv2">-- kW</p>
                                    </div>
                                    <div>
                                        <p class="text-slate-400 mb-1">Inverters</p>
                                        <p class="text-2xl font-black text-blue-400" id="sld_inv2">-- kW</p>
                                    </div>
                                    <div>
                                        <p class="text-slate-400 mb-1">Grid</p>
                                        <p class="text-2xl font-black text-emerald-400" id="sld_grid2">-- kW</p>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-500 mt-4">Live data from WebSocket | Plant: <span id="sld_plant_name">--</span></p>
                            </div>
                        </div>
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
        document.getElementById('pageTitle').textContent = (plantNames[currentPlant] || currentPlant) + ' - SLD';
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
        document.getElementById('sld_plant_name').textContent = plantNames[currentPlant] || currentPlant;
        const sldState = { vcbPower: 0, inverters: {} };
        function getInvTotal() { return Object.values(sldState.inverters).reduce((s,p) => s + p, 0); }
        function connectWS() {
            const ws = new WebSocket("wss://vinobasolar.scadahub.in:5001");
            ws.onopen = function() {
                document.getElementById('refreshPulse').className = 'w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]';
                ws.send(JSON.stringify({ type: "subscribe", unit_id: currentPlant }));
            };
            ws.onmessage = function(e) {
                try {
                    const d = JSON.parse(e.data); if (d.unit_id !== currentPlant) return;
                    if (d.task === 'VCB' && d.values && d.values["3 Phase Active Power"] !== undefined) {
                        sldState.vcbPower = parseFloat(d.values["3 Phase Active Power"]) || 0;
                    }
                    if (d.values) {
                        const hasInvPower = Object.keys(d.values).some(pk => {
                            const pkl = pk.toLowerCase();
                            return (/power/.test(pkl) && /active|ac/.test(pkl) && !/reactive|apparent/.test(pkl));
                        });
                        const isInv = (d.task && d.task.toString().toLowerCase() === 'inverter') || hasInvPower || Object.keys(d.values).some(k => /\b(string|pv|dc|mppt)\b.*\d.*\b(curr|current|amp)\b/i.test(k));
                        if (isInv && hasInvPower) {
                            let pwr = 0;
                            for (const pk in d.values) {
                                const pkl = pk.toLowerCase();
                                if (/active.*power|ac.*power|power.*ac|a\.c\..*power/i.test(pkl) && !/reactive|apparent|3.phase/i.test(pkl)) {
                                    pwr = parseFloat(d.values[pk]) || 0; break;
                                }
                            }
                            sldState.inverters[d.device || 'Unknown'] = pwr;
                        }
                    }
                    updateSld();
                } catch(err) { console.error(err); }
            };
            ws.onclose = function() { document.getElementById('refreshPulse').className = 'w-2.5 h-2.5 bg-red-500 rounded-full'; setTimeout(connectWS, 5000); };
        }
        function updateSld() {
            const invTotal = getInvTotal();
            document.getElementById('sld_pv').innerHTML = invTotal.toFixed(2) + ' <span class="text-lg text-slate-500">kW</span>';
            document.getElementById('sld_inv').innerHTML = invTotal.toFixed(2) + ' <span class="text-lg text-slate-500">kW</span>';
            document.getElementById('sld_grid').innerHTML = sldState.vcbPower.toFixed(2) + ' <span class="text-lg text-slate-500">kW</span>';
            document.getElementById('sld_pv2').textContent = invTotal.toFixed(2) + ' kW';
            document.getElementById('sld_inv2').textContent = invTotal.toFixed(2) + ' kW';
            document.getElementById('sld_grid2').textContent = sldState.vcbPower.toFixed(2) + ' kW';
        }
        connectWS();
    </script>
</body>
</html>
