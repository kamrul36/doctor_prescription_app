<?php

namespace App\Domain\Clinical\Models;

use App\Domain\Clinical\TestKind;
use Illuminate\Database\Eloquent\Model;

/**
 * An investigation advised now (with a timing note such as "D2" or
 * "fasting"), or an earlier result reviewed at this visit.
 *
 * @property TestKind $kind
 * @property int|null $lab_test_id
 * @property string $name_snapshot
 * @property string|null $timing_note
 * @property string|null $result
 */
class OrderedTest extends Model
{
    protected $fillable = ['kind', 'lab_test_id', 'name_snapshot', 'timing_note', 'result', 'result_date', 'notes', 'sort_order'];

    protected function casts(): array
    {
        return ['kind' => TestKind::class, 'result_date' => 'date'];
    }
}
