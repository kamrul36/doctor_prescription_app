<?php

namespace App\Providers;

use App\Domain\Access\Policies\RolePolicy;
use App\Domain\Access\Policies\UserPolicy;
use App\Domain\Access\Role;
use App\Domain\Catalog\Models\AdviceTemplate;
use App\Domain\Catalog\Models\LabTest;
use App\Domain\Catalog\Models\Procedure;
use App\Domain\Catalog\Policies\CatalogPolicy;
use App\Domain\Patient\Models\Patient;
use App\Domain\Patient\Policies\PatientPolicy;
use App\Domain\Practice\Models\Chamber;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Domain\Practice\Policies\PracticeSetupPolicy;
use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role as RoleModel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super admin passes every check. Business rules that must hold even
        // for them (e.g. the super_admin role is immutable) live in Actions.
        Gate::before(fn ($user) => $user instanceof User && $user->isSuperAdmin() ? true : null);

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(RoleModel::class, RolePolicy::class);
        Gate::policy(Patient::class, PatientPolicy::class);
        foreach ([LabTest::class, Procedure::class, AdviceTemplate::class] as $model) {
            Gate::policy($model, CatalogPolicy::class);
        }
        foreach ([Chamber::class, Doctor::class, PrescriptionTemplate::class] as $model) {
            Gate::policy($model, PracticeSetupPolicy::class);
        }

        // OpenAPI docs at /docs/api: Scramble opens them in `local`; elsewhere
        // only for a super admin (session login).
        Gate::define('viewApiDocs', fn (?User $user) => (bool) $user?->isSuperAdmin());

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(SecurityScheme::http('bearer', 'JWT'));
            });
    }
}
