<?php

namespace App\Domain\Practice\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a prescription template together with its sections.
 *
 * A new template belongs to the actor's doctor profile (global if they have
 * none); an existing one keeps its owner. Only one template per owner and
 * specialty can be the default. Templates are deactivated, never deleted,
 * because finalized visits will point at them.
 */
class SaveTemplateAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data  template fields plus a `sections` list (order = print order)
     */
    public function handle(User $actor, ?PrescriptionTemplate $template, array $data): PrescriptionTemplate
    {
        $creating = $template === null;
        $ownerId = $creating
            ? Doctor::query()->where('user_id', $actor->id)->value('id')
            : $template->doctor_id;

        $this->ensureCodeIsFree($ownerId, $data['code'], $template);

        return DB::transaction(function () use ($template, $creating, $ownerId, $data) {
            $template ??= new PrescriptionTemplate(['doctor_id' => $ownerId]);

            $template->fill(collect($data)->except('sections')->all())->save();
            $template->refresh(); // pick up column defaults for fields not submitted

            if ($template->is_default && $template->is_active) {
                PrescriptionTemplate::query()
                    ->where(fn ($q) => $template->doctor_id === null
                        ? $q->whereNull('doctor_id')
                        : $q->where('doctor_id', $template->doctor_id))
                    ->where('specialty_code', $template->specialty_code)
                    ->whereKeyNot($template->id)
                    ->update(['is_default' => false]);
            }

            if (array_key_exists('sections', $data)) {
                $template->sections()->delete();

                foreach (array_values($data['sections'] ?? []) as $i => $section) {
                    $template->sections()->create([
                        'section_key' => $section['section_key'],
                        'zone' => $section['zone'],
                        'label_en' => $section['label_en'] ?? null,
                        'label_bn' => $section['label_bn'] ?? null,
                        'is_visible' => $section['is_visible'] ?? true,
                        'sort_order' => $i,
                    ]);
                }
            }

            $this->audit->log($creating ? 'template.created' : 'template.updated', $template, [
                'code' => $template->code,
            ]);

            return $template->load('sections');
        });
    }

    private function ensureCodeIsFree(?int $ownerId, string $code, ?PrescriptionTemplate $template): void
    {
        $taken = PrescriptionTemplate::query()
            ->where('code', $code)
            ->where(fn ($q) => $ownerId === null ? $q->whereNull('doctor_id') : $q->where('doctor_id', $ownerId))
            ->when($template, fn ($q) => $q->whereKeyNot($template->id))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['code' => 'This template code is already in use.']);
        }
    }
}
