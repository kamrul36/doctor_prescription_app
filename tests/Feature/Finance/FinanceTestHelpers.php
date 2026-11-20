<?php

namespace Tests\Feature\Finance;

use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Practice\Models\Chamber;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\Models\DoctorChamberFee;
use App\Models\User;

/** A doctor with a chamber and fees, and a visit saved through the pad, for the finance tests. */
trait FinanceTestHelpers
{
    /**
     * @param  array<string, string>  $fees  visit type => amount
     * @return array{0: User, 1: Doctor, 2: Chamber}
     */
    private function doctorWithFees(array $fees = ['new' => '500', 'follow_up' => '300']): array
    {
        $user = User::factory()->doctor()->create();
        $doctor = Doctor::create(['user_id' => $user->id, 'name_en' => 'Dr. '.$user->id, 'name_bn' => 'ডা. '.$user->id]);
        $chamber = Chamber::create(['name_en' => 'Chamber '.$user->id, 'name_bn' => 'চেম্বার', 'address_en' => 'Dhaka', 'phones' => ['01711000000']]);
        $doctor->chambers()->attach($chamber->id);

        foreach ($fees as $type => $amount) {
            DoctorChamberFee::create(['doctor_id' => $doctor->id, 'chamber_id' => $chamber->id, 'visit_type' => $type, 'amount' => $amount]);
        }

        return [$user, $doctor, $chamber];
    }

    /**
     * Saves a draft on the web pad for a new patient and returns the visit.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function saveVisit(User $user, Chamber $chamber, array $overrides = []): CaseHistory
    {
        $this->actingAs($user)->post('/prescriptions', $overrides + [
            'chamber_id' => $chamber->id,
            'visit_type' => 'new',
            'patient' => ['name' => 'Karim '.uniqid(), 'age_years' => '40', 'gender' => 'male'],
            'diagnosis' => 'Gingivitis',
            'action' => 'draft',
        ])->assertSessionHasNoErrors();

        return CaseHistory::query()->latest('id')->firstOrFail();
    }

    private function invoiceOf(CaseHistory $case): Invoice
    {
        return Invoice::query()->where('case_history_id', $case->id)->with(['lines', 'payments'])->firstOrFail();
    }
}
