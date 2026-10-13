import { SLOT } from './dose';
import { t } from '../i18n';

// Field validation while typing (spec §10.1, validation and feedback rule).
// The server's FormRequest stays the authority; these checks mirror it so the
// user sees a wrong format at once. Rules come from the field's attributes:
//   required, maxlength                       HTML attributes
//   data-rule="phone|phone-list|list|integer|decimal|money|dose-slot|past-date|future-date"
//   data-min / data-max                       range for integer and decimal
//   data-max-items, data-item-min/-max        for phone-list and list
//   data-pattern + data-pattern-message       a regular expression the whole value must match
//   data-required-without="other_field"       one of the two must be filled
// <form x-data="validatedForm(@js($errors->toArray()))"> (submit is blocked while a field is wrong).
// Show a message with x-text="err('patient.phone')" or, for a row, rowErr(`medicines.${i}`).
const RULES = {
    phone(value) {
        // Same as the FormRequest: separators are removed, then digits with an optional leading +.
        const digits = value.replace(/[\s().-]/g, '');
        if (/[^\d+]/.test(digits) || digits.lastIndexOf('+') > 0) {
            return t('Use digits only (spaces and dashes are fine).');
        }
        return /^\+?\d{6,15}$/.test(digits) ? '' : t('Enter a phone number of 6 to 15 digits.');
    },
    // Chamber/branch contact numbers: comma-separated, each 5 to 15 digits (App\Support\Phone::contactList).
    'phone-list'(value, el) {
        const numbers = value.split(',').map((n) => n.trim()).filter(Boolean);
        const max = Number(el.dataset.maxItems || 10);
        if (numbers.length > max) {
            return t('Enter at most :max numbers.', { max });
        }
        const ok = numbers.every((n) => /^\+?\d{5,15}$/.test(n.replace(/[\s().-]/g, '')));
        return ok ? '' : t('Enter phone numbers of 5 to 15 digits, separated by commas.');
    },
    // Comma-separated names: data-max-items, data-item-min, data-item-max.
    list(value, el) {
        const items = value.split(',').map((n) => n.trim()).filter(Boolean);
        const max = Number(el.dataset.maxItems || 10);
        const [min, longest] = [Number(el.dataset.itemMin || 1), Number(el.dataset.itemMax || 255)];
        if (items.length > max) {
            return t('Enter at most :max, separated by commas.', { max });
        }
        return items.every((item) => item.length >= min && item.length <= longest) ? '' : t('Each one needs :min to :max characters.', { min, max: longest });
    },
    integer(value, el) {
        return /^\d+$/.test(value) ? range(Number(value), el) : t('Enter a whole number.');
    },
    decimal(value, el) {
        return /^\d+(\.\d+)?$/.test(value) ? range(Number(value), el) : t('Enter a number, e.g. 98.6.');
    },
    money(value) {
        return /^\d{1,7}(\.\d{1,2})?$/.test(value) ? '' : t('Enter an amount like 500 or 1250.50.');
    },
    'dose-slot'(value) {
        return SLOT.test(value.replace(/\s+/g, '')) ? '' : t('Use 0, ½, 1, 1½, 2 …');
    },
    'past-date'(value) {
        return value <= today() ? '' : t('The date cannot be in the future.');
    },
    'future-date'(value) {
        return value >= today() ? '' : t('The date cannot be in the past.');
    },
};

function range(number, el) {
    const min = el.dataset.min === undefined ? null : Number(el.dataset.min);
    const max = el.dataset.max === undefined ? null : Number(el.dataset.max);
    if ((min !== null && number < min) || (max !== null && number > max)) {
        return min !== null && max !== null ? t('Enter a value from :min to :max.', { min, max }) : min !== null ? t('Enter at least :min.', { min }) : t('Enter at most :max.', { max });
    }
    return '';
}

function today() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

// "medicines[0][dose_morning]" -> "medicines.0.dose_morning", the key Laravel uses for errors.
function keyOf(name) {
    return name.replace(/\]/g, '').replace(/\[/g, '.');
}

function isField(el) {
    return el.name && !el.disabled && !['hidden', 'submit', 'button', 'reset'].includes(el.type) && el.type !== 'radio' && el.type !== 'checkbox';
}

export default function validatedForm(serverErrors = {}) {
    return {
        errors: Object.fromEntries(Object.entries(serverErrors || {}).map(([key, messages]) => [key, [].concat(messages)[0]])),
        touchedFields: {},
        clientKeys: {},
        submitted: Object.keys(serverErrors || {}).length > 0,
        timers: {},
        formEl: null,
        otherErrors: [],

        init() {
            // Kept here: inside a nested x-data, `$el` would be that scope's element.
            this.formEl = this.$el;
            this.formEl.setAttribute('novalidate', '');
            // Rows render after this component, so mark server errors on the next tick.
            this.$nextTick(() => {
                this.fields().forEach((el) => this.mark(el, this.errors[keyOf(el.name)]));
                this.otherErrors = Object.entries(this.errors).filter(([key, message]) => message && !this.shownInPlace(key)).map(([, message]) => message);
            });

            this.formEl.addEventListener('input', (e) => this.onInput(e.target));
            this.formEl.addEventListener('change', (e) => this.check(e.target));
            this.formEl.addEventListener('focusout', (e) => {
                if (isField(e.target)) {
                    this.touchedFields[e.target.name] = true;
                    this.check(e.target);
                }
            });
            this.formEl.addEventListener('submit', (e) => this.validate(e));
            // Rows were removed or moved (indexes changed), or a script filled fields: check again.
            this.formEl.addEventListener('recheck-fields', () => this.$nextTick(() => this.recheck()));
        },

        fields() {
            return Array.from(this.formEl.elements).filter(isField);
        },

        // A server error is shown in place when its field, its `{key}-error` line or its row is on the
        // form; the rest (e.g. "status") are listed by <x-form-errors />.
        shownInPlace(key) {
            const prefix = key.includes('.') ? key.slice(0, key.lastIndexOf('.')) : null;
            const hasLine = (k) => this.formEl.querySelector(`[id="${CSS.escape(`${k}-error`)}"]`) !== null;
            const keys = this.fields().map((el) => keyOf(el.name));
            return hasLine(key) || keys.includes(key) || (prefix !== null && (hasLine(prefix) || keys.some((k) => k.startsWith(`${prefix}.`))));
        },

        // The age follows the date of birth (whole years up to today) and is locked while a date is set.
        ageFromDob(dobEl, ageName) {
            const age = this.formEl.elements.namedItem(ageName);
            const dob = dobEl.value;
            age.readOnly = dob !== '';
            if (dob === '' || dob > today()) {
                return;
            }
            const [y, m, d] = dob.split('-').map(Number);
            const now = new Date();
            const birthdayPending = now.getMonth() + 1 < m || (now.getMonth() + 1 === m && now.getDate() < d);
            age.value = String(now.getFullYear() - y - (birthdayPending ? 1 : 0));
            this.check(age);
        },

        err(key) {
            return this.errors[key] || '';
        },

        rowErr(prefix) {
            const key = Object.keys(this.errors).find((k) => k.startsWith(`${prefix}.`) && this.errors[k]);
            return key ? this.errors[key] : '';
        },

        onInput(el) {
            if (!isField(el)) {
                return;
            }
            clearTimeout(this.timers[el.name]);
            this.timers[el.name] = setTimeout(() => this.check(el), 300);
        },

        message(el) {
            const value = (el.value ?? '').trim();
            // "Required" waits until the user has left the field or submitted; formats show while typing.
            const strict = this.submitted || this.touchedFields[el.name];

            if (el.validity?.badInput) {
                return el.type === 'date' ? t('Enter a complete date.') : t('Enter a valid value.');
            }
            if (value === '') {
                if (strict && el.required) {
                    return el.tagName === 'SELECT' ? t('Choose one.') : t('This field is required.');
                }
                const other = el.dataset.requiredWithout && this.formEl.elements.namedItem(el.dataset.requiredWithout);
                if (strict && other && (other.value ?? '').trim() === '') {
                    return el.dataset.requiredMessage || t('This field is required.');
                }
                return '';
            }
            if (el.maxLength > 0 && value.length > el.maxLength) {
                return t('Use at most :max characters.', { max: el.maxLength });
            }
            if (el.minLength > 0 && value.length < el.minLength) {
                return t('Use at least :min characters.', { min: el.minLength });
            }
            if (el.type === 'email' && el.validity?.typeMismatch) {
                return t('Enter a valid email address.');
            }
            if (el.dataset.pattern && !new RegExp(`^(?:${el.dataset.pattern})$`, 'u').test(value)) {
                return el.dataset.patternMessage || t('This format is not valid.');
            }
            const rule = RULES[el.dataset.rule];
            return rule ? rule(value, el) : '';
        },

        check(el) {
            if (!isField(el)) {
                return '';
            }
            const key = keyOf(el.name);
            const message = this.message(el);
            this.errors[key] = message;
            this.clientKeys[key] = true;
            this.mark(el, message);

            // A field that is "required without" this one may be satisfied now.
            this.fields()
                .filter((other) => other.dataset.requiredWithout === el.name)
                .forEach((other) => {
                    const otherKey = keyOf(other.name);
                    this.errors[otherKey] = this.message(other);
                    this.clientKeys[otherKey] = true;
                    this.mark(other, this.errors[otherKey]);
                });

            return message;
        },

        mark(el, message) {
            el.setAttribute('aria-invalid', message ? 'true' : 'false');
            const hint = el.id && document.getElementById(`${el.id}-error`);
            if (hint) {
                el.setAttribute('aria-describedby', hint.id);
            }
        },

        recheck() {
            const present = new Set(this.fields().map((el) => keyOf(el.name)));
            for (const key of Object.keys(this.clientKeys)) {
                if (!present.has(key)) {
                    delete this.errors[key];
                    delete this.clientKeys[key];
                }
            }
            this.fields().filter((el) => this.clientKeys[keyOf(el.name)]).forEach((el) => this.check(el));
        },

        // @submit: block the post while a field is wrong and point at the first one.
        validate(event) {
            this.submitted = true;
            const invalid = this.fields().filter((el) => this.check(el));
            if (invalid.length) {
                event.preventDefault();
                invalid[0].focus();
                window.toast?.error(invalid.length === 1 ? t('Please fix the highlighted field.') : t('Please fix the :count highlighted fields.', { count: invalid.length }));
            }
        },
    };
}
