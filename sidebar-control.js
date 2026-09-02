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

        // Keep the same top-right control position even if older sidebar markup
        // remains in the browser cache.
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
            if (icon) icon.className = collapsed
                ? 'fa-solid fa-angles-right'
                : 'fa-solid fa-angles-left';
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
        if (window.__monthlyReportsHistoricalFix) return;
        if (typeof window.generateReportData !== 'function' ||
            typeof window.handleWSReportResponse !== 'function') return;

        window.__monthlyReportsHistoricalFix = true;

        const originalGenerateReportData = window.generateReportData;
        const originalHandleWSReportResponse = window.handleWSReportResponse;
        let monthlyRun = null;

        function cancelMonthlyRun() {
            if (!monthlyRun) return;
            monthlyRun.cancelled = true;
            if (monthlyRun.timer) clearTimeout(monthlyRun.timer);
            monthlyRun = null;
            pendingReportRequest = false;
        }

        function numberValue(value) {
            if (value === undefined || value === null || value === '') return 0;
            const n = Number(String(value).replace(/,/g, '').trim());
            return Number.isFinite(n) ? n : 0;
        }

        function maxCell(rows, index) {
            if (index < 0) return 0;
            let max = 0;
            rows.forEach(row => {
                const cells = row && Array.isArray(row.cells) ? row.cells : [];
                max = Math.max(max, numberValue(cells[index]));
            });
            return max;
        }

        function naturalCompare(a, b) {
            return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
        }

        function formatMonthDate(dateValue) {
            const parts = String(dateValue).split('-');
            if (parts.length !== 3) return dateValue;
            return parts[2] + '-' + parts[1] + '-' + parts[0];
        }

        function parseDailyWSReport(d, dateValue) {
            const columns = Array.isArray(d.columns) ? d.columns : [];
            const rows = Array.isArray(d.rows) ? d.rows : [];
            if (!columns.length || !rows.length) return null;

            const inverterCols = [];
            columns.forEach((col, index) => {
                const name = String((col && col.name) || '').trim();
                if (/^INV-\d+/i.test(name) && !(col && col.isHidden)) {
                    inverterCols.push({ name, index });
                }
            });
            if (!inverterCols.length) return null;

            const totalIdx = columns.findIndex(col => /inverter.*total/i.test(String((col && col.name) || '')));
            const vcbIdx = columns.findIndex(col =>
                /(ht[\s._-]*(panel|pannel)|vcb)/i.test(String((col && col.name) || '')) &&
                !(col && col.isHidden)
            );

            const inverters = {};
            inverterCols.forEach(col => {
                inverters[col.name] = maxCell(rows, col.index);
            });

            const inverterSum = Object.values(inverters).reduce((sum, value) => sum + value, 0);
            const reportedTotal = maxCell(rows, totalIdx);
            const invTotal = reportedTotal > 0 ? reportedTotal : inverterSum;
            const vcb = maxCell(rows, vcbIdx);

            return {
                date: dateValue,
                inverters,
                invTotal,
                vcb,
                txLoss: invTotal - vcb
            };
        }

        async function fetchDailyAPIFallback(run, dateValue) {
            try {
                const authToken = new URLSearchParams(window.location.search).get('token') ||
                    sessionStorage.getItem('vs_token') || '';
                const url = 'api_reports.php?tab=inv_vcb&type=daily&date=' +
                    encodeURIComponent(dateValue) + '&plant=' +
                    encodeURIComponent(run.plant) + '&token=' +
                    encodeURIComponent(authToken);
                const res = await fetch(url, {
                    headers: authToken ? { 'Authorization': 'Bearer ' + authToken } : {}
                });
                const result = await res.json();
                if (!result || !result.success || !Array.isArray(result.data)) return null;

                const names = result.meta && Array.isArray(result.meta.inv_names)
                    ? result.meta.inv_names : [];
                const inverters = {};
                names.forEach((name, idx) => {
                    let max = 0;
                    result.data.forEach(row => {
                        max = Math.max(max, numberValue(row['inv' + (idx + 1) + '_kwh']));
                    });
                    inverters[name] = max;
                });

                let vcb = 0;
                result.data.forEach(row => {
                    vcb = Math.max(vcb, numberValue(row.vcb_kwh));
                });

                const invTotal = Object.values(inverters).reduce((sum, value) => sum + value, 0);
                if (!names.length && invTotal === 0 && vcb === 0) return null;

                return {
                    date: dateValue,
                    inverters,
                    invTotal,
                    vcb,
                    txLoss: invTotal - vcb
                };
            } catch (err) {
                console.warn('Monthly daily API fallback failed for', dateValue, err);
                return null;
            }
        }

        function storeMonthlyDay(run, dayData) {
            if (!dayData) return;
            run.days[dayData.date] = dayData;
            Object.keys(dayData.inverters || {}).forEach(name => run.invNames.add(name));
            run.successfulDays++;
        }

        function renderMonthlyRun(run) {
            if (!run || run.cancelled || monthlyRun !== run) return;

            const invNames = Array.from(run.invNames).sort(naturalCompare);
            if (!run.successfulDays || !invNames.length) {
                monthlyRun = null;
                pendingReportRequest = false;
                fetchReportFromAPI().catch(err => {
                    const tbody = document.getElementById('reportTableBody');
                    if (tbody) {
                        tbody.innerHTML = '<tr><td colspan="30" class="py-10 text-center">' +
                            '<div class="text-red-500 font-bold mb-1">Data Error</div>' +
                            '<div class="text-gray-400 text-xs">' + err.message + '</div></td></tr>';
                    }
                    console.error(err);
                });
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
            if (wsReportTimeout) {
                clearTimeout(wsReportTimeout);
                wsReportTimeout = null;
            }
            lastReportData = {
                type: 'monthly',
                data: rows,
                meta: {
                    inv_names: invNames,
                    source: 'websocket_daily_history'
                }
            };
            monthlyRun = null;
            renderReportData('monthly', rows, invNames);
        }

        function advanceMonthlyRun(run) {
            if (!run || run.cancelled || monthlyRun !== run) return;
            if (run.timer) {
                clearTimeout(run.timer);
                run.timer = null;
            }

            run.index++;
            if (run.index >= run.requestDates.length) {
                renderMonthlyRun(run);
                return;
            }

            const dateValue = run.requestDates[run.index];
            run.currentDate = dateValue;

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
                console.log('WS: monthly history requesting daily report', run.plant, dateValue);

                run.timer = setTimeout(async () => {
                    if (run.cancelled || monthlyRun !== run || run.currentDate !== dateValue) return;
                    const fallback = await fetchDailyAPIFallback(run, dateValue);
                    storeMonthlyDay(run, fallback);
                    advanceMonthlyRun(run);
                }, 8000);
            };

            send();
        }

        window.handleWSReportResponse = function (d) {
            const typeEl = document.getElementById('reportType');

            if (!monthlyRun || !typeEl || typeEl.value !== 'monthly') {
                return originalHandleWSReportResponse(d);
            }

            const run = monthlyRun;
            if (run.cancelled || !run.currentDate) return;

            const responseDate =
                (d && (d.date || d.reportDate || d.report_date)) ||
                (d && d.meta && (d.meta.date || d.meta.reportDate)) ||
                '';

            if (responseDate) {
                const normalizedResponseDate = String(responseDate).slice(0, 10);
                if (/^\d{4}-\d{2}-\d{2}$/.test(normalizedResponseDate) &&
                    normalizedResponseDate !== run.currentDate) {
                    return;
                }
            }

            const dayData = parseDailyWSReport(d, run.currentDate);
            if (!dayData) return;

            if (run.timer) {
                clearTimeout(run.timer);
                run.timer = null;
            }
            storeMonthlyDay(run, dayData);
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
                allDates.push(
                    year + '-' +
                    String(monthIndex + 1).padStart(2, '0') + '-' +
                    String(day).padStart(2, '0')
                );
            }

            const now = new Date();
            let lastHistoryDay = daysInMonth;
            if (year === now.getFullYear() && monthIndex === now.getMonth()) {
                lastHistoryDay = now.getDate();
            } else if (monthStart > new Date(now.getFullYear(), now.getMonth(), 1)) {
                lastHistoryDay = 0;
            }
            const requestDates = allDates.slice(0, lastHistoryDay);

            if (displayDate) {
                displayDate.innerText = monthStart.toLocaleDateString('en-IN', {
                    year: 'numeric',
                    month: 'long'
                });
            }
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="30" class="py-12 bg-white">' +
                    '<div class="flex flex-col items-center justify-center">' +
                    '<div class="w-10 h-10 border-4 border-gray-200 border-t-blue-600 rounded-full animate-spin"></div>' +
                    '<p class="mt-3 text-sm font-bold text-gray-600">Loading each day from SCADA history...</p>' +
                    '</div></td></tr>';
            }

            if (!requestDates.length) {
                pendingReportRequest = false;
                fetchReportFromAPI().catch(console.error);
                return;
            }

            monthlyRun = {
                key: runKey,
                plant,
                selectedMonth,
                allDates,
                requestDates,
                days: {},
                invNames: new Set(),
                successfulDays: 0,
                index: -1,
                currentDate: '',
                timer: null,
                cancelled: false
            };

            pendingReportRequest = true;
            if (wsReportTimeout) {
                clearTimeout(wsReportTimeout);
                wsReportTimeout = null;
            }
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
