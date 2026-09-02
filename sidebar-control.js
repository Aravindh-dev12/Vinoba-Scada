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
        if (typeof window.generateReportData !== 'function') return;

        window.__monthlyReportsHistoricalFix = true;
        const originalGenerateReportData = window.generateReportData;

        window.generateReportData = function () {
            const typeEl = document.getElementById('reportType');
            if (!typeEl || typeEl.value !== 'monthly') {
                return originalGenerateReportData();
            }

            const monthEl = document.getElementById('monthSelect');
            const plantEl = document.getElementById('plantSelect');
            const tbody = document.getElementById('reportTableBody');
            const displayDate = document.getElementById('displayDate');
            const selectedMonth = monthEl ? monthEl.value : '';
            const plant = plantEl ? plantEl.value : '';

            if (!selectedMonth || !plant) return originalGenerateReportData();

            const monthDate = new Date(selectedMonth + '-01T00:00:00');
            if (displayDate && !isNaN(monthDate.getTime())) {
                displayDate.innerText = monthDate.toLocaleDateString('en-IN', { year: 'numeric', month: 'long' });
            }
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="30" class="py-12 bg-white"><div class="flex flex-col items-center justify-center"><div class="w-10 h-10 border-4 border-gray-200 border-t-blue-600 rounded-full animate-spin"></div><p class="mt-3 text-sm font-bold text-gray-600">Fetching monthly historical report...</p></div></td></tr>';
            }

            // The report WebSocket subscribes to real plant IDs. Keep the existing
            // DB aggregation for the synthetic admin "all" selection.
            if (plant === 'all') {
                pendingReportRequest = false;
                if (wsReportTimeout) { clearTimeout(wsReportTimeout); wsReportTimeout = null; }
                fetchReportFromAPI().catch(err => {
                    if (tbody) tbody.innerHTML = '<tr><td colspan="30" class="py-10 text-center"><div class="text-red-500 font-bold mb-1">Data Error</div><div class="text-gray-400 text-xs">' + err.message + '</div></td></tr>';
                    console.error(err);
                });
                return;
            }

            pendingReportRequest = true;
            if (wsReportTimeout) { clearTimeout(wsReportTimeout); wsReportTimeout = null; }
            connectReportWS();

            function sendMonthlyReport(dateValue) {
                if (!ws || ws.readyState !== WebSocket.OPEN || !pendingReportRequest) return false;
                ws.send(JSON.stringify({ type: 'subscribe', unit_id: plant }));
                ws.send(JSON.stringify({
                    type: 'generate_report',
                    unit_id: plant,
                    pageName: 'inverter&vcb-monthly',
                    date: dateValue
                }));
                console.log('WS: requested monthly historical report for', plant, dateValue);
                return true;
            }

            if (!sendMonthlyReport(selectedMonth)) {
                setTimeout(() => sendMonthlyReport(selectedMonth), 1500);
            }

            // Some report-server builds expect a full date even for a monthly page.
            // Retry with the first day before using the DB fallback.
            setTimeout(() => {
                if (pendingReportRequest) sendMonthlyReport(selectedMonth + '-01');
            }, 5000);

            wsReportTimeout = setTimeout(() => {
                if (!pendingReportRequest) return;
                console.log('Monthly WS report timeout, falling back to DB aggregate');
                pendingReportRequest = false;
                fetchReportFromAPI().catch(err => {
                    if (tbody) tbody.innerHTML = '<tr><td colspan="30" class="py-10 text-center"><div class="text-red-500 font-bold mb-1">Data Error</div><div class="text-gray-400 text-xs">' + err.message + '</div></td></tr>';
                    console.error(err);
                });
            }, 15000);
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
