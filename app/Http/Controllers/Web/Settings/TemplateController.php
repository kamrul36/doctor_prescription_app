<?php

namespace App\Http\Controllers\Web\Settings;

use App\Domain\Practice\Actions\SaveTemplateAction;
use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Domain\Practice\PrintMode;
use App\Domain\Practice\SectionKey;
use App\Domain\Practice\SectionZone;
use App\Domain\Practice\TemplateLayout;
use App\Http\Controllers\Controller;
use App\Http\Requests\Practice\SaveTemplateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', PrescriptionTemplate::class);

        return view('settings.templates.index', [
            'templates' => PrescriptionTemplate::query()->withCount('sections')->with('doctor')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', PrescriptionTemplate::class);

        $template = new PrescriptionTemplate([
            'paper_size' => 'A4', 'layout' => TemplateLayout::SingleColumn,
            'default_print_mode' => PrintMode::WithLetterhead, 'barcode_source' => 'prescription_no',
            'show_signature' => true, 'is_active' => true,
        ]);

        return view('settings.templates.create', $this->formData($template));
    }

    public function store(SaveTemplateRequest $request, SaveTemplateAction $save): RedirectResponse
    {
        $template = $save->handle($this->actor($request), null, $request->validated());

        return redirect()->route('settings.templates.index')->with('status', "Template {$template->name} created.");
    }

    public function edit(PrescriptionTemplate $template): View
    {
        Gate::authorize('update', $template);

        return view('settings.templates.edit', $this->formData($template->load('sections')));
    }

    public function update(SaveTemplateRequest $request, PrescriptionTemplate $template, SaveTemplateAction $save): RedirectResponse
    {
        $save->handle($this->actor($request), $template, $request->validated());

        return redirect()->route('settings.templates.index')->with('status', "Template {$template->name} updated.");
    }

    /** @return array<string, mixed> */
    private function formData(PrescriptionTemplate $template): array
    {
        // Existing sections first (in print order), then the keys not yet used, hidden.
        $rows = $template->exists ? $template->sections->keyBy(fn ($s) => $s->section_key->value) : collect();
        $sections = $rows->map(fn ($s) => [
            'section_key' => $s->section_key->value, 'zone' => $s->zone->value,
            'label_en' => $s->label_en, 'label_bn' => $s->label_bn, 'is_visible' => $s->is_visible,
        ])->values()->all();

        foreach (SectionKey::cases() as $key) {
            if (! $rows->has($key->value)) {
                $sections[] = [
                    'section_key' => $key->value, 'zone' => SectionZone::Right->value,
                    'label_en' => $key->defaultLabels()['en'], 'label_bn' => $key->defaultLabels()['bn'],
                    'is_visible' => false,
                ];
            }
        }

        return [
            'template' => $template,
            'sections' => $sections,
        ];
    }

    private function actor(SaveTemplateRequest $request): User
    {
        /** @var User */
        return $request->user();
    }
}
