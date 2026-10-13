{{-- The বাংলা | EN switch (spec §6). Saved on the account when logged in, in the session otherwise. --}}
<form method="POST" action="{{ route('locale.update') }}" class="{{ $class ?? '' }} inline-flex overflow-hidden rounded-md text-xs font-medium ring-1 ring-gray-300" aria-label="{{ __('Language') }}">
    @csrf
    @method('PUT')
    @foreach (['bn' => 'বাংলা', 'en' => 'EN'] as $code => $name)
        <button type="submit" name="locale" value="{{ $code }}" lang="{{ $code }}" aria-pressed="{{ app()->getLocale() === $code ? 'true' : 'false' }}"
                @class([
                    'px-2.5 py-1 transition',
                    'bg-teal-700 text-white' => app()->getLocale() === $code,
                    'bg-white text-gray-600 hover:bg-gray-100' => app()->getLocale() !== $code,
                ])>{{ $name }}</button>
    @endforeach
</form>
