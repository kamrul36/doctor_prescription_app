<nav class="mb-6 flex gap-1 border-b border-gray-200 text-sm" aria-label="Catalogs">
    @foreach ([
        ['catalog.lab-tests.index', 'catalog.lab-tests.*', 'Lab tests'],
        ['catalog.procedures.index', 'catalog.procedures.*', 'Procedures'],
        ['catalog.advice-templates.index', 'catalog.advice-templates.*', 'Advice templates'],
    ] as [$route, $pattern, $label])
        <a href="{{ route($route) }}"
           @class([
               '-mb-px border-b-2 px-4 py-2 font-medium',
               'border-teal-700 text-teal-800' => request()->routeIs($pattern),
               'border-transparent text-gray-600 hover:text-gray-900' => ! request()->routeIs($pattern),
           ])>{{ $label }}</a>
    @endforeach
</nav>
