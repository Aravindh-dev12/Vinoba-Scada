(function () {
    const DESKTOP_BREAKPOINT = 768;
    const STORAGE_KEY = 'vs_sidebar_collapsed';

    function initializeSidebar() {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar || sidebar.dataset.collapseReady === 'true') return;
        sidebar.dataset.collapseReady = 'true';

        const main = document.querySelector('main');
        const toggle = document.getElementById('collapseSidebarBtn');
        const icon = toggle ? toggle.querySelector('i') : null;
        const navLinks = sidebar.querySelectorAll('nav a');
        const primaryNav = document.getElementById('sidebarNav');
        const labels = sidebar.querySelectorAll('nav a span');
        const brandText = sidebar.querySelector('.sidebar-brand-text');
        const brandIcon = sidebar.querySelector('.sidebar-brand-icon');
        const footerText = sidebar.querySelector('.sidebar-footer-text');
        const sidebarHeader = sidebar.firstElementChild;

        if (toggle && sidebarHeader) {
            sidebarHeader.appendChild(toggle);
            toggle.className = 'hidden md:flex absolute top-3 w-7 h-7 items-center justify-center rounded-full bg-white border border-slate-200 shadow-md text-slate-500 hover:bg-emerald-100 hover:text-emerald-700 transition z-50';
            toggle.style.right = '-0.85rem';
        }

        function applyLayout() {
            const desktop = window.innerWidth >= DESKTOP_BREAKPOINT;
            const collapsed = desktop && localStorage.getItem(STORAGE_KEY) === '1';

            sidebar.style.width = collapsed ? '5rem' : '16rem';
            sidebar.style.overflow = 'visible';
            if (main) {
                main.style.marginLeft = desktop ? (collapsed ? '5rem' : '16rem') : '0';
                main.style.transition = 'margin-left 300ms ease';
            }

            labels.forEach(label => label.classList.toggle('hidden', collapsed));
            if (brandText) brandText.classList.toggle('hidden', collapsed);
            if (footerText) footerText.classList.toggle('hidden', collapsed);
            if (sidebarHeader) sidebarHeader.style.padding = collapsed ? '0.75rem 0.5rem' : '1rem';
            if (primaryNav) primaryNav.style.padding = collapsed ? '0.5rem' : '1rem';
            if (brandIcon) {
                brandIcon.classList.toggle('h-16', !collapsed);
                brandIcon.classList.toggle('w-16', !collapsed);
                brandIcon.classList.toggle('h-10', collapsed);
                brandIcon.classList.toggle('w-10', collapsed);
            }
            navLinks.forEach(link => {
                link.classList.toggle('justify-center', collapsed);
                link.style.justifyContent = collapsed ? 'center' : '';
                link.style.paddingLeft = collapsed ? '0.5rem' : '';
                link.style.paddingRight = collapsed ? '0.5rem' : '';
                link.style.minHeight = '2.75rem';
                const label = link.querySelector('span');
                const linkIcon = link.querySelector('i');
                if (label) link.title = collapsed ? label.textContent.trim() : '';
                if (linkIcon) {
                    linkIcon.style.display = 'inline-flex';
                    linkIcon.style.alignItems = 'center';
                    linkIcon.style.justifyContent = 'center';
                    linkIcon.style.flexShrink = '0';
                    linkIcon.style.margin = '0';
                }
            });
            if (icon) icon.className = collapsed ? 'fa-solid fa-angles-right' : 'fa-solid fa-angles-left';
            if (toggle) toggle.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
        }

        if (toggle) {
            toggle.addEventListener('click', function () {
                const collapsed = localStorage.getItem(STORAGE_KEY) === '1';
                localStorage.setItem(STORAGE_KEY, collapsed ? '0' : '1');
                applyLayout();
            });
        }
        window.addEventListener('resize', applyLayout);
        applyLayout();
    }

    function installMonthlyReportsFix() {
        if (!/\/reports\.php$/i.test(window.location.pathname)) return;
        if (window.__monthlyReportsHistoricalFixV2) return;
        if (typeof window.generateReportData !== 'function' || typeof window.handleWSReportResponse !== 'function') return;

        window.__monthlyReportsHistoricalFixV2 = true;

        const originalGenerateReportData = window.generateReportData;
        const originalHandleWSReportResponse = window.handleWSReportResponse;
        let monthlyRun = null;

        function numberValue(value) {
            if (value === undefined || value === null || value === '') return 0;
            if (typeof value === 'number') return Number.isFinite(value) ? value : 0;
            if (typeof value === 'object') {
                const candidates = ['value', 'rawValue', 'displayValue', 'text', 'formattedValue'];
                for (const key of candidates) {
                    if (Object.prototype.hasOwnProperty.call(value, key)) {
                        const parsed = numberValue(value[key]);
                        if (parsed !== 0) return parsed;
                    }
                }
                return 0;
            }
            const cleaned = String(value).replace(/,/g, '').trim();
            const match = cleaned.match(/[-+]?\d*\.?\d+(?:[eE][-+]?\d+)?/);
            if (!match) return 0;
            const n = parseFloat(match[0]);
            return Number.isFinite(n) ? n : 0;
        }

        function reportPayload(message) {
            if (!message || typeof message !== 'object') return { columns: [], rows: [] };
            if (Array.isArray(message.columns) || Array.isArray(message.rows)) {
                return {
                    columns: Array.isArray(message.columns) ? message.columns : [],
                    rows: Array.isArray(message.rows) ? message.rows : []
                };
            }
            const nested = message.reportData;
            if (nested && typeof nested === 'object') {
                return {
                    columns: Array.isArray(nested.columns) ? nested.columns : [],
                    rows: Array.isArray(nested.rows) ? nested.rows : []
                };
            }
            return { columns: [], rows: [] };
        }

        function columnName(col) {
            if (!col || typeof col !== 'object') return '';
            return String(col.name || col.title || col.label || col.header || col.field || '').trim();
        }

        function cellAt(row, index) {
            if (!row || index < 0) return 0;
            if (Array.isArray(row.cells)) return numberValue(row.cells[index]);
            if (Array.isArray(row.values)) return numberValue(row.values[index]);
            return 0;
        }

        function maxColumn(rows, index) {
            if (index < 0) return 0;
            let max = 0;
            rows.forEach(row => { max = Math.max(max, cellAt(row, index)); });
            return max;
        }

        function maxColumns(rows, indices) {
            let max = 0;
            indices.forEach(index => { max = Math.max(max, maxColumn(rows, index)); });
            return max;
        }

        function maxRowKeys(rows, pattern) {
            let max = 0;
            rows.forEach(row => {
                if (!row || typeof row !== 'object') return;
                Object.keys(row).forEach(key => {
                    if (pattern.test(key)) max = Math.max(max, numberValue(row[key]));
                });
            });
            return max;
        }

        function naturalCompare(a, b) {
            return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
        }

        function formatMonthDate(dateValue) {
            const parts = String(dateValue).split('-');
            return parts.length === 3 ? parts[2] + '-' + parts[1] + '-' + parts[0] : dateValue;
        }

        function parseDailyReport(message, dateValue) {
            const payload = reportPayload(message);
            const columns = payload.columns;
            const rows = payload.rows;
            if (!columns.length || !rows.length) return null;

            const inverterCols = [];
            const vcbCols = [];
            const lossCols = [];
            let totalIdx = -1;

            columns.forEach((col, index) => {
                const name = columnName(col);
                if (!name) return;

                if (/^\s*(?:inv|inverter)[\s_.-]*\d+/i.test(name) && !/total/i.test(name)) {
                    inverterCols.push({ name, index });
                }
                if (/inverter\s*total|inv\s*total|total\s*inverter/i.test(name)) totalIdx = index;
                if (/\bvcb\b|ht[\s_.-]*(?:panel|pannel)|ht[\s_.-]*mfm|ht[\s_.-]*meter/i.test(name)) vcbCols.push(index);
                if (/transformer\s*loss|tx\s*loss|\bloss\b/i.test(name)) lossCols.push(index);
            });

            if (!inverterCols.length) return null;
            inverterCols.sort((a, b) => naturalCompare(a.name, b.name));

            const inverters = {};
            inverterCols.forEach(col => { inverters[col.name] = maxColumn(rows, col.index); });
            const inverterSum = Object.values(inverters).reduce((sum, value) => sum + value, 0);
            const reportedTotal = maxColumn(rows, totalIdx);
            const invTotal = reportedTotal > 0 ? reportedTotal : inverterSum;

            let vcb = maxColumns(rows, vcbCols);
            if (vcb <= 0) {
                vcb = maxRowKeys(rows, /vcb|ht[_\s.-]*(?:panel|pannel|mfm|meter)/i);
            }

            let txLoss = maxColumns(rows, lossCols);
            if (txLoss <= 0) txLoss = maxRowKeys(rows, /transformer[_\s.-]*loss|tx[_\s.-]*loss/i);

            if (vcb <= 0 && invTotal > 0 && txLoss > 0 && invTotal >= txLoss) {
                vcb = invTotal - txLoss;
            }
            if (txLoss <= 0 && invTotal > 0 && vcb > 0) {
                txLoss = invTotal - vcb;
            }

            return { date: dateValue, inverters, invTotal, vcb, txLoss };
        }

        async function fetchDailyAPIFallback(run, dateValue) {
            try {
                const authToken = new URLSearchParams(window.location.search).get('token') || sessionStorage.getItem('vs_token') || '';
                const url = 'api_reports.php?tab=inv_vcb&type=daily&date=' + encodeURIComponent(dateValue) +
                    '&plant=' + encodeURIComponent(run.plant) + '&token=' + encodeURIComponent(authToken);
                const res = await fetch(url, { headers: authToken ? { 'Authorization': 'Bearer ' + authToken } : {} });
                const result = await res.json();
                if (!result || !result.success || !Array.isArray(result.data)) return null;

                const names = result.meta && Array.isArray(result.meta.inv_names) ? result.meta.inv_names : [];
                const inverters = {};
                names.forEach((name, idx) => {
                    let max = 0;
                    result.data.forEach(row => { max = Math.max(max, numberValue(row['inv' + (idx + 1) + '_kwh'])); });
                    inverters[name] = max;
                });

                let vcb = 0;
                result.data.forEach(row => { vcb = Math.max(vcb, numberValue(row.vcb_kwh)); });
                const invTotal = Object.values(inverters).reduce((sum, value) => sum + value, 0);
                if (!names.length && invTotal === 0 && vcb === 0) return null;
                return { date: dateValue, inverters, invTotal, vcb, txLoss: invTotal - vcb };
            } catch (err) {
                console.warn('Monthly daily API fallback failed for', dateValue, err);
                return null;
            }
        }

        function cancelMonthlyRun() {
            if (!monthlyRun) return;
            monthlyRun.cancelled = true;
            if (monthlyRun.timer) clearTimeout(monthlyRun.timer);
            monthlyRun = null;
            pendingReportRequest = false;
        }

        function storeDay(run, dayData) {
            if (!dayData) return;
            run.days[dayData.date] = dayData;
            Object.keys(dayData.inverters || {}).forEach(name => run.invNames.add(name));
            run.successfulDays++;
        }

        function renderMonthly(run) {
            if (!run || run.cancelled || monthlyRun !== run) return;
            const invNames = Array.from(run.invNames).sort(naturalCompare);

            if (!run.successfulDays || !invNames.length) {
                monthlyRun = null;
                pendingReportRequest = false;
                fetchReportFromAPI().catch(err => console.error(err));
                return;
            }

            const rows = run.allDates.map(dateValue => {
                const day = run.days[dateValue] || { inverters: {}, invTotal: 0, vcb: 0, txLoss: 0 };
                const row = {
                    time_label: formatMonthDate(dateValue),
                    inv_total_kwh: numberValue(day.invTotal),
                    vcb_kwh: numberValue(day.vcb),
                    tx_loss: numberValue(day.txLoss),
                    vcb_kw: 0,
                    ot: 0,
                    wt1: 0,
                    wt2: 0
                };
                invNames.forEach((name, idx) => {
                    row['inv' + (idx + 1) + '_kwh'] = numberValue(day.inverters[name]);
                    row['inv' + (idx + 1) + '_kw'] = 0;
                    row['inv' + (idx + 1) + '_temp'] = 0;
                });
                return row;
            });

            pendingReportRequest = false;
            if (wsReportTimeout) { clearTimeout(wsReportTimeout); wsReportTimeout = null; }
            lastReportData = { type: 'monthly', data: rows, meta: { inv_names: invNames, source: 'websocket_daily_history' } };
            monthlyRun = null;
            renderReportData('monthly', rows, invNames);
        }

        function advance(run) {
            if (!run || run.cancelled || monthlyRun !== run) return;
            if (run.timer) { clearTimeout(run.timer); run.timer = null; }

            run.index++;
            if (run.index >= run.requestDates.length) {
                renderMonthly(run);
                return;
            }

            const dateValue = run.requestDates[run.index];
            run.currentDate = dateValue;

            const send = () => {
                if (run.cancelled || monthlyRun !== run) return;
                if (!ws || ws.readyState !== WebSocket.OPEN) {
                    connectReportWS();
                    run.timer = setTimeout(send, 400);
                    return;
                }

                ws.send(JSON.stringify({ type: 'subscribe', unit_id: run.plant }));
                ws.send(JSON.stringify({
                    type: 'generate_report',
                    unit_id: run.plant,
                    pageName: 'inverter&vcb-daily',
                    date: dateValue
                }));
                console.log('Monthly history request:', run.plant, dateValue);

                run.timer = setTimeout(async () => {
                    if (run.cancelled || monthlyRun !== run || run.currentDate !== dateValue) return;
                    const fallback = await fetchDailyAPIFallback(run, dateValue);
                    storeDay(run, fallback);
                    advance(run);
                }, 7000);
            };
            send();
        }

        window.handleWSReportResponse = function (message) {
            const typeEl = document.getElementById('reportType');
            if (!monthlyRun || !typeEl || typeEl.value !== 'monthly') {
                return originalHandleWSReportResponse(message);
            }

            const run = monthlyRun;
            if (run.cancelled || !run.currentDate) return;

            const responseDate = (message && (message.date || message.reportDate || message.report_date)) ||
                (message && message.meta && (message.meta.date || message.meta.reportDate)) || '';
            if (responseDate) {
                const normalized = String(responseDate).slice(0, 10);
                if (/^\d{4}-\d{2}-\d{2}$/.test(normalized) && normalized !== run.currentDate) return;
            }

            const dayData = parseDailyReport(message, run.currentDate);
            if (!dayData) return;

            if (run.timer) { clearTimeout(run.timer); run.timer = null; }
            storeDay(run, dayData);
            advance(run);
        };

        window.generateReportData = function () {
            const typeEl = document.getElementById('reportType');
            if (!typeEl || typeEl.value !== 'monthly') {
                cancelMonthlyRun();
                return originalGenerateReportData();
            }

            const monthEl = document.getElementById('monthSelect');
            const plantEl = document.getElementById('plantSelect');
            const tbody = document.getElementById('reportTableBody');
            const displayDate = document.getElementById('displayDate');
            const selectedMonth = monthEl ? monthEl.value : '';
            const plant = plantEl ? plantEl.value : '';
            if (!selectedMonth || !plant) return originalGenerateReportData();

            if (plant === 'all') {
                cancelMonthlyRun();
                return originalGenerateReportData();
            }

            const runKey = plant + '|' + selectedMonth;
            if (monthlyRun && !monthlyRun.cancelled && monthlyRun.key === runKey) return;
            cancelMonthlyRun();

            const monthStart = new Date(selectedMonth + '-01T00:00:00');
            if (isNaN(monthStart.getTime())) return originalGenerateReportData();

            const year = monthStart.getFullYear();
            const monthIndex = monthStart.getMonth();
            const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();
            const allDates = [];
            for (let day = 1; day <= daysInMonth; day++) {
                allDates.push(year + '-' + String(monthIndex + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0'));
            }

            const now = new Date();
            let lastHistoryDay = daysInMonth;
            if (year === now.getFullYear() && monthIndex === now.getMonth()) lastHistoryDay = now.getDate();
            else if (monthStart > new Date(now.getFullYear(), now.getMonth(), 1)) lastHistoryDay = 0;

            if (displayDate) displayDate.innerText = monthStart.toLocaleDateString('en-IN', { year: 'numeric', month: 'long' });
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="30" class="py-12 bg-white"><div class="flex flex-col items-center justify-center"><div class="w-10 h-10 border-4 border-gray-200 border-t-blue-600 rounded-full animate-spin"></div><p class="mt-3 text-sm font-bold text-gray-600">Loading monthly SCADA history...</p></div></td></tr>';
            }

            monthlyRun = {
                key: runKey,
                plant,
                allDates,
                requestDates: allDates.slice(0, lastHistoryDay),
                index: -1,
                currentDate: '',
                days: {},
                invNames: new Set(),
                successfulDays: 0,
                timer: null,
                cancelled: false
            };
            pendingReportRequest = true;
            connectReportWS();
            advance(monthlyRun);
        };
    }

    document.addEventListener('DOMContentLoaded', function () {
        initializeSidebar();
        installMonthlyReportsFix();
    });
    const observer = new MutationObserver(initializeSidebar);
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
