// UI strings for the scripts. The layout puts the current language's JSON translations
// (lang/bn.json) in window.APP_I18N; the English text is the key, so English needs no
// dictionary and a missing entry shows in English. Placeholders work like Laravel's: t('Enter at most :max.', { max: 3 }).
export function t(text, replace = {}) {
    let out = (window.APP_I18N || {})[text] ?? text;
    for (const [key, value] of Object.entries(replace).sort(([a], [b]) => b.length - a.length)) {
        out = out.replaceAll(`:${key}`, String(value));
    }
    return out;
}
