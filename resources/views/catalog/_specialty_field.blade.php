{{-- Specialty select for a catalog form; empty = general (suggested to every doctor). Options come from the admin's list. --}}
@php
    $specialtyOptions = \App\Domain\Practice\Models\Specialty::options();
    // Keep a deactivated specialty the item already has, so saving other fields does not drop it.
    if ($item->specialty_id && ! $specialtyOptions->contains('id', $item->specialty_id) && $item->specialty) {
        $specialtyOptions->push($item->specialty);
    }
@endphp
<div>
    <label for="specialty_id" class="block text-sm font-medium">Specialty</label>
    <select id="specialty_id" name="specialty_id" class="{{ $input }}">
        <option value="">General (all doctors)</option>
        @foreach ($specialtyOptions as $option)
            <option value="{{ $option->id }}" @selected((string) old('specialty_id', $item->specialty_id) === (string) $option->id)>{{ $option->name }}{{ $option->is_active ? '' : ' (inactive)' }}</option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-500">Suggested first to doctors with this specialty; everyone can still find it.</p>
</div>
