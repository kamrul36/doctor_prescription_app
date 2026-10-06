<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    /** Every permission the application knows, for building a role editor. */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Role::class);

        return response()->json([
            'data' => array_map(fn (Permission $p) => [
                'name' => $p->value,
                'label' => $p->label(),
                'group' => $p->group(),
            ], Permission::cases()),
        ]);
    }
}
