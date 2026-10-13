<?php
require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Collections - GoCa$h</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .page-shell {
            display: grid;
            gap: 24px;
        }
        .page-card {
            background: var(--card-bg);
            border-radius: var(--radius-main);
            box-shadow: var(--shadow-soft);
            padding: 28px;
            transition: var(--transition);
        }
        .page-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-3px);
        }
        .page-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .page-card-header h2 {
            margin: 0;
            font-size: 20px;
            color: var(--text-main);
        }
        .page-card-header p {
            margin: 8px 0 0;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }
        /* Shared summary card styles are now global */
        .summary-card {
            border-radius: var(--radius-main);
            padding: 22px;
            background: #F8FAFC;
            border: 1px solid #E5ECF4;
            transition: var(--transition);
        }
        .summary-card.primary {
            background: linear-gradient(135deg, #0F766E, #14B8A6);
            border-color: transparent;
        }
        .summary-label {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.45px;
            color: #64748B;
            margin-bottom: 10px;
        }
        .summary-card.primary .summary-label {
            color: rgba(255, 255, 255, 0.78);
        }
        .summary-value {
            font-size: 28px;
            font-weight: 800;
            color: #0F172A;
        }
        .summary-card.primary .summary-value {
            color: white;
        }
        .calendar-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .calendar-month-nav {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .calendar-month-label {
            min-width: 190px;
            text-align: center;
            font-size: 18px;
            font-weight: 800;
            color: var(--text-main);
        }
        .calendar-nav-btn {
            width: 42px;
            height: 42px;
            border: 1px solid #D7DEE7;
            border-radius: 12px;
            background: white;
            color: #0F172A;
            cursor: pointer;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.05);
        }
        .calendar-nav-btn:hover {
            background: #F8FAFC;
        }
        .calendar-weekdays,
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 12px;
        }
        .calendar-weekday {
            text-align: center;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.45px;
            color: #64748B;
            padding: 6px 0;
        }
        .calendar-day {
            min-height: 118px;
            border: 1px solid #E2E8F0;
            border-radius: 18px;
            padding: 12px;
            background: #FCFDFE;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            cursor: pointer;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }
        .calendar-day:hover {
            transform: translateY(-2px);
            border-color: #14B8A6;
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.08);
        }
        .calendar-day:focus-visible {
            outline: 3px solid rgba(20, 184, 166, 0.28);
            outline-offset: 2px;
        }
        .calendar-day.has-data {
            background: linear-gradient(180deg, rgba(20, 184, 166, 0.09), #ffffff);
        }
        .calendar-day.is-selected {
            border-color: #0F766E;
            box-shadow: 0 14px 28px rgba(20, 184, 166, 0.18);
        }
        .calendar-day.is-empty {
            visibility: hidden;
            pointer-events: none;
        }
        /* Sunday — visible but disabled (GoCash: no collections on Sunday) */
        .calendar-day.is-sunday {
            background-color: #f5f5f5;
            pointer-events: none;
            opacity: 0.55;
            cursor: default;
        }
        .calendar-day.is-sunday .calendar-day-number,
        .calendar-day.is-sunday .day-number {
            color: #aaa;
        }
        .calendar-day-number {
            font-size: 18px;
            font-weight: 800;
            color: #0F172A;
        }
        .day-number {
            font-size: 18px;
            font-weight: 800;
            color: #0F172A;
        }
        .calendar-day-total {
            font-size: 14px;
            font-weight: 800;
            color: #0F766E;
        }
        .amount {
            font-size: 14px;
            font-weight: 800;
            color: #0F766E;
        }
        .calendar-day-meta,
        .calendar-day-empty {
            font-size: 12px;
            color: #64748B;
        }
        .calendar-loading,
        .empty-state {
            text-align: center;
            padding: 34px 18px;
            color: var(--text-muted);
            font-weight: 600;
        }
        .selected-date {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(90, 141, 238, 0.1);
            color: #5A8DEE;
            font-size: 13px;
            font-weight: 700;
        }
        .detail-table-wrap {
            border: 1px solid #E8ECF0;
            border-radius: 18px;
            overflow: hidden;
        }
        .collection-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(15, 23, 42, 0.52);
            z-index: 1200;
        }
        .collection-modal-dialog {
            width: min(720px, 100%);
            max-height: calc(100vh - 48px);
            overflow: auto;
            background: #FFFFFF;
            border-radius: 24px;
            box-shadow: 0 28px 60px rgba(15, 23, 42, 0.22);
            padding: 28px;
            position: relative;
        }
        .collection-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
        }
        .collection-modal-title {
            margin: 0;
            font-size: 22px;
            color: var(--text-main);
        }
        .collection-modal-copy {
            margin: 8px 0 0;
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
        }
        .collection-modal-close {
            width: 42px;
            height: 42px;
            border: 1px solid #D7DEE7;
            border-radius: 12px;
            background: #FFFFFF;
            color: #0F172A;
            cursor: pointer;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.05);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .collection-modal-close:hover {
            background: #F8FAFC;
        }
        .collection-modal-body {
            display: grid;
            gap: 18px;
        }
        .modal-status-card {
            border-radius: 18px;
            padding: 18px 20px;
            background: #F8FAFC;
            border: 1px solid #E5ECF4;
        }
        .modal-status-label {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.45px;
            color: #64748B;
            margin-bottom: 8px;
        }
        .modal-status-value {
            font-size: 24px;
            font-weight: 800;
            color: #0F172A;
        }
        .modal-results-table {
            width: 100%;
            border-collapse: collapse;
        }
        .modal-results-table th,
        .modal-results-table td {
            padding: 14px 18px;
            border-bottom: 1px solid #E8ECF0;
            text-align: left;
        }
        .modal-results-table th:last-child,
        .modal-results-table td:last-child {
            text-align: right;
        }
        .modal-results-table tbody tr:last-child td {
            border-bottom: 0;
        }
        .collector-total-name {
            font-weight: 700;
            color: var(--text-main);
        }
        @media (max-width: 1024px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 768px) {
            .calendar-weekdays,
            .calendar-grid {
                gap: 8px;
            }
            .calendar-day {
                min-height: 102px;
            }
            .calendar-month-label {
                min-width: 0;
                width: 100%;
                order: -1;
            }
            .collection-modal {
                padding: 14px;
            }
            .collection-modal-dialog {
                padding: 22px;
            }
        }
    </style>
</head>
<body>
<?php include 'layout/sidebar.php'; ?>

    <main class="main-wrapper">
        <header class="top-header">
            <div class="greeting">
                <h1>Daily Collections</h1>
                <p>Review daily collection totals per collector using the same payment data shown in the collector app.</p>
            </div>

            <div class="header-actions">
                <button class="icon-btn icon-badge-wrap" id="notifBellBtn"><i class="ph ph-bell"></i></button>
                <button class="icon-btn" onclick="window.location.href='profile.php'"><i class="ph ph-user"></i></button>
            </div>
        </header>

        <div class="content">
            <div class="page-shell">
                <section class="page-card">
                    <div class="page-card-header">
                        <div>
                            <h2>Collection Calendar</h2>
                            <p>Click any date to load each collector&apos;s total collection for that day.</p>
                        </div>

                        <div class="calendar-month-nav">
                            <button type="button" class="calendar-nav-btn" id="calendarPrevBtn" aria-label="Previous month">
                                <i class="ph ph-caret-left"></i>
                            </button>
                            <div class="calendar-month-label" id="calendarMonthLabel">Loading...</div>
                            <button type="button" class="calendar-nav-btn" id="calendarNextBtn" aria-label="Next month">
                                <i class="ph ph-caret-right"></i>
                            </button>
                        </div>
                    </div>

                    <div class="calendar-weekdays">
                        <div class="calendar-weekday">Sun</div>
                        <div class="calendar-weekday">Mon</div>
                        <div class="calendar-weekday">Tue</div>
                        <div class="calendar-weekday">Wed</div>
                        <div class="calendar-weekday">Thu</div>
                        <div class="calendar-weekday">Fri</div>
                        <div class="calendar-weekday">Sat</div>
                    </div>

                    <div class="calendar-grid" id="collectionCalendarGrid">
                        <div class="calendar-loading">Loading calendar...</div>
                    </div>
                </section>

                <section class="page-card">
                    <div class="page-card-header">
                        <div>
                            <h2>Collector Daily Summary</h2>
                            <p>Daily totals grouped from the existing `payments` table.</p>
                        </div>

                        <div class="selected-date" id="selectedDateBadge">
                            <i class="ph ph-calendar-blank"></i>
                            Select a date
                        </div>
                    </div>

                    <div class="summary-grid" style="margin-bottom: 22px;">
                        <div class="summary-card primary">
                            <div class="summary-label">Total Collection</div>
                            <div class="summary-value" id="selectedTotalCollection">PHP 0.00</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Transactions</div>
                            <div class="summary-value" id="selectedTransactionCount">0</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Collectors</div>
                            <div class="summary-value" id="selectedCollectorCount">0</div>
                        </div>
                    </div>

                    <div class="detail-table-wrap">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Collector Name</th>
                                    <th>Total Collection</th>
                                </tr>
                            </thead>
                            <tbody id="collectorTotalsTableBody">
                                <tr>
                                    <td colspan="2" class="empty-state">Select a date from the calendar to view collector totals.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <div id="collectionModal" class="collection-modal" style="display:none;" aria-hidden="true">
        <div class="collection-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="collectionModalTitle">
            <div class="collection-modal-header">
                <div>
                    <h3 class="collection-modal-title" id="collectionModalTitle">Collector Daily Summary</h3>
                    <p class="collection-modal-copy" id="collectionModalDate">Select a calendar date to load totals.</p>
                </div>

                <button type="button" class="collection-modal-close" id="closeModal" aria-label="Close summary modal">
                    <i class="ph ph-x"></i>
                </button>
            </div>

            <div class="collection-modal-body">
                <div class="modal-status-card">
                    <div class="modal-status-label">Total Collection</div>
                    <div class="modal-status-value" id="modalTotalCollection">PHP 0.00</div>
                </div>

                <div id="collectionResults" class="detail-table-wrap">
                    <div class="empty-state">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function formatCurrency(value) {
            return 'PHP ' + Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function buildMonthKey(year, month) {
            return `${year}-${String(month).padStart(2, '0')}`;
        }

        function currentMonthKey() {
            const now = new Date();
            return buildMonthKey(now.getFullYear(), now.getMonth() + 1);
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        const calendarState = {
            currentMonth: currentMonthKey(),
            selectedDate: null,
            monthData: {}
        };

        function formatDisplayDate(dateString) {
            const date = new Date(dateString + 'T00:00:00');
            return new Intl.DateTimeFormat(undefined, {
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            }).format(date);
        }

        function formatMonthLabel(monthKey) {
            const [year, month] = monthKey.split('-').map(Number);
            return new Intl.DateTimeFormat(undefined, {
                month: 'long',
                year: 'numeric'
            }).format(new Date(year, month - 1, 1));
        }

        function shiftMonth(monthKey, delta) {
            const [year, month] = monthKey.split('-').map(Number);
            const nextMonth = new Date(year, month - 1 + delta, 1);
            return buildMonthKey(nextMonth.getFullYear(), nextMonth.getMonth() + 1);
        }

        function setModalVisibility(isOpen) {
            const modal = document.getElementById('collectionModal');
            modal.style.display = isOpen ? 'flex' : 'none';
            modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        }

        function openCollectionModal(dateKey) {
            const results = document.getElementById('collectionResults');
            document.getElementById('collectionModalDate').textContent = 'Date: ' + formatDisplayDate(dateKey);
            document.getElementById('modalTotalCollection').textContent = 'Loading...';
            results.innerHTML = '<div class="empty-state">Loading data...</div>';
            setModalVisibility(true);
        }

        function closeCollectionModal() {
            setModalVisibility(false);
        }

        function getCalendarDayFromEventTarget(target) {
            if (target instanceof Element) {
                return target.closest('.calendar-day[data-date]');
            }

            if (target && target.parentElement instanceof Element) {
                return target.parentElement.closest('.calendar-day[data-date]');
            }

            return null;
        }

        function renderCollectorResultsTable(collectors) {
            if (!Array.isArray(collectors) || collectors.length === 0) {
                return '<div class="empty-state">No collections recorded.</div>';
            }

            const rows = collectors.map(function(collector) {
                const collectorName = collector.name || collector.collector_name || 'Unknown Collector';
                const collectorTotal = collector.total || collector.total_collection || 0;

                return `
                    <tr>
                        <td class="collector-total-name">${escapeHtml(collectorName)}</td>
                        <td style="font-weight:700; color:var(--text-main);">${formatCurrency(collectorTotal)}</td>
                    </tr>
                `;
            }).join('');

            return `
                <table class="modal-results-table">
                    <thead>
                        <tr>
                            <th>Collector</th>
                            <th>Total Collected</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            `;
        }

        function renderCalendarGrid() {
            const grid = document.getElementById('collectionCalendarGrid');
            const [year, month] = calendarState.currentMonth.split('-').map(Number);
            const firstDay = new Date(year, month - 1, 1);
            const daysInMonth = new Date(year, month, 0).getDate();
            const leadingEmptyDays = firstDay.getDay();

            document.getElementById('calendarMonthLabel').textContent = formatMonthLabel(calendarState.currentMonth);
            grid.innerHTML = '';

            for (let index = 0; index < leadingEmptyDays; index += 1) {
                const emptyCell = document.createElement('div');
                emptyCell.className = 'calendar-day is-empty';
                grid.appendChild(emptyCell);
            }

            for (let day = 1; day <= daysInMonth; day += 1) {
                const dateKey = `${calendarState.currentMonth}-${String(day).padStart(2, '0')}`;
                const data = calendarState.monthData[dateKey] || null;
                // Sunday check: parse as local date to avoid UTC-shift issues
                const isSunday = new Date(dateKey + 'T00:00:00').getDay() === 0;

                const dayCard = document.createElement('div');
                dayCard.className = 'calendar-day';
                dayCard.setAttribute('data-date', dateKey);

                if (isSunday) {
                    // Keep Sunday visible but fully disabled — no role/tabindex
                    dayCard.classList.add('is-sunday');
                    dayCard.setAttribute('aria-label', 'Sunday — no collections allowed');
                    dayCard.setAttribute('aria-disabled', 'true');
                } else {
                    dayCard.setAttribute('role', 'button');
                    dayCard.setAttribute('tabindex', '0');
                    dayCard.setAttribute('aria-label', 'View collector totals for ' + formatDisplayDate(dateKey));
                }

                if (data && !isSunday) {
                    dayCard.classList.add('has-data');
                }
                if (calendarState.selectedDate === dateKey) {
                    dayCard.classList.add('is-selected');
                }

                dayCard.innerHTML = `
                    <div class="calendar-day-number day-number">${day}</div>
                    <div>
                        ${isSunday
                            ? `<div class="calendar-day-empty" style="font-size:11px;color:#bbb;">No collection</div>`
                            : data
                                ? `<div class="calendar-day-total amount">${formatCurrency(data.total_collection)}</div>
                                   <div class="calendar-day-meta">${data.number_of_transactions} transaction${data.number_of_transactions === 1 ? '' : 's'}</div>`
                                : `<div class="calendar-day-empty">No collections</div>`}
                    </div>
                `;
                grid.appendChild(dayCard);
            }
        }

        async function loadCollectionCalendar(monthKey = calendarState.currentMonth) {
            const grid = document.getElementById('collectionCalendarGrid');
            calendarState.currentMonth = monthKey;
            grid.innerHTML = '<div class="calendar-loading">Loading calendar...</div>';
            document.getElementById('calendarMonthLabel').textContent = formatMonthLabel(monthKey);

            try {
                const response = await fetch('get_collection_calendar_data.php?month=' + encodeURIComponent(monthKey), {
                    cache: 'no-store'
                });
                const payload = await response.json();

                if (payload.status !== 'success') {
                    throw new Error(payload.message || 'Unable to load calendar data.');
                }

                calendarState.monthData = {};
                (payload.calendar || []).forEach(function(item) {
                    calendarState.monthData[item.date] = item;
                });

                renderCalendarGrid();
            } catch (error) {
                grid.innerHTML = '<div class="calendar-loading">' + escapeHtml(error.message || 'Unable to load calendar data.') + '</div>';
            }
        }

        async function loadCollectorTotals(dateKey, options = {}) {
            const openModal = options.openModal === true;
            calendarState.selectedDate = dateKey;
            renderCalendarGrid();

            const badge = document.getElementById('selectedDateBadge');
            const totalCollection = document.getElementById('selectedTotalCollection');
            const transactionCount = document.getElementById('selectedTransactionCount');
            const collectorCount = document.getElementById('selectedCollectorCount');
            const tableBody = document.getElementById('collectorTotalsTableBody');
            const modalResults = document.getElementById('collectionResults');
            const modalTotalCollection = document.getElementById('modalTotalCollection');

            badge.innerHTML = '<i class="ph ph-calendar-blank"></i> ' + escapeHtml(formatDisplayDate(dateKey));
            totalCollection.textContent = 'Loading...';
            transactionCount.textContent = '...';
            collectorCount.textContent = '...';
            tableBody.innerHTML = '<tr><td colspan="2" class="empty-state">Loading collector totals...</td></tr>';

            if (openModal) {
                openCollectionModal(dateKey);
            }

            try {
                const response = await fetch('/loan_api/admin/get_collector_daily_totals.php?date=' + encodeURIComponent(dateKey), {
                    cache: 'no-store'
                });
                const payload = await response.json();

                if (payload.status !== 'success') {
                    throw new Error(payload.message || 'Unable to load collector totals.');
                }

                const collectors = payload.collectors || [];

                totalCollection.textContent = formatCurrency(payload.total_collection || 0);
                transactionCount.textContent = payload.total_transactions || 0;
                collectorCount.textContent = collectors.length;
                modalTotalCollection.textContent = formatCurrency(payload.total_collection || 0);

                if (collectors.length === 0) {
                    const emptyMessage = escapeHtml(payload.message || 'No collections recorded.');
                    tableBody.innerHTML = '<tr><td colspan="2" class="empty-state">' + emptyMessage + '</td></tr>';
                    modalResults.innerHTML = '<div class="empty-state">' + emptyMessage + '</div>';
                    return;
                }

                tableBody.innerHTML = collectors.map(function(collector) {
                    const collectorName = collector.name || collector.collector_name || 'Unknown Collector';
                    const collectorTotal = collector.total || collector.total_collection || 0;
                    return `
                        <tr>
                            <td class="collector-total-name">${escapeHtml(collectorName)}</td>
                            <td style="font-weight:700; color:var(--text-main);">${formatCurrency(collectorTotal)}</td>
                        </tr>
                    `;
                }).join('');
                modalResults.innerHTML = renderCollectorResultsTable(collectors);
            } catch (error) {
                totalCollection.textContent = formatCurrency(0);
                transactionCount.textContent = '0';
                collectorCount.textContent = '0';
                modalTotalCollection.textContent = formatCurrency(0);
                tableBody.innerHTML = '<tr><td colspan="2" class="empty-state">' + escapeHtml(error.message || 'Unable to load collector totals.') + '</td></tr>';
                modalResults.innerHTML = '<div class="empty-state">Error loading data.</div>';
            }
        }

        document.getElementById('calendarPrevBtn').addEventListener('click', function() {
            loadCollectionCalendar(shiftMonth(calendarState.currentMonth, -1));
        });

        document.getElementById('calendarNextBtn').addEventListener('click', function() {
            loadCollectionCalendar(shiftMonth(calendarState.currentMonth, 1));
        });

        document.getElementById('closeModal').onclick = closeCollectionModal;

        document.addEventListener('click', function(event) {
            const closeButton = event.target.closest('#closeModal');
            if (closeButton) {
                closeCollectionModal();
                return;
            }

            if (event.target === document.getElementById('collectionModal')) {
                closeCollectionModal();
                return;
            }

            const dayCard = getCalendarDayFromEventTarget(event.target);
            if (!dayCard) {
                return;
            }

            const selectedDate = dayCard.getAttribute('data-date');
            if (!selectedDate) {
                return;
            }

            // Block Sunday — collections not allowed
            if (new Date(selectedDate + 'T00:00:00').getDay() === 0) {
                return;
            }

            loadCollectorTotals(selectedDate, { openModal: true });
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && document.getElementById('collectionModal').style.display === 'flex') {
                closeCollectionModal();
                return;
            }

            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            const dayCard = getCalendarDayFromEventTarget(event.target);
            if (!dayCard) {
                return;
            }

            event.preventDefault();
            const selectedDate = dayCard.getAttribute('data-date');
            if (!selectedDate) {
                return;
            }

            // Block Sunday — collections not allowed
            if (new Date(selectedDate + 'T00:00:00').getDay() === 0) {
                return;
            }

            loadCollectorTotals(selectedDate, { openModal: true });
        });

        loadCollectionCalendar(calendarState.currentMonth).then(function() {
            const today = new Date();
            const todayKey = buildMonthKey(today.getFullYear(), today.getMonth() + 1) + '-' + String(today.getDate()).padStart(2, '0');
            loadCollectorTotals(todayKey);
        });
    </script>
    <?php include 'notif_dropdown.php'; ?>
</body>
</html>
