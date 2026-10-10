// Repeating form rows (branches, complaints, tests, medicines, ...).
// Blade passes the initial rows (saved data or old input) and a blank row.
// Inputs are named from the row index, so a normal form post keeps working.
let nextKey = 1;

export default function rowList(initial = [], blank = {}, { min = 0 } = {}) {
    return {
        rows: [],

        init() {
            this.rows = (Array.isArray(initial) ? initial : Object.values(initial || {})).map((row) => this.keyed(row));
            while (this.rows.length < min) {
                this.rows.push(this.keyed({}));
            }
        },

        keyed(row) {
            return { ...structuredClone(blank), ...(row || {}), _key: nextKey++ };
        },

        add(row = {}) {
            this.rows.push(this.keyed(row));
            // Focus the first field of the new row.
            this.$nextTick(() => {
                const rows = this.$root.querySelectorAll('[data-row]');
                rows[rows.length - 1]?.querySelector('input, select, textarea')?.focus();
            });
        },

        remove(index) {
            this.rows.splice(index, 1);
        },

        move(index, step) {
            const target = index + step;
            if (target < 0 || target >= this.rows.length) {
                return;
            }
            const [row] = this.rows.splice(index, 1);
            this.rows.splice(target, 0, row);
        },
    };
}
