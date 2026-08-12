/**
 * Alpine component behind the add-transaction form.
 *
 * Amount and charge accept arithmetic ("10 * 2", "(4 * 4)+54"); on blur the
 * expression is replaced with its two decimal result. The server re-parses the
 * submitted value, so this is only a convenience.
 */
// TransactionType::Expense — the type most entries are, and the same default the
// bulk grid starts a row with.
const DEFAULT_TYPE = 1;

export default function transactionForm({ type = DEFAULT_TYPE, amount = '', charge = '' } = {}) {
    return {
        type,
        amount,
        charge,

        evaluate(expression) {
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
        },

        resolve(field) {
            const raw = String(this[field] ?? '').trim();

            if (raw === '') {
                return;
            }

            const value = this.evaluate(raw);

            if (value !== null) {
                this[field] = value.toFixed(2);
            }
        },

        isTransfer() {
            return this.type === 2;
        },
    };
}
