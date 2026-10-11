{{--
    The error line under a field inside a validatedForm (spec §10.1): shows the browser check
    while typing, or the server's message after a failed save. `for` is the Laravel error key
    (dot notation); the id `{for}-error` lets the field point at it with aria-describedby.
--}}
@props(['for'])
<p id="{{ $for }}-error" {{ $attributes->merge(['class' => 'mt-1 text-sm text-red-700']) }} x-text="err(@js($for))"></p>
