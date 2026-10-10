<?php

namespace App\Domain\Practice\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Practice\Models\Chamber;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a chamber (admin only; there can be several) and replaces
 * its branch list with the submitted one, in order.
 */
class SaveChamberAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data  chamber fields plus an optional `branches` list
     */
    public function handle(?Chamber $chamber, array $data): Chamber
    {
        return DB::transaction(function () use ($chamber, $data) {
            $creating = $chamber === null;
            $chamber ??= new Chamber;

            $chamber->fill(collect($data)->except('branches')->all())->save();

            if (array_key_exists('branches', $data)) {
                $chamber->branches()->delete();

                foreach (array_values($data['branches'] ?? []) as $i => $branch) {
                    $chamber->branches()->create([
                        'name_en' => $branch['name_en'],
                        'name_bn' => $branch['name_bn'] ?? null,
                        'phones' => $this->phones($branch['phones'] ?? []),
                        'sort_order' => $i,
                    ]);
                }
            }

            $this->audit->log($creating ? 'chamber.created' : 'chamber.updated', $chamber, [
                'fields' => array_keys(collect($data)->except('branches')->all()),
                'branches' => $chamber->branches()->count(),
            ]);

            return $chamber->load('branches');
        });
    }

    /**
     * The Blade form sends phones as one comma-separated string.
     *
     * @return list<string>
     */
    private function phones(array|string $phones): array
    {
        if (is_string($phones)) {
            $phones = explode(',', $phones);
        }

        return array_values(array_filter(array_map(trim(...), $phones), fn ($p) => $p !== ''));
    }
}
