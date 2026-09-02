<?php
$token = isset($_GET['token']) ? $_GET['token'] : '';
$adminUser = null;
if ($token) {
    require 'config.php';
    $safeToken = $conn->real_escape_string($token);
    $res = $conn->query("SELECT * FROM users WHERE auth_token = '$safeToken' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $adminUser = $res->fetch_assoc();
    }
}
if (!$adminUser || $adminUser['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solar Plants Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body { 
            font-family: 'Inter', sans-serif; 
        }
        
        .alert-state {
            animation: alert-glow 1.5s infinite;
            border-width: 2px !important;
        }
        @keyframes alert-glow {
            0%, 100% { box-shadow: 0 0 5px rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.4); }
            50% { box-shadow: 0 0 20px rgba(239, 68, 68, 0.8); border-color: rgba(239, 68, 68, 1); }
        }
        
        .status-badge-pulse { 
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; 
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9; 
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1; 
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8; 
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <div id="dashboard-view" class="flex flex-col min-h-screen w-full">
        <nav class="bg-white border-b border-slate-200 sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                <div class="flex flex-wrap justify-between min-h-14 py-2 gap-2 items-center">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-blue-600 text-white rounded-md flex items-center justify-center"><i class="fa-solid fa-bolt text-sm"></i></div>
                        <h1 class="text-lg font-bold tracking-tight text-slate-800">Vinoba Solar Dashboard</h1>
                    </div>
                    <div class="flex items-center gap-3">
                        <span id="ws-status" class="text-xs font-bold text-red-500"><i class="fa-solid fa-circle text-[8px] mr-1"></i>Disconnected</span>
                        
                        <div class="h-6 w-px bg-slate-200 mx-1"></div>
                        
                        <div class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md" title="Live combined active power across all plants">
                            <i class="fa-solid fa-bolt text-amber-500"></i>
                            <span>Overall Active Power</span>
                            <span id="overall-active-power" class="font-black tabular-nums">0.00 kW</span>
                        </div>
                        <button onclick="logout()" class="px-3 py-1.5 text-xs font-medium text-slate-600 bg-slate-100 hover:bg-red-50 hover:text-red-600 rounded-md transition-colors">
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <main class="flex-grow max-w-7xl mx-auto w-full py-6 px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6" id="plants-container">
                </div>
        </main>
    </div>

    <div id="manage-users-modal" class="fixed inset-0 bg-slate-900/50 hidden z-[100] flex items-center justify-center backdrop-blur-sm px-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-slate-800">Add New User</h3>
                <button onclick="document.getElementById('manage-users-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="addUserForm" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">User Email</label>
                    <input type="email" id="new_email" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Password</label>
                    <input type="password" id="new_password" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Plant ID</label>
                    <select id="new_plant_id" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
                        <option value="vinoba-velliyanai">Vinoba Velliyanai</option>
                        <option value="makkalpower">Makkal Power</option>
                        <option value="anushyam">Anushyam Plant</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Role</label>
                    <select id="new_role" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg text-sm transition-colors">Create User</button>
                <p id="user-msg" class="text-xs text-center font-medium mt-2 hidden"></p>
            </form>
        </div>
    </div>

    <script>
        const plants = [
            { id: 'vinoba-velliyanai', name: 'Vinoba Velliyanai', theme: 'violet' },
            { id: 'makkalpower', name: 'Makkal Power', theme: 'blue' },
            { id: 'anushyam', name: 'Anushyam Plant', theme: 'emerald' }
        ];

        const plantState = {};
        const authToken = new URLSearchParams(window.location.search).get('token') || '';
        plants.forEach(p => {
            plantState[p.id] = {
                vcbPower: 0,
                hasVCB: false,
                dailyEnergy: 0,
                inverters: {},
                peakInverterKw: 0,
                peakHour: '--:--',
                hourlyPowerByInverter: {},
                liveCombinedKw: 0,
                lastUpdate: '--'
            };
        });

        document.addEventListener('DOMContentLoaded', () => {
            renderCards();
            connectWS();
        });

        function logout() {
            localStorage.removeItem('userRole');
            localStorage.removeItem('vs_token');
            localStorage.removeItem('vs_user');
            window.location.href = 'logout.php';
        }

        document.getElementById('addUserForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = document.getElementById('user-msg');
            msg.classList.add('hidden');

            const token = localStorage.getItem('vs_token');
            try {
                const res = await fetch('api.php?action=add_user', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + token
                    },
                    body: JSON.stringify({ 
                        email: document.getElementById('new_email').value, 
                        password: document.getElementById('new_password').value,
                        plant_id: document.getElementById('new_plant_id').value,
                        role: document.getElementById('new_role').value
                    })
                });
                const data = await res.json();
                
                msg.innerHTML = data.status === 'success' 
                    ? `<i class="fa-solid fa-circle-check mr-1"></i> ${data.message}` 
                    : `<i class="fa-solid fa-circle-exclamation mr-1"></i> ${data.message}`;
                
                msg.className = `text-xs text-center font-medium mt-2 ${data.status === 'success' ? 'text-green-600' : 'text-red-600'}`;
                msg.classList.remove('hidden');
                
                if(data.status === 'success') document.getElementById('addUserForm').reset();
            } catch (error) {
                console.error('User Add Error:', error);
            }
        });

        function renderCards() {
            const container = document.getElementById('plants-container');
            container.innerHTML = plants.map(p => `
                <a href="home.php?plant=${p.id}&token=${authToken}" class="block">
                    <div id="card-${p.id}" class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm flex flex-col h-[430px] transition-all hover:-translate-y-1 hover:shadow-lg duration-200">
                        <div class="bg-${p.theme}-50 px-4 py-3 border-b border-${p.theme}-100 flex justify-between items-center shrink-0">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-solar-panel text-${p.theme}-600"></i>
                                <h3 class="text-base font-bold text-slate-800">${p.name}</h3>
                            </div>
                            <div id="badge-${p.id}" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-200 text-slate-500">Wait...</div>
                        </div>
                        
                        <div class="p-4 grid grid-cols-2 gap-3 shrink-0">
                            <div class="bg-slate-50 rounded-lg p-3 border border-slate-100 flex flex-col justify-center">
                                <div class="text-slate-400 text-[10px] font-bold uppercase mb-1 tracking-wider"><i class="fa-solid fa-bolt text-yellow-500 mr-1"></i><span id="active-label-${p.id}">Active</span></div>
                                <div class="text-sm font-black text-slate-800" id="active-${p.id}">0.00 kW</div>
                            </div>
                            <div class="bg-slate-50 rounded-lg p-3 border border-slate-100 flex flex-col justify-center">
                                <div class="text-slate-400 text-[10px] font-bold uppercase mb-1 tracking-wider"><i class="fa-solid fa-sun text-orange-400 mr-1"></i>Today</div>
                                <div class="text-sm font-black text-slate-800" id="today-${p.id}">0.00 kWh</div>
                            </div>
                            <div class="col-span-2 bg-amber-50/60 rounded-lg p-3 border border-amber-100 flex items-center justify-between">
                                <div>
                                    <div class="text-amber-600 text-[10px] font-bold uppercase mb-1 tracking-wider"><i class="fa-solid fa-chart-line mr-1"></i>Today Peak — Combined Inverters</div>
                                    <div class="text-base font-black text-slate-800" id="peak-${p.id}">0.000 MW</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-slate-400 text-[10px] font-bold uppercase mb-1">Peak Hour</div>
                                    <div class="text-sm font-black text-amber-700" id="peak-hour-${p.id}">--:--</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="px-4 pb-4 flex flex-col overflow-hidden">
                            <div class="flex justify-between items-center mb-2 shrink-0">
                                <div class="text-slate-500 text-[10px] font-bold uppercase tracking-wider"><i class="fa-solid fa-network-wired mr-1 text-slate-400"></i>Inverter Details</div>
                                <div class="text-[9px] text-slate-400 font-medium">Update: <span id="time-${p.id}">--</span></div>
                            </div>
                            <div id="inverters-${p.id}" class="bg-slate-50 rounded-lg p-2 border border-slate-200 flex-grow overflow-y-auto space-y-1.5 custom-scrollbar">
                                <div class="text-xs text-slate-400 italic text-center py-4">Waiting for telemetry data...</div>
                            </div>
                        </div>
                    </div>
                </a>
            `).join('');
        }

        function updateUI(unit_id) {
            const st = plantState[unit_id];
            if(!st) return;

            let sumInverterPower = 0;
            for (const inv in st.inverters) {
                sumInverterPower += st.inverters[inv].power;
            }

            const finalActivePower = st.hasVCB ? st.vcbPower : sumInverterPower;
            const activeLabel = st.hasVCB ? 'VCB Active' : 'Active';

            document.getElementById(`active-${unit_id}`).textContent = `${finalActivePower.toFixed(2)} kW`;
            const labelEl = document.getElementById(`active-label-${unit_id}`);
            if (labelEl) labelEl.textContent = activeLabel;
            updateOverallActivePower();
            document.getElementById(`today-${unit_id}`).textContent  = `${st.dailyEnergy.toFixed(2)} kWh`;
            document.getElementById(`peak-${unit_id}`).textContent = `${(st.peakInverterKw / 1000).toFixed(3)} MW`;
            document.getElementById(`peak-hour-${unit_id}`).textContent = st.peakHour;
            document.getElementById(`time-${unit_id}`).textContent   = st.lastUpdate;

            const invContainer = document.getElementById(`inverters-${unit_id}`);
            let invHTML = '';

            const PLANT_GREEN_THRESHOLD = {
                'vinoba-velliyanai': 22,
                'makkalpower': 22,
                'anushyam': 22
            };
            const plantThreshold = PLANT_GREEN_THRESHOLD[unit_id] || null;
            const sortedInverters = Object.keys(st.inverters).sort((a, b) => {
                const numA = parseInt(a.replace(/\D/g, '')) || 0;
                const numB = parseInt(b.replace(/\D/g, '')) || 0;
                return numA - numB;
            });
            
            if (sortedInverters.length > 0) {
                sortedInverters.forEach(invName => {
                    const inv = st.inverters[invName];
                    const hasStrings = inv.total > 0;
                    const greenThreshold = plantThreshold !== null ? Math.min(plantThreshold, inv.total) : Math.ceil(inv.total * 0.7);
                    const isGreen = hasStrings && inv.active >= greenThreshold;
                    const isYellow = hasStrings && inv.active > 0 && inv.active < greenThreshold;
                    const isRed = hasStrings && inv.active === 0;

                    let strColor, strBorder, rowBg, iconColor;
                    if (isGreen) {
                        strColor = 'text-emerald-700 bg-emerald-100'; strBorder = 'border-emerald-200'; rowBg = 'bg-emerald-50/30'; iconColor = 'text-emerald-500';
                    } else if (isRed) {
                        strColor = 'text-red-700 bg-red-100'; strBorder = 'border-red-200'; rowBg = 'bg-red-50'; iconColor = 'text-red-500';
                    } else if (isYellow) {
                        strColor = 'text-amber-700 bg-amber-100'; strBorder = 'border-amber-300'; rowBg = 'bg-amber-50/50'; iconColor = 'text-amber-400';
                    } else {
                        strColor = 'text-slate-600 bg-slate-100'; strBorder = 'border-slate-200'; rowBg = 'bg-white'; iconColor = 'text-slate-300';
                    }
                    const strBadge = hasStrings
                        ? `<span class="font-bold px-1.5 py-0.5 rounded border ${strBorder} ${strColor} min-w-[36px] text-center text-[10px]">${inv.active}/${inv.total}</span>`
                        : '';

                    invHTML += `
                        <div class="flex justify-between items-center text-xs ${rowBg} px-2.5 py-1.5 rounded border border-slate-200 shadow-sm transition-colors">
                            <span class="font-semibold text-slate-700 capitalize flex items-center gap-1.5">
                                <i class="fa-solid fa-server ${iconColor} text-[10px]"></i>
                                ${invName}
                            </span>
                            <div class="flex items-center gap-2">
                                <span class="text-slate-500 font-medium">${inv.power.toFixed(1)} kW</span>
                                ${strBadge}
                            </div>
                        </div>
                    `;
                });
                invContainer.innerHTML = invHTML;
            }

            const badge = document.getElementById(`badge-${unit_id}`);
            const card = document.getElementById(`card-${unit_id}`);

            if (finalActivePower > 0) {
                badge.className = "text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700";
                badge.innerHTML = `<span class="h-2 w-2 rounded-full bg-green-500 inline-block mr-1 shadow-[0_0_5px_green]"></span> ON`;
                card.classList.remove('alert-state');
            } else {
                badge.className = "text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700 status-badge-pulse";
                badge.innerHTML = `<span class="h-2 w-2 rounded-full bg-red-500 inline-block mr-1"></span> OFF`;
                card.classList.add('alert-state');
            }
        }

        function updateOverallActivePower() {
            const totalKw = Object.values(plantState).reduce((total, state) => {
                if (state.hasVCB) return total + (Number(state.vcbPower) || 0);
                const inverterKw = Object.values(state.inverters || {}).reduce(
                    (sum, inverter) => sum + (Number(inverter.power) || 0), 0
                );
                return total + inverterKw;
            }, 0);
            const totalEl = document.getElementById('overall-active-power');
            if (totalEl) totalEl.textContent = totalKw.toFixed(2) + ' kW';
        }

        function extractInverterPower(values) {
            if (!values || typeof values !== 'object') return 0;
            for (const key in values) {
                const lower = key.toLowerCase();
                if (/active.*power|ac.*power|power.*ac|a\.c\..*power/i.test(lower) && !/reactive|apparent|3.phase/i.test(lower)) {
                    return Number(values[key]) || 0;
                }
            }
            return 0;
        }

        function loadInverterPeakHistory(unit, data, requestedDevice = '') {
            const st = plantState[unit];
            const readings = data.data || data.records || data.results || data.values || [];
            if (!st || !Array.isArray(readings) || !readings.length) return;
            const sample = readings[0] || {};
            const deviceName = data.device || sample.device || requestedDevice;
            if (!deviceName) return;

            const commonTimePower = {};
            readings.forEach(reading => {
                const values = reading.values || reading.data || reading;
                const power = extractInverterPower(values);
                const rawTime = String(reading.timestamp || reading.time || reading.recorded_at || reading.datetime || '');
                const directTime = rawTime.match(/(?:T|\s|^)(\d{1,2}):(\d{2})/);
                const parsedTime = new Date(rawTime);
                let hour = null;
                let minute = null;
                if (directTime) {
                    hour = Number(directTime[1]);
                    minute = Number(directTime[2]);
                } else if (!isNaN(parsedTime.getTime())) {
                    hour = parsedTime.getHours();
                    minute = parsedTime.getMinutes();
                }
                if (hour !== null && minute !== null) {
                    const fiveMinute = Math.floor(minute / 5) * 5;
                    const timeBucket = String(hour).padStart(2, '0') + ':' + String(fiveMinute).padStart(2, '0');
                    // Keep the latest/highest sample for this inverter in the same short bucket.
                    if (power > (commonTimePower[timeBucket] || 0)) commonTimePower[timeBucket] = power;
                }
            });
            st.hourlyPowerByInverter[deviceName] = commonTimePower;

            const inverterSeries = Object.values(st.hourlyPowerByInverter);
            let historicalPeakKw = 0;
            let historicalPeakTime = '--:--';
            if (inverterSeries.length) {
                const commonTimes = Object.keys(inverterSeries[0]).filter(time =>
                    inverterSeries.every(series => Object.prototype.hasOwnProperty.call(series, time))
                );
                commonTimes.forEach(time => {
                    const combinedPower = inverterSeries.reduce((sum, series) => sum + series[time], 0);
                    if (combinedPower > historicalPeakKw) {
                        historicalPeakKw = combinedPower;
                        historicalPeakTime = time;
                    }
                });
            }

            st.peakInverterKw = historicalPeakKw;
            st.peakHour = historicalPeakTime;
            if (st.liveCombinedKw > st.peakInverterKw) {
                const now = new Date();
                st.peakInverterKw = st.liveCombinedKw;
                st.peakHour = String(now.getHours()).padStart(2, '0') + ':' +
                    String(Math.floor(now.getMinutes() / 5) * 5).padStart(2, '0');
            }
            updateUI(unit);
        }

        const requestedPeakHistory = new Set();
        const pendingPeakHistory = {};
        let ws;
        function connectWS() {
            ws = new WebSocket("wss://vinobasolar.scadahub.in:5001");

            ws.onopen = function() {
                requestedPeakHistory.clear();
                Object.keys(pendingPeakHistory).forEach(unit => { pendingPeakHistory[unit] = []; });
                const wsStatus = document.getElementById('ws-status');
                wsStatus.className = "text-xs font-bold text-green-500";
                wsStatus.innerHTML = '<i class="fa-solid fa-circle text-[8px] mr-1 shadow-[0_0_5px_green]"></i> Live Data';
                
                plants.forEach(p => {
                    ws.send(JSON.stringify({ type: "subscribe", unit_id: p.id }));
                });
            };

            ws.onmessage = function(event) {
                try {
                    const data = JSON.parse(event.data);
                    const unit = data.unit_id;
                    if(!plantState[unit]) return;

                    if (data.type === 'daily_data_result') {
                        const queuedDevice = pendingPeakHistory[unit] && pendingPeakHistory[unit].length
                            ? pendingPeakHistory[unit].shift()
                            : '';
                        loadInverterPeakHistory(unit, data, queuedDevice);
                        return;
                    }

                    plantState[unit].lastUpdate = data.time || new Date().toLocaleTimeString();

                    if (data.values && data.values["3 Phase Active Power"] !== undefined) {
                        plantState[unit].vcbPower = parseFloat(data.values["3 Phase Active Power"]) || 0;
                        plantState[unit].hasVCB = true;
                    }

                    if (data.virtualTags && data.virtualTags["vcb-today"] !== undefined) {
                        plantState[unit].dailyEnergy = parseFloat(data.virtualTags["vcb-today"].value) || 0;
                    }

                    if (data.values) {
                        const taskStr = data.task ? data.task.toString().toLowerCase() : '';
                        const deviceStr = data.device ? data.device.toString().toLowerCase() : '';
                        const isVCBMsg = (taskStr === 'vcb' || deviceStr.includes('vcb'));

                        if (!isVCBMsg) {
                            const keys = Object.keys(data.values);
                            const hasInvPower = keys.some(pk => {
                                const pkl = pk.toLowerCase();
                                return (/power/.test(pkl) && /active|ac/.test(pkl) && !/reactive|apparent/.test(pkl));
                            });
                            const hasNumberedCurrents = keys.some(k => /\d/.test(k) && /curr|current|amp/i.test(k) && !/phase|3.phase|reactive|apparent|freq|temp/i.test(k.toLowerCase()));
                            const isInv = (taskStr === 'inverter') || hasInvPower || hasNumberedCurrents;
                            if (isInv) {
                            let activeStrCount = 0;
                            let totalStrCount = 0;
                            for (const key in data.values) {
                                const kl = key.toLowerCase();
                                if (/phase|phasa|ph_|r.phase|y.phase|b.phase|a.phase|c.phase|3.phase|three.phase/i.test(kl)) continue;
                                if (/inverter.*curr|inv.*curr|total.*curr|grid.*curr|load.*curr|reactive.*curr|mppt.*curr|dc.*curr/i.test(kl)) continue;
                                if (/freq|temperature|temp|ambient|cosphi|pf.*_/i.test(kl)) continue;
                                if (/\b(curr|current|amp|i)\b/i.test(kl) && !/\b(volt|voltage|temp|freq)\b/i.test(kl) && /\d/.test(key)) {
                                    totalStrCount++;
                                    if (parseFloat(data.values[key]) > 0.5) activeStrCount++;
                                }
                            }
                            const deviceName = data.device || "Unknown Inverter";
                            const historyKey = unit + '|' + deviceName;
                            if (!requestedPeakHistory.has(historyKey) && ws.readyState === WebSocket.OPEN) {
                                requestedPeakHistory.add(historyKey);
                                if (!pendingPeakHistory[unit]) pendingPeakHistory[unit] = [];
                                pendingPeakHistory[unit].push(deviceName);
                                ws.send(JSON.stringify({
                                    type: 'get_daily_data',
                                    unit_id: unit,
                                    device: deviceName,
                                    date: new Date().getFullYear() + '-' +
                                        String(new Date().getMonth() + 1).padStart(2, '0') + '-' +
                                        String(new Date().getDate()).padStart(2, '0')
                                }));
                            }
                            let pwr = 0;
                            for (const pk in data.values) {
                                const pkl = pk.toLowerCase();
                                if (/active.*power|ac.*power|power.*ac|a\.c\..*power/i.test(pkl) && !/reactive|apparent|3.phase/i.test(pkl)) {
                                    pwr = parseFloat(data.values[pk]) || 0; break;
                                }
                            }
                            plantState[unit].inverters[deviceName] = {
                                active: activeStrCount,
                                total: totalStrCount,
                                power: pwr
                            };
                            const liveCombinedPower = Object.values(plantState[unit].inverters)
                                .reduce((sum, inverter) => sum + (inverter.power || 0), 0);
                            plantState[unit].liveCombinedKw = liveCombinedPower;
                            if (liveCombinedPower > plantState[unit].peakInverterKw) {
                                const now = new Date();
                                plantState[unit].peakInverterKw = liveCombinedPower;
                                plantState[unit].peakHour = String(now.getHours()).padStart(2, '0') + ':' +
                                    String(Math.floor(now.getMinutes() / 5) * 5).padStart(2, '0');
                            }
                            }
                        }
                    }

                    updateUI(unit);

                } catch(e) {
                    console.error("WS Parse Error:", e);
                }
            };

            ws.onclose = function() {
                const wsStatus = document.getElementById('ws-status');
                wsStatus.className = "text-xs font-bold text-red-500 status-badge-pulse";
                wsStatus.innerHTML = '<i class="fa-solid fa-circle text-[8px] mr-1"></i> Disconnected - Retrying...';
                
                setTimeout(connectWS, 5000); 
            };
            
            ws.onerror = function() {
                console.log("WebSocket encountered an error.");
            };
        }
    </script>
</body>
</html>
