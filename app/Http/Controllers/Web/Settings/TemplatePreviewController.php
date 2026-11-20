<?php

namespace App\Http\Controllers\Web\Settings;

use App\Domain\Document\ExportService;
use App\Domain\Document\Support\SamplePrescription;
use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Domain\Practice\Models\TemplateSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Practice\PreviewTemplateRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;

/**
 * Live preview for the template editor: the real A4 print view, laid out with the
 * unsaved form state, filled with a sample visit. Writes nothing.
 */
class TemplatePreviewController extends Controller
{
    public function __invoke(PreviewTemplateRequest $request, ExportService $export): Response
    {
        $data = $request->validated();

        $template = new PrescriptionTemplate(collect($data)->except(['sections', 'lang', 'mode', 'bn_digits'])->all());
        $template->setRelation('sections', new Collection(array_map(
            fn (array $section, int $i) => new TemplateSection([...$section, 'sort_order' => $i, 'is_visible' => (bool) ($section['is_visible'] ?? false)]),
            $data['sections'],
            array_keys($data['sections']),
        )));

        /** @var User $user */
        $user = $request->user();

        return response($export->html(SamplePrescription::make($user), $request->options(), template: $template))
            ->header('Cache-Control', 'no-store');
    }
}
