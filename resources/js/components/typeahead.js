// Search-as-you-type against the session `lookup/*` endpoints (same logic and
// ranking as the API). Bind the text two-way with x-modelable="text"; picking
// an item dispatches `picked` with the item, typing over it dispatches
// `picked` with null so the parent can clear the linked id.
export default function typeahead({ url, label = 'name', take = 8, minChars = 1, params = {} } = {}) {
    return {
        text: '',
        results: [],
        open: false,
        active: -1,
        loading: false,
        timer: null,
        requestNo: 0,

        labelOf(item) {
            return typeof label === 'function' ? label(item) : item?.[label] ?? '';
        },

        onInput() {
            this.$dispatch('picked', null);
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.search(), 200);
        },

        async search() {
            const term = (this.text || '').trim();
            if (term.length < minChars) {
                this.results = [];
                this.open = false;
                return;
            }

            const requestNo = ++this.requestNo;
            this.loading = true;
            try {
                const { data } = await window.axios.get(url, { params: { search: term, take, ...params } });
                if (requestNo !== this.requestNo) {
                    return; // a newer search is on its way
                }
                this.results = data.data ?? [];
                this.active = this.results.length ? 0 : -1;
                this.open = this.results.length > 0;
            } catch {
                this.results = [];
                this.open = false;
            } finally {
                if (requestNo === this.requestNo) {
                    this.loading = false;
                }
            }
        },

        move(step) {
            if (!this.open || !this.results.length) {
                return;
            }
            this.active = (this.active + step + this.results.length) % this.results.length;
        },

        choose(index = this.active) {
            const item = this.results[index];
            if (!item) {
                return;
            }
            this.text = this.labelOf(item);
            this.open = false;
            this.results = [];
            this.$dispatch('picked', item);
        },

        // Enter picks the highlighted item instead of submitting the form.
        onEnter(event) {
            if (this.open && this.active >= 0) {
                event.preventDefault();
                this.choose();
            }
        },

        close() {
            setTimeout(() => {
                this.open = false;
            }, 150);
        },
    };
}
