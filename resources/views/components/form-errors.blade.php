{{-- Inside a validatedForm: server errors that have no field on the form (e.g. "status", or a rule an Action checks). --}}
<div x-show="otherErrors.length" x-cloak {{ $attributes->merge(['class' => 'mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800']) }}>
    <ul class="list-inside list-disc">
        <template x-for="message in otherErrors">
            <li x-text="message"></li>
        </template>
    </ul>
</div>
