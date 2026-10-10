// Dose shortcut, mirrored by App\Domain\Clinical\ValueObjects\DoseShortcut (the server re-validates):
//   "2+0+2 7d"  -> 2 morning, 0 noon, 2 night, 7 days
//   "1+0+0 6m"  -> 6 months;   "1+1+1 2w" -> 2 weeks
//   "0+0+1 cont" / "1+0+1 sos" -> continuous / as needed
// Slots accept 0, ½ (or 1/2), 1, 1½ (or 1.5), 2, 3 ...
const SLOT = /^(?:\d+(?:\.5)?|½|\d+½|\d+\s?1\/2|1\/2)$/;
const UNITS = { d: 'day', day: 'day', days: 'day', w: 'week', wk: 'week', week: 'week', weeks: 'week', m: 'month', mo: 'month', month: 'month', months: 'month' };
const OPEN_ENDED = { cont: 'continuous', continuous: 'continuous', sos: 'as_needed', prn: 'as_needed', as_needed: 'as_needed' };

function normaliseSlot(slot) {
    const s = slot.replace(/\s+/g, '').replace('1/2', '½');
    if (s === '.5' || s === '0.5' || s === '0½') return '½';
    if (/^\d+\.5$/.test(s)) return `${parseInt(s, 10)}½`;
    return s;
}

export function parseDoseShortcut(input) {
    const text = (input || '').trim().toLowerCase();
    if (text === '') {
        return { ok: false, error: '' };
    }

    const match = text.match(/^([^\s]+(?:\s*\+\s*[^\s+]+){2})(?:\s+(.+))?$/);
    if (!match) {
        return { ok: false, error: 'Write the dose as morning+noon+night, e.g. 1+0+1 7d' };
    }

    const slots = match[1].split('+').map((s) => s.trim());
    if (slots.length !== 3 || !slots.every((s) => SLOT.test(s.replace(/\s+/g, '')))) {
        return { ok: false, error: 'Each dose slot is a number such as 0, ½, 1, 1½ or 2' };
    }
    const [morning, noon, night] = slots.map(normaliseSlot);
    if ([morning, noon, night].every((s) => s === '0')) {
        return { ok: false, error: 'A dose of 0+0+0 gives nothing' };
    }

    const result = { ok: true, dose_morning: morning, dose_noon: noon, dose_night: night };
    const rest = (match[2] || '').trim();
    if (rest === '') {
        return result;
    }
    if (OPEN_ENDED[rest]) {
        return { ...result, duration_value: '', duration_unit: OPEN_ENDED[rest] };
    }

    const duration = rest.match(/^(\d{1,3})\s*([a-z_]+)$/);
    if (!duration || !UNITS[duration[2]]) {
        return { ok: false, error: 'Duration: a number with d, w or m (7d, 2w, 6m), or cont / sos' };
    }

    return { ...result, duration_value: duration[1], duration_unit: UNITS[duration[2]] };
}

// x-data="doseInput(row)": type the shortcut, the row's dose/duration fields fill in.
export default function doseInput(row) {
    return {
        shortcut: '',
        error: '',

        apply() {
            const parsed = parseDoseShortcut(this.shortcut);
            this.error = parsed.error ?? '';
            if (!parsed.ok) {
                return;
            }
            for (const key of ['dose_morning', 'dose_noon', 'dose_night', 'duration_value', 'duration_unit']) {
                if (key in parsed) {
                    row[key] = parsed[key];
                }
            }
            this.shortcut = '';
        },
    };
}
