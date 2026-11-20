{{--
    Live preview: the real A4 print view, laid out with the unsaved form and a sample visit
    (TemplatePreviewController). Lives inside the <form> so it can read every field; its own
    controls have no name, so saving never sees them.
--}}
@use('App\Domain\Document\Support\PrintLocale')
@use('App\Domain\Practice\PrintMode')
<aside x-data="templatePreview(@js(route('settings.templates.preview')), @js(old('default_print_mode', $template->default_print_mode?->value ?? PrintMode::WithLetterhead->value)))"
       class="min-w-0 xl:sticky xl:top-20 xl:self-start" aria-labelledby="preview-title">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="preview-title" class="text-lg font-semibold">{{ __('Live preview') }}</h2>
        <span class="text-xs text-gray-500" x-show="loading" x-cloak>{{ __('Updating…') }}</span>
        <span class="text-xs text-red-700" x-show="failed && !loading" x-cloak>{{ __('Preview could not be updated.') }}</span>
    </div>
    <p class="text-xs text-gray-500">{{ __('Sample prescription. Changes show here at once; nothing is saved until you press Save.') }}</p>

    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
        <div class="inline-flex overflow-hidden rounded ring-1 ring-gray-300" role="radiogroup" aria-label="{{ __('Print language') }}">
            @foreach (PrintLocale::cases() as $locale)
                <button type="button" role="radio" :aria-checked="lang === @js($locale->value)" x-on:click="lang = @js($locale->value)"
                        :class="lang === @js($locale->value) ? 'bg-teal-700 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        class="px-2.5 py-1 not-first:border-l not-first:border-gray-300">{{ $locale->label() }}</button>
            @endforeach
        </div>
        <select x-model="mode" aria-label="{{ __('Print mode') }}" class="rounded px-2 py-1 ring-1 ring-gray-300">
            @foreach (PrintMode::cases() as $mode)
                <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
            @endforeach
        </select>
    </div>

    <div x-ref="box" class="relative mt-3 overflow-x-hidden overflow-y-auto xl:max-h-[calc(100vh-13rem)] rounded-lg border border-gray-200 bg-gray-200"
         :style="`height: ${Math.ceil(height * scale)}px`">
        <iframe x-ref="frame" title="{{ __('Live preview') }}" tabindex="-1" sandbox="allow-same-origin"
                class="absolute top-0 left-0 origin-top-left border-0"
                :style="`width: ${frameWidth}px; height: ${height}px; transform: scale(${scale})`"></iframe>
    </div>
</aside>
