import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';

// Toast notifications (spec §10.1): window.toast.success|warning|error|info(message).
// Success hides after 3 s; warnings and errors stay 6 s. All can be closed.
const notyf = new Notyf({
    duration: 3000, dismissible: true, position: { x: 'right', y: 'top' },
    types: [
        { type: 'success', background: '#0f766e' },
        { type: 'warning', background: '#d97706', duration: 6000, icon: false },
        { type: 'error', background: '#b91c1c', duration: 6000 },
        { type: 'info', background: '#334155', icon: false },
    ],
});

// Notyf renders the message as HTML; messages can hold user data (names), so escape them.
function escape(text) {
    const div = document.createElement('div');
    div.textContent = String(text ?? '');
    return div.innerHTML;
}

const show = (type) => (message) => notyf.open({ type, message: escape(message) });

window.toast = {
    success: show('success'),
    warning: show('warning'),
    error: show('error'),
    info: show('info'),
};
