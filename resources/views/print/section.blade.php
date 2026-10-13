{{-- One printed section: its label and items (an item = optional title + lines). Medicines are numbered. --}}
<section data-section="{{ $section['key'] }}">
    @if ($section['key'] === 'medicines')
        <h2 class="rx">{{ $section['label'] === 'Rx' ? '℞' : $section['label'] }}</h2>
        <ol class="medicines">
            @foreach ($section['items'] as $item)
                <li>
                    <span class="item-title">{{ $item['title'] }}</span>
                    @foreach ($item['lines'] as $line)
                        <span class="line">{{ $line }}</span>
                    @endforeach
                </li>
            @endforeach
        </ol>
    @else
        <h2>{{ $section['label'] }}</h2>
        @if (count($section['items']) === 1 && $section['items'][0]['title'] === null)
            @foreach ($section['items'][0]['lines'] as $line)
                <div class="lines">{{ $line }}</div>
            @endforeach
        @else
            <ul>
                @foreach ($section['items'] as $item)
                    <li>
                        @if ($item['title'])<div class="item-title">{{ $item['title'] }}</div>@endif
                        @foreach ($item['lines'] as $line)
                            <div class="lines">{{ $line }}</div>
                        @endforeach
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</section>
