// Patient strip on the prescription pad. Either a registered patient is
// selected (patient_id set, details shown read-only), or new details are
// typed; while typing name/phone, likely matches are offered so the doctor
// can pick the existing record instead of registering a duplicate.
export default function patientPicker({ url, selected = null, fields = {} } = {}) {
    return {
        patient: selected,
        form: { name: '', phone: '', age_years: '', gender: '', address: '', ...fields },
        matches: [],
        timer: null,

        get selectedId() {
            return this.patient?.id ?? '';
        },

        lookup() {
            clearTimeout(this.timer);
            const term = (this.form.phone || '').replace(/\D/g, '').length >= 4 ? this.form.phone : this.form.name;
            if (!term || term.trim().length < 3) {
                this.matches = [];
                return;
            }
            this.timer = setTimeout(async () => {
                try {
                    const { data } = await window.axios.get(url, { params: { q: term, per_page: 5 } });
                    this.matches = data.data ?? [];
                } catch {
                    this.matches = [];
                }
            }, 300);
        },

        use(patient) {
            this.patient = patient;
            this.matches = [];
        },

        change() {
            this.patient = null;
        },
    };
}
