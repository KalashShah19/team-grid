/**
 * Team-Grid — Custom DOB Date Picker
 * Enforces DD-MM-YYYY format across all browsers with interactive Year/Month dropdowns.
 */
(function() {
    'use strict';

    // Helper: format any ISO/raw date into DD-MM-YYYY
    function toDDMMYYYY(val) {
        if (!val) return '';
        val = String(val).trim();
        // Match YYYY-MM-DD
        const isoMatch = val.match(/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/);
        if (isoMatch) {
            const y = isoMatch[1];
            const m = String(isoMatch[2]).padStart(2, '0');
            const d = String(isoMatch[3]).padStart(2, '0');
            return `${d}-${m}-${y}`;
        }
        // Match DD-MM-YYYY
        const ddmmyyyy = val.match(/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/);
        if (ddmmyyyy) {
            const d = String(ddmmyyyy[1]).padStart(2, '0');
            const m = String(ddmmyyyy[2]).padStart(2, '0');
            const y = ddmmyyyy[3];
            return `${d}-${m}-${y}`;
        }
        return val;
    }

    // Helper: parse DD-MM-YYYY into { day, month, year }
    function parseDDMMYYYY(val) {
        if (!val) return null;
        const m = String(val).trim().match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/);
        if (!m) return null;
        const d = parseInt(m[1], 10);
        const mo = parseInt(m[2], 10);
        const y = parseInt(m[3], 10);
        if (mo < 1 || mo > 12 || d < 1 || d > 31 || y < 1900) return null;
        return { day: d, month: mo, year: y };
    }

    // Auto-mask digits to DD-MM-YYYY while typing
    function formatAsTyping(value) {
        // Strip non-digits
        const digits = value.replace(/\D/g, '').slice(0, 8);
        if (!digits) return '';

        if (digits.length <= 2) {
            return digits;
        } else if (digits.length <= 4) {
            return digits.slice(0, 2) + '-' + digits.slice(2);
        } else {
            return digits.slice(0, 2) + '-' + digits.slice(2, 4) + '-' + digits.slice(4);
        }
    }

    const MONTH_NAMES = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];

    const DAYS_HEADER = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

    // Single active popup calendar instance
    let activePopup = null;
    let activeInput = null;

    function closeActiveCalendar() {
        if (activePopup) {
            activePopup.remove();
            activePopup = null;
            activeInput = null;
        }
    }

    // Close on click outside or Escape
    document.addEventListener('click', (e) => {
        if (!activePopup) return;
        if (!activePopup.contains(e.target) && !e.target.closest('.dob-calendar-toggle') && e.target !== activeInput) {
            closeActiveCalendar();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && activePopup) {
            closeActiveCalendar();
        }
    });

    function openCalendar(input) {
        if (activeInput === input && activePopup) {
            closeActiveCalendar();
            return;
        }

        closeActiveCalendar();
        activeInput = input;

        // Parse initial date or default to year 2000 for quick DOB picking
        const currentYear = new Date().getFullYear();
        let selected = parseDDMMYYYY(input.value);
        let viewYear = selected ? selected.year : 2000;
        let viewMonth = selected ? selected.month : 1; // 1-12

        const popup = document.createElement('div');
        popup.className = 'dob-calendar-popup';
        popup.setAttribute('role', 'dialog');
        popup.setAttribute('aria-label', 'Date of Birth Calendar');

        function renderCalendar() {
            // Month & Year header controls
            const header = document.createElement('div');
            header.className = 'dob-cal-header';

            const prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'dob-cal-nav';
            prevBtn.innerHTML = '‹';
            prevBtn.title = 'Previous Month';
            prevBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                viewMonth--;
                if (viewMonth < 1) {
                    viewMonth = 12;
                    viewYear--;
                }
                update();
            });

            const nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'dob-cal-nav';
            nextBtn.innerHTML = '›';
            nextBtn.title = 'Next Month';
            nextBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                viewMonth++;
                if (viewMonth > 12) {
                    viewMonth = 1;
                    viewYear++;
                }
                update();
            });

            // Month Select
            const monthSelect = document.createElement('select');
            monthSelect.className = 'dob-cal-select';
            MONTH_NAMES.forEach((name, idx) => {
                const opt = document.createElement('option');
                opt.value = idx + 1;
                opt.textContent = name;
                if (idx + 1 === viewMonth) opt.selected = true;
                monthSelect.appendChild(opt);
            });
            monthSelect.addEventListener('change', (e) => {
                e.stopPropagation();
                viewMonth = parseInt(monthSelect.value, 10);
                update();
            });

            // Year Select (Current year down to 1940)
            const yearSelect = document.createElement('select');
            yearSelect.className = 'dob-cal-select';
            for (let y = currentYear; y >= 1940; y--) {
                const opt = document.createElement('option');
                opt.value = y;
                opt.textContent = y;
                if (y === viewYear) opt.selected = true;
                yearSelect.appendChild(opt);
            }
            yearSelect.addEventListener('change', (e) => {
                e.stopPropagation();
                viewYear = parseInt(yearSelect.value, 10);
                update();
            });

            const selectors = document.createElement('div');
            selectors.className = 'dob-cal-selectors';
            selectors.appendChild(monthSelect);
            selectors.appendChild(yearSelect);

            header.appendChild(prevBtn);
            header.appendChild(selectors);
            header.appendChild(nextBtn);

            // Weekdays header row
            const weekdays = document.createElement('div');
            weekdays.className = 'dob-cal-weekdays';
            DAYS_HEADER.forEach(d => {
                const el = document.createElement('div');
                el.className = 'dob-cal-weekday';
                el.textContent = d;
                weekdays.appendChild(el);
            });

            // Days Grid
            const daysGrid = document.createElement('div');
            daysGrid.className = 'dob-cal-grid';

            // First day of month (0 = Sun, 1 = Mon, ..., 6 = Sat)
            const firstDay = new Date(viewYear, viewMonth - 1, 1).getDay();
            // Convert to Mon=0..Sun=6
            const startOffset = (firstDay + 6) % 7;

            // Total days in month
            const daysInMonth = new Date(viewYear, viewMonth, 0).getDate();
            // Days in prev month
            const prevMonthDays = new Date(viewYear, viewMonth - 1, 0).getDate();

            // Previous month padding days
            for (let i = startOffset - 1; i >= 0; i--) {
                const dayEl = document.createElement('button');
                dayEl.type = 'button';
                dayEl.className = 'dob-cal-day out-of-month';
                dayEl.textContent = prevMonthDays - i;
                dayEl.tabIndex = -1;
                daysGrid.appendChild(dayEl);
            }

            // Current month days
            for (let d = 1; d <= daysInMonth; d++) {
                const dayEl = document.createElement('button');
                dayEl.type = 'button';
                dayEl.className = 'dob-cal-day';
                dayEl.textContent = d;

                const isSelected = selected && selected.year === viewYear && selected.month === viewMonth && selected.day === d;
                if (isSelected) {
                    dayEl.classList.add('selected');
                }

                dayEl.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const formatted = `${String(d).padStart(2, '0')}-${String(viewMonth).padStart(2, '0')}-${viewYear}`;
                    input.value = formatted;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    closeActiveCalendar();
                    input.focus();
                });

                daysGrid.appendChild(dayEl);
            }

            // Format Badge / Indicator
            const footer = document.createElement('div');
            footer.className = 'dob-cal-footer';
            footer.innerHTML = `
                <span class="dob-format-hint">Format: <strong>DD-MM-YYYY</strong></span>
                <button type="button" class="dob-cal-close-btn">Close</button>
            `;
            footer.querySelector('.dob-cal-close-btn').addEventListener('click', (e) => {
                e.stopPropagation();
                closeActiveCalendar();
            });

            popup.innerHTML = '';
            popup.appendChild(header);
            popup.appendChild(weekdays);
            popup.appendChild(daysGrid);
            popup.appendChild(footer);
        }

        function update() {
            renderCalendar();
            reposition();
        }

        function reposition() {
            const rect = input.getBoundingClientRect();
            const popupRect = popup.getBoundingClientRect();
            
            // Default: position directly below input
            let top = rect.bottom + window.scrollY + 6;
            let left = rect.left + window.scrollX;

            // If popup would overflow viewport right
            if (left + 300 > window.innerWidth) {
                left = Math.max(10, window.innerWidth - 310);
            }

            // If popup would overflow viewport bottom, show above
            if (rect.bottom + 320 > window.innerHeight && rect.top > 320) {
                top = rect.top + window.scrollY - 326;
            }

            popup.style.top = `${top}px`;
            popup.style.left = `${left}px`;
        }

        renderCalendar();
        document.body.appendChild(popup);
        activePopup = popup;
        reposition();
    }

    // Attach picker logic to an input element
    function initInput(input) {
        if (input.dataset.dobPickerInitialized) return;
        input.dataset.dobPickerInitialized = 'true';

        // Configure input attributes
        input.type = 'text';
        input.inputMode = 'numeric';
        input.placeholder = 'DD-MM-YYYY';
        input.maxLength = 10;
        input.autocomplete = 'bday';
        input.classList.add('dob-picker-input');

        // Initial format check
        if (input.value) {
            input.value = toDDMMYYYY(input.value);
        }

        // Auto-format typing
        input.addEventListener('input', (e) => {
            const cursor = input.selectionStart;
            const prevLen = input.value.length;
            const formatted = formatAsTyping(input.value);
            input.value = formatted;
        });

        // Ensure keydown handles backspace naturally
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && (input.value.endsWith('-'))) {
                input.value = input.value.slice(0, -1);
            }
        });

        // Find or create the toggle button beside the input
        let toggle = input.parentElement?.querySelector('.dob-calendar-toggle');
        if (!toggle && input.parentElement) {
            toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'dob-calendar-toggle';
            toggle.innerHTML = '📅';
            toggle.tabIndex = -1;
            toggle.setAttribute('aria-label', 'Choose date of birth');
            input.parentElement.appendChild(toggle);
        }

        if (toggle) {
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                openCalendar(input);
            });
        }
    }

    // Global initializer
    function initAll() {
        // Target explicit IDs and selectors
        const selectors = [
            '#loginDob',
            '#regDob',
            '#newDob',
            'input[name="dob"]',
            '.dob-picker-input'
        ];
        document.querySelectorAll(selectors.join(',')).forEach(initInput);
    }

    window.TM = window.TM || {};
    window.TM.initDobPicker = initInput;
    window.TM.formatDob = toDDMMYYYY;

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
