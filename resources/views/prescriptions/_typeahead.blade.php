{{--
    A typeahead input inside an Alpine row (x-for). Parameters:
      $url      lookup URL;  $label  item field shown;  $name  JS expression for the input name
      $model    row field bound to the text;  $picked  JS run with `item` (null when the text is edited)
      $placeholder, $meta (optional JS expression for a grey hint per result)
--}}
<div class="relative" x-data="typeahead({ url: @js($url), label: @js($label) })" x-modelable="text" x-model="{{ $model }}"
     @picked="(item => { {{ $picked }} })($event.detail)">
    <input :name="{{ $name }}" x-model="text" @input="onInput()" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)"
           @keydown.enter="onEnter($event)" @keydown.escape="open = false" @blur="close()" autocomplete="off"
           placeholder="{{ $placeholder ?? '' }}" class="w-full rounded px-2 py-1.5 text-sm ring-1 ring-gray-300">
    <ul x-show="open" x-cloak class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded border border-gray-200 bg-white text-sm shadow-lg">
        <template x-for="(item, k) in results" :key="item.id">
            <li @mousedown.prevent="choose(k)" @mouseenter="active = k" :class="k === active ? 'bg-teal-50' : ''" class="flex cursor-pointer justify-between gap-2 px-3 py-1.5">
                <span x-text="labelOf(item)"></span>
                <span class="text-xs text-gray-500" x-text="{{ $meta ?? "item.specialty ?? ''" }}"></span>
            </li>
        </template>
    </ul>
</div>
