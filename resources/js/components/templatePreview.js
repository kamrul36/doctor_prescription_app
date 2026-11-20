// Live A4 preview in the template editor. Every change in the surrounding form is
// POSTed (debounced) to settings.templates.preview, which renders the real print
// view with the unsaved template and a sample visit; the HTML goes into an iframe
// so the print CSS stays apart from the app's Tailwind. Nothing is saved.
// <div x-data="templatePreview('/settings/templates/preview', 'with_letterhead')"> inside the <form>.
// The print view switches to a phone layout under 820px, so the frame is wider than
// the 210 mm sheet (794px at 96 dpi) and the whole frame is scaled down to fit.
const FRAME_PX = 860;
const PAGE_PX = (297 * 96) / 25.4 + 24;

export default (url, defaultMode) => ({
    lang: document.documentElement.lang === 'bn' ? 'bn' : 'en',
    mode: defaultMode,
    loading: false,
    failed: false,
    scale: 1,
    height: PAGE_PX,
    seq: 0,
    timer: null,

    init() {
        const form = this.$el.closest('form');
        const onChange = (e) => {
            // The preview's own controls have no name and refresh through $watch.
            if (e.target.name === 'default_print_mode') {
                this.mode = e.target.value; // the $watch refreshes
            } else if (e.target.name) {
                this.queue();
            }
        };
        form.addEventListener('input', onChange);
        form.addEventListener('change', onChange);
        this.$watch('lang', () => this.refresh());
        this.$watch('mode', () => this.refresh());

        new ResizeObserver(() => this.fit()).observe(this.$refs.box);
        this.$refs.frame.addEventListener('load', () => this.fit());
        this.fit();
        this.refresh();
    },

    queue() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.refresh(), 300);
    },

    async refresh() {
        const form = this.$el.closest('form');
        const data = new FormData(form);
        data.delete('_method'); // the edit form sends PUT; the preview route is POST
        data.set('lang', this.lang);
        data.set('mode', this.mode);
        const seq = ++this.seq;
        this.loading = true;
        try {
            const res = await fetch(url, {
                method: 'POST',
                body: data,
                // JSON accept: a validation error comes back as 422, not a redirect to the form.
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (seq !== this.seq) return; // a newer request is on its way
            if (res.ok) {
                this.$refs.frame.srcdoc = await res.text();
                this.failed = false;
            } else {
                // 422: the form shows the field errors; keep the last good page.
                this.failed = res.status !== 422;
            }
        } catch {
            if (seq === this.seq) this.failed = true;
        } finally {
            if (seq === this.seq) this.loading = false;
        }
    },

    frameWidth: FRAME_PX,

    // Scale the frame to the column; the frame keeps the page's full height (no inner scroll).
    fit() {
        this.scale = Math.min(1, this.$refs.box.clientWidth / FRAME_PX);
        // The sheet's own height (+ its 24px bottom margin), so the frame also shrinks again.
        const sheet = this.$refs.frame.contentDocument?.querySelector('.sheet');
        this.height = Math.max(PAGE_PX, sheet ? sheet.offsetHeight + 24 : 0);
    },
});
