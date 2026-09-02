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
        if (window.__monthlyReportsSocketOnly) return;
        if (typeof window.generateReportData !== 'function' || typeof window.handleWSReportResponse !== 'function') return;

        window.__monthlyReportsSocketOnly = true;

        const originalGenerateReportData = window.generateReportData;
        const originalHandleWSReportResponse = window.handleWSReportResponse;
        let monthlyRun = null;

        function numberValue(value) {
            if (value === undefined || value === null || value === '') return 0;
            if (typeof value === 'number') return Number.isFinite(value) ? value : 0;
            if (typeof value === 'object') {
                for (const key of ['value', 'rawValue', 'displayValue', 'formattedValue', 'text']) {
                    if (Object.prototype.hasOwnProperty.call(value, key)) return numberValue(value[key]);
                }
                return 0;
            }
            const match = String(value).replace(/,/g, '').match(/[-+]?\d*\.?\d+(?:[eE][-+]?\d+)?/);
            if (!match) return 0;
            const n = parseFloat(match[0]);
            return Number.isFinite(n) ? n : 0;
        }

        function reportPayload(message) {
            if (!message || typeof message !== 'object') return { columns: [], rows: [] };
            if (Array.isArray(message.columns) && Array.isArray(message.rows)) {
                return { columns: message.columns, rows: message.rows };
            }
            if (message.reportData && typeof message.reportData === 'object') {
                return {
                    columns: Array.isArray(message.reportData.columns) ? message.reportData.columns : [],
                    rows: Array.isArray(message.reportData.rows) ? message.reportData.rows : []
                };
            }
            return { columns: [], rows: [] };
        }

        function columnName(column) {
            if (!column || typeof column !== 'object') return '';
            return String(column.name || column.title || column.label || column.header || column.field || '').trim();
        }

        function cellValue(row, index) {
            if (!row || index < 0) return 0;
            if (Array.isArray(row.cells)) return numberValue(row.cells[index]);
            if (Array.isArray(row.values)) return numberValue(row.values[index]);
            return 0;
        }

        function maxActualValue(rows, indices) {
            if (!indices.length) return 0;
            let max = 0;
            rows.forEach(row => {
                indices.forEach(index => {
                    max = Math.max(max, cellValue(row, index));
                });
            });
            return max;
        }

        function naturalCompare(a, b) {
            return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
        }

        function formatMonthDate(dateValue) {
            const p = String(dateValue).split('-');
            return p.length === 3 ? p[2] + '-' + p[1] + '-' + p[0] : dateValue;
        }

        function parseHistoricalDay(message, dateValue) {
            const payload = reportPayload(message);
            const columns = payload.columns;
            const rows = payload.rows;
            if (!columns.length || !rows.length) return null;

            const inverterColumns = [];
            const vcbIndices = [];
            const lossIndices = [];
            const totalIndices = [];

            columns.forEach((column, index) => {
                const name = columnName(column);
                if (!name) return;

                if (/^\s*(?:inv|inverter)[\s_.-]*\d+/i.test(name) && !/total/i.test(name)) {
                    inverterColumns.push({ name, index });
                }
                if (/inverter\s*total|inv\s*total|total\s*inverter/i.test(name)) totalIndices.push(index);
                if (/\bvcb\b|ht[\s_.-]*(?:panel|pannel|mfm|meter)/i.test(name)) vcbIndices.push(index);
                if (/transformer\s*loss|tx\s*loss|\bloss\b/i.test(name)) lossIndices.push(index);
            });

            if (!inverterColumns.length && !vcbIndices.length && !totalIndices.length) return null;
            inverterColumns.sort((a, b) => naturalCompare(a.name, b.name));

            const inverters = {};
            inverterColumns.forEach(column => {
                inverters[column.name] = maxActualValue(rows, [column.index]);
            });

            return {
                date: dateValue,
                inverters,
                invTotal: maxActualValue(rows, totalIndices),
                vcb: maxActualValue(rows, vcbIndices),
                txLoss: maxActualValue(rows, lossIndices)
            };
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
            Object.keys(dayData.inverters).forEach(name => run.invNames.add(name));
        }

        function renderMonthly(run) {
            if (!run || run.cancelled || monthlyRun !== run) return;

            const dates = Object.keys(run.days).sort();
            const invNames = Array.from(run.invNames).sort(naturalCompare);
            pendingReportRequest = false;
            monthlyRun = null;

            if (!dates.length) {
                lastReportData = null;
                const tbody = document.getElementById('reportTableBody');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="30" class="py-10 text-center text-gray-500">No historical WebSocket report data returned for this month.</td></tr>';
                }
                return;
            }

            const rows = dates.map(dateValue => {
                const day = run.days[dateValue];
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
                invNames.forEach((name, index) => {
                    row['inv' + (index + 1) + '_kwh'] = Object.prototype.hasOwnProperty.call(day.inverters, name)
                        ? numberValue(day.inverters[name])
                        : 0;
                    row['inv' + (index + 1) + '_kw'] = 0;
                    row['inv' + (index + 1) + '_temp'] = 0;
                });
                return row;
            });

            lastReportData = {
                type: 'monthly',
                data: rows,
                meta: {
                    inv_names: invNames,
                    source: 'websocket_historical_report_only'
                }
            };
            renderReportData('monthly', rows, invNames);
        }

        function updateProgress(run) {
            const tbody = document.getElementById('reportTableBody');
            if (!tbody) return;
            const current = Math.min(run.index + 1, run.requestDates.length);
            tbody.innerHTML = '<tr><td colspan="30" class="py-12 text-center text-gray-500">Loading historical WebSocket data ' + current + '/' + run.requestDates.length + '...</td></tr>';
        }

        function advanceMonthlyRun(run) {
            if (!run || run.cancelled || monthlyRun !== run) return;
            if (run.timer) {
                clearTimeout(run.timer);
                run.timer = null;
            }

            run.index++;
            if (run.index >= run.requestDates.length) {
                renderMonthly(run);
                return;
            }

            const dateValue = run.requestDates[run.index];
            run.currentDate = dateValue;
            updateProgress(run);

            const send = () => {
                if (run.cancelled || monthlyRun !== run) return;
                if (!ws || ws.readyState !== WebSocket.OPEN) {
                    connectReportWS();
                    run.timer = setTimeout(send, 500);
                    return;
                }

                ws.send(JSON.stringify({ type: 'subscribe', unit_id: run.plant }));
                ws.send(JSON.stringify({
                    type: 'generate_report',
                    unit_id: run.plant,
                    pageName: 'inverter&vcb-daily',
                    date: dateValue
                }));
                console.log('Monthly WS historical request:', run.plant, dateValue);

                run.timer = setTimeout(() => {
                    if (run.cancelled || monthlyRun !== run || run.currentDate !== dateValue) return;
                    console.warn('No WS historical report returned for', run.plant, dateValue);
                    advanceMonthlyRun(run);
                }, 10000);
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

            const responseDate =
                (message && (message.date || message.reportDate || message.report_date)) ||
                (message && message.meta && (message.meta.date || message.meta.reportDate || message.meta.report_date)) ||
                (message && message.reportData && (message.reportData.date || message.reportData.reportDate || message.reportData.report_date)) ||
                '';

            if (responseDate) {
                const normalized = String(responseDate).slice(0, 10);
                if (/^\d{4}-\d{2}-\d{2}$/.test(normalized) && normalized !== run.currentDate) return;
            }

            const dayData = parseHistoricalDay(message, run.currentDate);
            if (!dayData) return;

            if (run.timer) {
                clearTimeout(run.timer);
                run.timer = null;
            }
            storeDay(run, dayData);
            advanceMonthlyRun(run);
        };

        window.generateReportData = function () {
            const typeEl = document.getElementById('reportType');
            if (!typeEl || typeEl.value !== 'monthly') {
                cancelMonthlyRun();
                return originalGenerateReportData();
            }

            const monthEl = document.getElementById('monthSelect');
            const plantEl = document.getElementById('plantSelect');
            const selectedMonth = monthEl ? monthEl.value : '';
            const plant = plantEl ? plantEl.value : '';
            const tbody = document.getElementById('reportTableBody');
            const displayDate = document.getElementById('displayDate');

            if (!selectedMonth || !plant) return;

            if (plant === 'all') {
                cancelMonthlyRun();
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="30" class="py-10 text-center text-gray-500">Select one plant to load its live historical WebSocket report.</td></tr>';
                }
                return;
            }

            const key = plant + '|' + selectedMonth;
            if (monthlyRun && !monthlyRun.cancelled && monthlyRun.key === key) return;
            cancelMonthlyRun();

            const monthStart = new Date(selectedMonth + '-01T00:00:00');
            if (isNaN(monthStart.getTime())) return;

            if (displayDate) {
                displayDate.innerText = monthStart.toLocaleDateString('en-IN', { year: 'numeric', month: 'long' });
            }

            const year = monthStart.getFullYear();
            const monthIndex = monthStart.getMonth();
            const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();
            const now = new Date();
            let lastDay = daysInMonth;

            if (year === now.getFullYear() && monthIndex === now.getMonth()) {
                lastDay = now.getDate();
            } else if (monthStart > new Date(now.getFullYear(), now.getMonth(), 1)) {
                lastDay = 0;
            }

            const requestDates = [];
            for (let day = 1; day <= lastDay; day++) {
                requestDates.push(
                    year + '-' +
                    String(monthIndex + 1).padStart(2, '0') + '-' +
                    String(day).padStart(2, '0')
                );
            }

            if (!requestDates.length) {
                if (tbody) tbody.innerHTML = '<tr><td colspan="30" class="py-10 text-center text-gray-500">No historical dates available for this month.</td></tr>';
                return;
            }

            if (wsReportTimeout) {
                clearTimeout(wsReportTimeout);
                wsReportTimeout = null;
            }

            monthlyRun = {
                key,
                plant,
                requestDates,
                index: -1,
                currentDate: null,
                days: {},
                invNames: new Set(),
                timer: null,
                cancelled: false
            };

            pendingReportRequest = true;
            connectReportWS();
            advanceMonthlyRun(monthlyRun);
        };
    }

    installMonthlyReportsFix();
    document.addEventListener('DOMContentLoaded', function () {
        initializeSidebar();
        installMonthlyReportsFix();
    });
    const observer = new MutationObserver(initializeSidebar);
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
