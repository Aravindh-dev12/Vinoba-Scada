<?php require 'check_auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="pageTitle">Solar Plant - Inverter</title>
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
                    <div><h2 class="text-xl font-black text-slate-800 tracking-tight">Inverter Overview</h2></div>
                </div>
                <div class="flex items-center gap-3 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                    <div id="refreshPulse" class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]"></div>
                    <span class="text-xs font-bold text-slate-600 tracking-widest hidden sm:inline" id="clockDisplay">--:--:--</span>
                </div>
            </header>
            <div class="p-4 sm:p-6 w-full flex flex-col gap-6 max-w-[1600px] mx-auto">
                <div id="inv_summary_cards" class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition duration-300">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-emerald-50 rounded-full blur-xl -z-10 group-hover:bg-emerald-100 transition"></div>
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Total Inverters</h3>
                        <p class="font-black text-slate-800 text-3xl" id="inv_total_count">--</p>
                        <p class="text-xs text-slate-500 font-medium mt-1">Active units</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition duration-300">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-blue-50 rounded-full blur-xl -z-10 group-hover:bg-blue-100 transition"></div>
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Total Power</h3>
                        <p class="font-black text-slate-800 text-3xl" id="inv_total">-- <span class="text-sm font-bold text-blue-600">kW</span></p>
                        <p class="text-xs text-slate-500 font-medium mt-1">Combined AC output</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition duration-300">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-50 rounded-full blur-xl -z-10 group-hover:bg-purple-100 transition"></div>
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Total Generation</h3>
                        <p class="font-black text-slate-800 text-3xl" id="inv_total_gen">-- <span class="text-sm font-bold text-purple-600">kWh</span></p>
                        <p class="text-xs text-slate-500 font-medium mt-1">Today combined</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition duration-300">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-sky-50 rounded-full blur-xl -z-10 group-hover:bg-sky-100 transition"></div>
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Efficiency</h3>
                        <p class="font-black text-slate-800 text-3xl" id="inv_avg_eff">-- <span class="text-sm font-bold text-sky-600">%</span></p>
                        <p class="text-xs text-slate-500 font-medium mt-1">Average across units</p>
                    </div>
                </div>
                <div id="inv_detail_container" class="space-y-6">
                    <div class="text-xs text-slate-400 italic text-center py-6">Waiting for inverter telemetry...</div>
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
        const currentPlant = urlParams.get('plant') || 'vinoba-velliyanai';
        const authToken = urlParams.get('token') || sessionStorage.getItem('vs_token') || '';
        const plantNames = { 'vinoba-velliyanai': 'Vinoba Velliyanai', 'makkalpower': 'Makkal Power', 'anushyam': 'Anushyam Plant' };
        document.getElementById('pageTitle').textContent = (plantNames[currentPlant] || currentPlant) + ' - Inverter';
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
        const invData = {};
        function connectWS() {
            const ws = new WebSocket("wss://vinobasolar.scadahub.in:5001");
            ws.onopen = function() {
                document.getElementById('refreshPulse').className = 'w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]';
                ws.send(JSON.stringify({ type: "subscribe", unit_id: currentPlant }));
            };
            ws.onmessage = function(e) {
                try {
                    const d = JSON.parse(e.data); if (d.unit_id !== currentPlant) return;
                    // Explicitly skip VCB messages
                    const taskStr = d.task ? d.task.toString().toLowerCase() : '';
                    const deviceStr = d.device ? d.device.toString().toLowerCase() : '';
                    if (taskStr === 'vcb' || deviceStr.includes('vcb')) return;
                    if (d.values) {
                        const keys = Object.keys(d.values);
                        const hasInvPower = keys.some(pk => {
                            const pkl = pk.toLowerCase();
                            return (/power/.test(pkl) && /active|ac/.test(pkl) && !/reactive|apparent/.test(pkl));
                        });
                        const hasNumberedCurrents = keys.some(k => /\d/.test(k) && /curr|current|amp/i.test(k) && !/phase|3.phase|reactive|apparent|freq|temp/i.test(k.toLowerCase()));
                        const isInv = (taskStr === 'inverter') || hasInvPower || hasNumberedCurrents;
                        if (!isInv) return;
                        console.log('=== INV MSG === device:', d.device, 'task:', d.task, 'keys:', keys);

                        // Pair-based detection: find all numbered current keys and pair with matching numbered voltage keys
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
                        let activeStr = 0, totalStr = 0;
                        for (const n in byNum) {
                            const group = byNum[n];
                            // Find current key in this group
                            let currKey = '', voltKey = '';
                            for (const k of group) {
                                const kl = k.toLowerCase();
                                if (usedKeys.has(k)) continue;
                                // Skip phase and inverter-level keys
                                if (/phase|phasa|ph_|r.phase|y.phase|b.phase|a.phase|c.phase|3.phase|three.phase/i.test(kl)) continue;
                                if (/inverter.*curr|inv.*curr|total.*curr|grid.*curr|load.*curr|reactive.*curr|mppt.*curr|dc.*curr/i.test(kl)) continue;
                                if (/freq|temperature|temp|ambient|cosphi|pf.*_/i.test(kl)) continue;
                                // Identify current key
                                if (!currKey && /\b(curr|current|amp|i)\b/i.test(kl) && !/\b(volt|voltage|temp|freq)\b/i.test(kl)) {
                                    currKey = k;
                                }
                                // Identify voltage key
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
                                totalStr++;
                                if (curr > 0.5) activeStr++;
                                console.log('  FOUND string', n, 'currKey:', currKey, '=', curr, 'voltKey:', voltKey || 'none', '=', volt);
                            }
                        }
                        strings.sort((x,y) => x.n - y.n);
                        const devName = d.device || 'Inverter';
                        let pwr = 0;
                        for (const pk in d.values) {
                            const pkl = pk.toLowerCase();
                            if (/active.*power|ac.*power|power.*ac|a\.c\..*power/i.test(pkl) && !/reactive|apparent|3.phase/i.test(pkl)) {
                                pwr = parseFloat(d.values[pk]) || 0; break;
                            }
                        }
                        if (!invData[devName]) invData[devName] = {};
                        invData[devName] = Object.assign({}, invData[devName], {
                            power: pwr || invData[devName].power || 0,
                            reactive: parseFloat(d.values["a.c. reactive power"]) || invData[devName].reactive || 0,
                            pf: parseFloat(d.values["Power Factor"]) || invData[devName].pf || 0,
                            vac_ab: parseFloat(d.values["a.c. voltage AB"]) || invData[devName].vac_ab || 0,
                            vac_bc: parseFloat(d.values["a.c. voltage BC"]) || invData[devName].vac_bc || 0,
                            vac_ca: parseFloat(d.values["a.c. voltage CA"]) || invData[devName].vac_ca || 0,
                            freq: parseFloat(d.values["a.c. frequency"]) || invData[devName].freq || 0,
                            i_a: parseFloat(d.values["A phase current"]) || invData[devName].i_a || 0,
                            i_b: parseFloat(d.values["B phase current"]) || invData[devName].i_b || 0,
                            i_c: parseFloat(d.values["C phase current"]) || invData[devName].i_c || 0,
                            eff: parseFloat(d.values["inverter efficiency"]) || invData[devName].eff || 0,
                            amb: parseFloat(d.values["internal ambient temperature"]) || invData[devName].amb || 0,
                            dailyGen: parseFloat(d.values["daily generation"]) || invData[devName].dailyGen || 0,
                            totalGen: parseFloat(d.values["total generation"]) || invData[devName].totalGen || 0,
                            dailyCO2: parseFloat(d.values["daily CO2 reduction"]) || invData[devName].dailyCO2 || 0,
                            totalCO2: parseFloat(d.values["total CO2 reduction"]) || invData[devName].totalCO2 || 0,
                            dailyHrs: parseFloat(d.values["daily working hours"]) || invData[devName].dailyHrs || 0,
                            totalHrs: parseFloat(d.values["total working hours"]) || invData[devName].totalHrs || 0,
                            activeStr, totalStr, strings
                        });
                        console.log('=== STORED', devName, 'strings:', strings.length, 'active:', activeStr, 'total:', totalStr);
                        renderAll();
                    }
                } catch(err) { console.error(err); }
            };
            ws.onclose = function() { document.getElementById('refreshPulse').className = 'w-2.5 h-2.5 bg-red-500 rounded-full'; setTimeout(connectWS, 5000); };
        }
        function openStringModal(invName) {
            const inv = invData[invName];
            console.log('openStringModal:', invName, 'inv:', inv, 'strings:', inv ? inv.strings : 'none');
            if (!inv) return;
            document.getElementById('stringModalTitle').textContent = invName + ' - String Details';
            const grid = document.getElementById('stringGrid');
            if (!inv.strings || !inv.strings.length) {
                grid.innerHTML = '<div class="col-span-full text-center text-sm text-slate-400 py-8">No string data available</div>';
            } else {
                grid.innerHTML = inv.strings.map(s => {
                    const ok = s.active;
                    return `<div class="border ${ok ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50'} rounded-lg p-2 text-center">
                        <p class="text-[10px] font-bold ${ok ? 'text-emerald-700' : 'text-red-700'} uppercase tracking-wider">String ${s.n}</p>
                        <p class="mt-1 text-sm font-black ${ok ? 'text-slate-800' : 'text-red-700'}">${s.curr.toFixed(1)} <span class="text-[10px] text-slate-500">A</span></p>
                        <p class="text-[10px] font-medium text-slate-500">${s.volt.toFixed(1)} V</p>
                    </div>`;
                }).join('');
            }
            document.getElementById('stringModal').classList.remove('hidden');
        }
        function closeStringModal() {
            document.getElementById('stringModal').classList.add('hidden');
        }
        document.getElementById('stringModal').addEventListener('click', function(e) {
            if (e.target === this) closeStringModal();
        });
        function renderAll() {
            const keys = Object.keys(invData).sort((a,b) => (parseInt(a.replace(/\D/g,''))||0) - (parseInt(b.replace(/\D/g,''))||0));
            if (!keys.length) return;
            const totalPower = keys.reduce((s,k) => s + invData[k].power, 0);
            const totalGen = keys.reduce((s,k) => s + invData[k].dailyGen, 0);
            const avgEff = keys.length ? (keys.reduce((s,k) => s + invData[k].eff, 0) / keys.length) : 0;
            document.getElementById('inv_total_count').textContent = keys.length;
            document.getElementById('inv_total').innerHTML = totalPower.toFixed(2) + ' <span class="text-sm font-bold text-blue-600">kW</span>';
            document.getElementById('inv_total_gen').innerHTML = totalGen.toFixed(2) + ' <span class="text-sm font-bold text-purple-600">kWh</span>';
            document.getElementById('inv_avg_eff').innerHTML = avgEff.toFixed(1) + ' <span class="text-sm font-bold text-sky-600">%</span>';
            const container = document.getElementById('inv_detail_container');
            container.innerHTML = keys.map(k => {
                const v = invData[k];
                return `<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                        <h3 class="text-sm font-black text-slate-600 uppercase tracking-widest flex items-center gap-2"><i class="fa-solid fa-server text-blue-500"></i> ${k}</h3>
                        <div class="flex items-center gap-2">
                            <button onclick="openStringModal('${k}')" class="bg-blue-50 hover:bg-blue-100 text-blue-600 text-xs font-bold px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                                <i class="fa-solid fa-eye"></i> View Strings
                            </button>
                            <span class="rounded-full ${v.power > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'} px-3 py-1 text-xs font-bold">${v.power > 0 ? 'Online' : 'Offline'}</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mb-4">
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">AC Power</p><p class="mt-1 text-xl font-black text-slate-800">${v.power.toFixed(2)} <span class="text-xs text-blue-600">kW</span></p></div>
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Reactive</p><p class="mt-1 text-xl font-black text-slate-800">${v.reactive.toFixed(2)} <span class="text-xs text-sky-600">kVAR</span></p></div>
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Power Factor</p><p class="mt-1 text-xl font-black text-slate-800">${v.pf.toFixed(3)}</p></div>
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Efficiency</p><p class="mt-1 text-xl font-black text-slate-800">${v.eff.toFixed(1)}<span class="text-xs text-emerald-600">%</span></p></div>
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">AC Freq</p><p class="mt-1 text-xl font-black text-slate-800">${v.freq.toFixed(2)} <span class="text-xs text-slate-500">Hz</span></p></div>
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Ambient</p><p class="mt-1 text-xl font-black text-slate-800">${v.amb.toFixed(1)} <span class="text-xs text-orange-600">degC</span></p></div>
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Vac AB</p><p class="mt-1 text-xl font-black text-slate-800">${v.vac_ab.toFixed(1)} <span class="text-xs text-slate-500">V</span></p></div>
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Vac BC</p><p class="mt-1 text-xl font-black text-slate-800">${v.vac_bc.toFixed(1)} <span class="text-xs text-slate-500">V</span></p></div>
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Vac CA</p><p class="mt-1 text-xl font-black text-slate-800">${v.vac_ca.toFixed(1)} <span class="text-xs text-slate-500">V</span></p></div>
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">I A</p><p class="mt-1 text-xl font-black text-slate-800">${v.i_a.toFixed(2)} <span class="text-xs text-slate-500">A</span></p></div>
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">I B</p><p class="mt-1 text-xl font-black text-slate-800">${v.i_b.toFixed(2)} <span class="text-xs text-slate-500">A</span></p></div>
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">I C</p><p class="mt-1 text-xl font-black text-slate-800">${v.i_c.toFixed(2)} <span class="text-xs text-slate-500">A</span></p></div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Daily Gen</p><p class="mt-1 text-lg font-bold text-slate-800">${v.dailyGen.toFixed(1)} <span class="text-xs text-purple-600">kWh</span></p></div>
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Gen</p><p class="mt-1 text-lg font-bold text-slate-800">${v.totalGen.toFixed(0)} <span class="text-xs text-purple-600">kWh</span></p></div>
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Daily CO2</p><p class="mt-1 text-lg font-bold text-slate-800">${v.dailyCO2.toFixed(1)} <span class="text-xs text-emerald-600">kg</span></p></div>
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total CO2</p><p class="mt-1 text-lg font-bold text-slate-800">${v.totalCO2.toFixed(0)} <span class="text-xs text-emerald-600">kg</span></p></div>
                    </div>
                </div>`;
            }).join('');
        }
        connectWS();
    </script>
</body>
</html>
