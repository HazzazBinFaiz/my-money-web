const TYPE_INCOME = 0;
const TYPE_EXPENSE = 1;
const TYPE_TRANSFER = 2;

const DRAFT_KEY = 'bulk-transactions-draft';

// Column order used by keyboard navigation, clipboard paste and CSV import.
const COLUMNS = ['type', 'account_id', 'to_account_id', 'category_id', 'amount', 'charge', 'date', 'time', 'note'];

const CSV_HEADERS = ['Type', 'Account', 'To Account', 'Category', 'Amount', 'Charge', 'Date', 'Time', 'Note'];

const TYPE_LABELS = {
    income: TYPE_INCOME,
    expense: TYPE_EXPENSE,
    transfer: TYPE_TRANSFER,
    0: TYPE_INCOME,
    1: TYPE_EXPENSE,
    2: TYPE_TRANSFER,
};

/**
 * Evaluates the small arithmetic expressions typed into amount cells.
 * The server re-parses whatever is submitted, so this is a convenience only.
 */
function evaluateExpression(expression) {
    const cleaned = String(expression ?? '').replace(/[\s,]/g, '');

    if (cleaned === '' || /[^0-9.+\-*/()]/.test(cleaned)) {
        return null;
    }

    try {
        const result = Function(`"use strict"; return (${cleaned});`)();

        return Number.isFinite(result) ? result : null;
    } catch (error) {
        return null;
    }
}

function normalise(value) {
    return String(value ?? '').trim().toLowerCase();
}

function pad(value) {
    return String(value).padStart(2, '0');
}

function today() {
    const now = new Date();

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

function currentTime() {
    const now = new Date();

    return `${pad(now.getHours())}:${pad(now.getMinutes())}`;
}

function blankRow(defaults = {}) {
    return {
        type: TYPE_EXPENSE,
        account_id: '',
        to_account_id: '',
        category_id: '',
        amount: '',
        charge: '0',
        note: '',
        // Left empty on purpose: a row only gets a date once it is being used.
        date: '',
        time: '',
        ...defaults,
    };
}

/**
 * Splits a delimited table, honouring quoted fields that contain the delimiter.
 */
function parseDelimited(text, delimiter) {
    const rows = [];
    let row = [];
    let field = '';
    let quoted = false;

    for (let i = 0; i < text.length; i++) {
        const character = text[i];

        if (quoted) {
            if (character === '"') {
                if (text[i + 1] === '"') {
                    field += '"';
                    i++;
                } else {
                    quoted = false;
                }
            } else {
                field += character;
            }

            continue;
        }

        if (character === '"') {
            quoted = true;
        } else if (character === delimiter) {
            row.push(field);
            field = '';
        } else if (character === '\n') {
            row.push(field);
            rows.push(row);
            row = [];
            field = '';
        } else if (character !== '\r') {
            field += character;
        }
    }

    if (field !== '' || row.length) {
        row.push(field);
        rows.push(row);
    }

    return rows.filter((cells) => cells.some((cell) => String(cell).trim() !== ''));
}

/**
 * Accepts 2026-07-04, 2026/7/4, and day-first 4/7/2026 or 4-7-2026.
 * Slash and dash dates are read day-first, never month-first, so a pasted
 * 04/07/2026 is the 4th of July rather than the 7th of April.
 */
function parseDate(value) {
    const raw = String(value ?? '').trim();

    if (raw === '') {
        return null;
    }

    const iso = raw.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})$/);

    if (iso) {
        return `${iso[1]}-${pad(iso[2])}-${pad(iso[3])}`;
    }

    const dayFirst = raw.match(/^(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})$/);

    if (dayFirst) {
        return `${dayFirst[3]}-${pad(dayFirst[2])}-${pad(dayFirst[1])}`;
    }

    // Anything else (e.g. "4 July 2026") falls back to the browser parser.
    const parsed = new Date(raw);

    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    return `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())}`;
}

/**
 * Accepts 09:30, 9:30, 9:30 PM.
 */
function parseTime(value) {
    const raw = String(value ?? '').trim();

    if (raw === '') {
        return null;
    }

    const match = raw.match(/^(\d{1,2}):(\d{2})\s*(am|pm)?$/i);

    if (! match) {
        return null;
    }

    let hours = Number(match[1]);
    const minutes = Number(match[2]);
    const meridiem = match[3]?.toLowerCase();

    if (meridiem === 'pm' && hours < 12) hours += 12;
    if (meridiem === 'am' && hours === 12) hours = 0;

    if (hours > 23 || minutes > 59) {
        return null;
    }

    return `${pad(hours)}:${pad(minutes)}`;
}

/**
 * Alpine component behind the bulk transaction grid.
 */
export default function bulkTransactions({
    rows = [],
    errors = {},
    accounts = [],
    incomeCategories = [],
    expenseCategories = [],
} = {}) {
    return {
        rows: rows.length ? rows.map((row) => ({ ...blankRow(), ...row })) : [blankRow()],
        errors,
        addCount: 1,
        accounts,
        incomeCategories,
        expenseCategories,
        columns: COLUMNS,

        csvOpen: false,
        csvFileName: '',
        importReport: null,

        init() {
            // Only offer a draft when the page opened empty (no server round trip).
            if (! rows.length) {
                this.restoreDraft();
            }

            this.$watch('rows', () => this.saveDraft());
        },

        // -- row helpers ---------------------------------------------------

        isTransfer(row) {
            return Number(row.type) === TYPE_TRANSFER;
        },

        categoriesFor(row) {
            return Number(row.type) === TYPE_INCOME ? this.incomeCategories : this.expenseCategories;
        },

        /**
         * Switching type invalidates the cells that no longer apply.
         */
        typeChanged(row) {
            if (this.isTransfer(row)) {
                row.category_id = '';
            } else {
                row.to_account_id = '';

                const allowed = this.categoriesFor(row).map((category) => String(category.id));

                if (! allowed.includes(String(row.category_id))) {
                    row.category_id = '';
                }
            }
        },

        /**
         * New rows inherit type, accounts and date from the last row so a long
         * batch for one account and one day stays a couple of keystrokes.
         */
        addRows(count = null) {
            const total = Math.max(1, Math.min(100, Number(count ?? this.addCount) || 1));

            for (let i = 0; i < total; i++) {
                this.rows.push(blankRow());
            }
        },

        isBlank(row) {
            const filled = ['account_id', 'to_account_id', 'category_id', 'amount', 'note']
                .some((key) => String(row[key] ?? '').trim() !== '');

            if (filled) {
                return false;
            }

            // The charge column defaults to 0, so only a real charge counts.
            const charge = String(row.charge ?? '').trim();

            return charge === '' || Number(charge) === 0;
        },

        /**
         * Every cell the row's type actually needs is filled, so it will be
         * saved as a transaction. Cells disabled by the type are not required.
         */
        isComplete(row) {
            const required = ['account_id', 'amount', 'date', 'time'];

            required.push(this.isTransfer(row) ? 'to_account_id' : 'category_id');

            return required.every((key) => String(row[key] ?? '').trim() !== '');
        },

        /**
         * Started but not finished: shown with a red tint so it is obvious
         * before submitting.
         */
        isPartial(row) {
            return ! this.isBlank(row) && ! this.isComplete(row);
        },

        /**
         * Copies a row into the next one when that row is still empty,
         * otherwise inserts a copy directly below and pushes the rest down.
         */
        duplicate(index) {
            const copy = { ...this.rows[index] };
            const next = this.rows[index + 1];

            if (next && this.isBlank(next)) {
                this.rows.splice(index + 1, 1, copy);

                return;
            }

            this.rows.splice(index + 1, 0, copy);
        },

        remove(index) {
            this.rows.splice(index, 1);

            if (this.rows.length === 0) {
                this.rows.push(blankRow());
            }
        },

        clearAll() {
            this.rows = [blankRow()];
            this.errors = {};
            this.importReport = null;
        },

        resolveAmount(row, field) {
            const raw = String(row[field] ?? '').trim();

            if (raw === '') {
                return;
            }

            const value = evaluateExpression(raw);

            if (value !== null) {
                row[field] = value.toFixed(2);
            }
        },

        error(index, field) {
            return this.errors[`rows.${index}.${field}`] ?? null;
        },

        // -- keyboard navigation -------------------------------------------

        cellAt(rowIndex, columnIndex) {
            const column = COLUMNS[columnIndex];

            return column ? this.$refs.grid?.querySelector(`[data-cell="${rowIndex}:${column}"]`) : null;
        },

        /**
         * Focuses a cell, stepping over cells the current row type disables
         * (no destination account unless transfer, no category on transfers).
         * `step` is the direction of travel, 0 when moving between rows.
         */
        focusCell(rowIndex, columnIndex, step = 0) {
            const row = Math.max(0, Math.min(this.rows.length - 1, rowIndex));
            const start = Math.max(0, Math.min(COLUMNS.length - 1, columnIndex));

            this.$nextTick(() => {
                let target = this.cellAt(row, start);

                if (target?.disabled) {
                    // Continue the way we were going, then look the other way.
                    for (const direction of (step !== 0 ? [step, -step] : [1, -1])) {
                        let found = null;

                        for (let i = start + direction; i >= 0 && i < COLUMNS.length; i += direction) {
                            const candidate = this.cellAt(row, i);

                            if (candidate && ! candidate.disabled) {
                                found = candidate;
                                break;
                            }
                        }

                        if (found) {
                            target = found;
                            break;
                        }
                    }
                }

                if (! target || target.disabled) {
                    return;
                }

                target.focus();

                if (typeof target.select === 'function' && target.type === 'text') {
                    target.select();
                }
            });
        },

        /**
         * A row that stops being blank gets the previous row's date and time,
         * falling back to now, so entry never stalls on the date cell.
         */
        ensureDefaults(index) {
            const row = this.rows[index];

            if (! row || this.isBlank(row)) {
                return;
            }

            const previous = this.rows.slice(0, index).reverse().find((candidate) => candidate.date);

            if (! row.date) {
                row.date = previous?.date ?? today();
            }

            if (! row.time) {
                row.time = previous?.time ?? currentTime();
            }
        },

        /**
         * Arrow up/down and Enter walk the grid; Ctrl+D duplicates,
         * Ctrl+Backspace deletes. Tab keeps its native behaviour.
         */
        navigate(event, rowIndex, column) {
            const columnIndex = COLUMNS.indexOf(column);
            const isText = event.target.tagName === 'INPUT' && event.target.type === 'text';
            // Date and time inputs use the arrows to step their own segments.
            const keepsArrows = ['date', 'time'].includes(event.target.type);

            if (keepsArrows && event.key.startsWith('Arrow')) {
                return;
            }

            if (event.key === 'Enter') {
                // Never let Enter submit the batch from inside a cell.
                event.preventDefault();

                if (! event.shiftKey && this.opensPicker(event.target)) {
                    return;
                }

                if (event.shiftKey) {
                    this.focusCell(rowIndex - 1, columnIndex);

                    return;
                }

                if (rowIndex === this.rows.length - 1) {
                    this.addRows(1);
                }

                this.focusCell(rowIndex + 1, columnIndex);

                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();

                if (rowIndex === this.rows.length - 1) {
                    this.addRows(1);
                }

                this.focusCell(rowIndex + 1, columnIndex);

                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.focusCell(rowIndex - 1, columnIndex);

                return;
            }

            // Inside a text box, left/right belong to the caret unless it sits at the edge.
            if (event.key === 'ArrowLeft' && (! isText || event.target.selectionStart === 0)) {
                event.preventDefault();
                this.focusCell(rowIndex, columnIndex - 1, -1);

                return;
            }

            if (event.key === 'ArrowRight'
                && (! isText || event.target.selectionStart === event.target.value.length)) {
                event.preventDefault();
                this.focusCell(rowIndex, columnIndex + 1, 1);

                return;
            }

            if (event.key.toLowerCase() === 'd' && (event.ctrlKey || event.metaKey)) {
                event.preventDefault();
                this.duplicate(rowIndex);
                this.focusCell(rowIndex + 1, columnIndex);

                return;
            }

            if (event.key === 'Backspace' && (event.ctrlKey || event.metaKey)) {
                event.preventDefault();
                this.remove(rowIndex);
                this.focusCell(Math.max(0, rowIndex - 1), columnIndex);
            }
        },

        /**
         * Selects and date/time inputs answer Enter by opening their picker
         * instead of moving on. showPicker is not everywhere yet, so a browser
         * without it simply keeps focus where it is.
         */
        opensPicker(element) {
            const isPicker = element.tagName === 'SELECT' || ['date', 'time'].includes(element.type);

            if (! isPicker) {
                return false;
            }

            if (typeof element.showPicker === 'function') {
                try {
                    element.showPicker();
                } catch (error) {
                    // Some browsers refuse without a direct user gesture; ignore.
                }
            }

            return true;
        },

        // -- matching ------------------------------------------------------

        matchAccount(label) {
            const wanted = normalise(label);

            if (wanted === '') {
                return '';
            }

            const found = this.accounts.find((account) => normalise(account.name) === wanted);

            return found ? String(found.id) : '';
        },

        matchCategory(label, type) {
            const wanted = normalise(label);

            if (wanted === '') {
                return '';
            }

            const pool = Number(type) === TYPE_INCOME ? this.incomeCategories : this.expenseCategories;
            const found = pool.find((category) => normalise(category.name) === wanted);

            return found ? String(found.id) : '';
        },

        matchType(label) {
            const key = normalise(label);

            return key in TYPE_LABELS ? TYPE_LABELS[key] : null;
        },

        /**
         * Turns one delimited line into a row, reporting labels it could not match.
         */
        rowFromCells(cells, report) {
            const [type, account, toAccount, category, amount, charge, date, time, note] = cells;

            const row = blankRow();
            const matchedType = this.matchType(type);

            if (matchedType === null && String(type ?? '').trim() !== '') {
                report.unmatchedTypes++;
            }

            row.type = matchedType ?? TYPE_EXPENSE;

            row.account_id = this.matchAccount(account);
            if (row.account_id === '' && String(account ?? '').trim() !== '') {
                report.unmatchedAccounts.push(String(account).trim());
            }

            if (Number(row.type) === TYPE_TRANSFER) {
                row.to_account_id = this.matchAccount(toAccount);

                if (row.to_account_id === '' && String(toAccount ?? '').trim() !== '') {
                    report.unmatchedAccounts.push(String(toAccount).trim());
                }
            } else {
                row.category_id = this.matchCategory(category, row.type);

                if (row.category_id === '' && String(category ?? '').trim() !== '') {
                    report.unmatchedCategories.push(String(category).trim());
                }
            }

            row.amount = String(amount ?? '').trim();
            row.charge = String(charge ?? '').trim() || '0';
            row.note = String(note ?? '').trim();
            row.date = parseDate(date) ?? today();
            row.time = parseTime(time) ?? currentTime();

            return row;
        },

        newReport() {
            return { imported: 0, unmatchedAccounts: [], unmatchedCategories: [], unmatchedTypes: 0 };
        },

        finishReport(report) {
            report.unmatchedAccounts = [...new Set(report.unmatchedAccounts)];
            report.unmatchedCategories = [...new Set(report.unmatchedCategories)];

            this.importReport = report;
        },

        // -- clipboard paste -----------------------------------------------

        /**
         * Pasting a block from a spreadsheet fills the grid starting at the
         * focused cell, adding rows as needed. Single values paste normally.
         */
        paste(event) {
            const active = document.activeElement;
            const inGrid = active && this.$refs.grid?.contains(active);

            if (active && ! inGrid && active !== document.body) {
                return;
            }

            const [focusedRow, focusedColumn] = String(active?.dataset?.cell ?? '0:type').split(':');
            const rowIndex = Number(focusedRow) || 0;
            const column = COLUMNS.includes(focusedColumn) ? focusedColumn : 'type';

            const text = event.clipboardData?.getData('text/plain') ?? '';

            if (! text.includes('\t') && ! text.trim().includes('\n')) {
                return;
            }

            event.preventDefault();

            const table = parseDelimited(text.replace(/\n$/, ''), '\t');
            const startColumn = COLUMNS.indexOf(column);
            const report = this.newReport();

            table.forEach((cells, offset) => {
                const target = rowIndex + offset;

                while (this.rows.length <= target) {
                    this.rows.push(blankRow());
                }

                // A full-width paste is treated as complete rows, so labels get matched.
                if (startColumn === 0 && cells.length >= 5) {
                    this.rows.splice(target, 1, this.rowFromCells(cells, report));
                    report.imported++;

                    return;
                }

                cells.forEach((value, cellOffset) => {
                    const field = COLUMNS[startColumn + cellOffset];

                    if (! field) {
                        return;
                    }

                    this.applyCell(this.rows[target], field, value, report);
                });

                report.imported++;
            });

            this.finishReport(report);
        },

        /**
         * Writes a single pasted value into a row, matching labels for the
         * select columns so pasted text lands on the right option.
         */
        applyCell(row, field, value, report) {
            const raw = String(value ?? '').trim();

            if (field === 'type') {
                const matched = this.matchType(raw);

                if (matched === null) {
                    report.unmatchedTypes++;
                } else {
                    row.type = matched;
                }

                return;
            }

            if (field === 'account_id' || field === 'to_account_id') {
                const matched = this.matchAccount(raw);

                if (matched === '' && raw !== '') {
                    report.unmatchedAccounts.push(raw);
                }

                row[field] = matched;

                return;
            }

            if (field === 'category_id') {
                const matched = this.matchCategory(raw, row.type);

                if (matched === '' && raw !== '') {
                    report.unmatchedCategories.push(raw);
                }

                row.category_id = matched;

                return;
            }

            if (field === 'date') {
                row.date = parseDate(raw) ?? row.date;

                return;
            }

            if (field === 'time') {
                row.time = parseTime(raw) ?? row.time;

                return;
            }

            row[field] = raw;
        },

        // -- CSV import ----------------------------------------------------

        openCsv() {
            this.csvOpen = true;
            this.csvFileName = '';
        },

        downloadTemplate() {
            const sample = [
                CSV_HEADERS,
                ['Expense', this.accounts[0]?.name ?? 'Cash', '', this.expenseCategories[0]?.name ?? 'Groceries', '12.50', '', today(), '09:30', 'Example row'],
                ['Income', this.accounts[0]?.name ?? 'Cash', '', this.incomeCategories[0]?.name ?? 'Salary', '1000', '', today(), '10:00', ''],
                ['Transfer', this.accounts[0]?.name ?? 'Cash', this.accounts[1]?.name ?? 'Bank', '', '250', '1.50', today(), '10:15', ''],
            ];

            const csv = sample
                .map((cells) => cells.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(','))
                .join('\n');

            const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
            const link = document.createElement('a');

            link.href = url;
            link.download = 'bulk-transactions-template.csv';
            link.click();

            URL.revokeObjectURL(url);
        },

        csvChosen(event) {
            const file = event.target.files[0];

            this.csvFileName = file ? file.name : '';
        },

        /**
         * Reads the chosen CSV and appends matched rows to the grid.
         */
        async fillFromCsv() {
            const file = this.$refs.csvInput?.files[0];

            if (! file) {
                return;
            }

            const text = await file.text();
            const table = parseDelimited(text, ',');

            if (! table.length) {
                return;
            }

            // Drop the header line when it looks like one.
            if (normalise(table[0][0]) === 'type') {
                table.shift();
            }

            const report = this.newReport();
            const imported = table.map((cells) => {
                report.imported++;

                return this.rowFromCells(cells, report);
            });

            // Replace trailing blank rows rather than leaving gaps.
            const kept = this.rows.filter((row) => ! this.isBlank(row));

            this.rows = [...kept, ...imported];
            this.finishReport(report);
            this.csvOpen = false;

            if (this.$refs.csvInput) {
                this.$refs.csvInput.value = '';
            }

            this.csvFileName = '';
        },

        // -- totals and draft ----------------------------------------------

        totals() {
            const totals = { income: 0, expense: 0, transfer: 0, filled: 0, partial: 0 };

            this.rows.forEach((row) => {
                if (! this.isComplete(row)) {
                    if (this.isPartial(row)) {
                        totals.partial++;
                    }

                    return;
                }

                totals.filled++;

                const amount = evaluateExpression(row.amount) ?? 0;
                const type = Number(row.type);

                if (type === TYPE_INCOME) totals.income += amount;
                else if (type === TYPE_EXPENSE) totals.expense += amount;
                else totals.transfer += amount;
            });

            return totals;
        },

        format(value) {
            return Number(value ?? 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },

        saveDraft() {
            try {
                localStorage.setItem(DRAFT_KEY, JSON.stringify(this.rows));
            } catch (error) {
                // Storage full or unavailable: drafts are a nicety, never a blocker.
            }
        },

        restoreDraft() {
            try {
                const draft = JSON.parse(localStorage.getItem(DRAFT_KEY) ?? 'null');

                if (Array.isArray(draft) && draft.length && ! draft.every((row) => this.isBlank(row))) {
                    this.rows = draft.map((row) => ({ ...blankRow(), ...row }));
                }
            } catch (error) {
                // Ignore malformed drafts.
            }
        },

        clearDraft() {
            localStorage.removeItem(DRAFT_KEY);
        },
    };
}
