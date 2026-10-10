<?php

namespace App\Domain\Practice;

/**
 * The blocks a prescription can show. A template decides which appear, in
 * which zone and under which label; the print layer has one partial per key.
 */
enum SectionKey: string
{
    case PatientBlock = 'patient_block';
    case Complaints = 'complaints';
    // One block per specialty on the doctor's profile (e.g. the gynae menstrual history); empty for a general physician.
    case Specialty = 'specialty';
    case Vitals = 'vitals';
    case Examination = 'examination';
    case Diagnosis = 'diagnosis';
    case TreatmentPlan = 'treatment_plan';
    case InvestigationsAdvised = 'investigations_advised';
    case InvestigationsReviewed = 'investigations_reviewed';
    case Medicines = 'medicines';
    case Advice = 'advice';
    case FollowUp = 'follow_up';

    /** @return array{en: string, bn: string} */
    public function defaultLabels(): array
    {
        return match ($this) {
            self::PatientBlock => ['en' => 'Patient', 'bn' => 'রোগীর তথ্য'],
            self::Complaints => ['en' => 'Complaints', 'bn' => 'অভিযোগ'],
            self::Specialty => ['en' => 'History', 'bn' => 'ইতিহাস'],
            self::Vitals => ['en' => 'Vitals', 'bn' => 'ভাইটাল সাইন'],
            self::Examination => ['en' => 'On Examination', 'bn' => 'পরীক্ষা-নিরীক্ষা'],
            self::Diagnosis => ['en' => 'Diagnosis', 'bn' => 'রোগ নির্ণয়'],
            self::TreatmentPlan => ['en' => 'Treatment Plan', 'bn' => 'চিকিৎসা পরিকল্পনা'],
            self::InvestigationsAdvised => ['en' => 'Investigations', 'bn' => 'পরীক্ষা'],
            self::InvestigationsReviewed => ['en' => 'Previous Reports', 'bn' => 'পূর্বের রিপোর্ট'],
            self::Medicines => ['en' => 'Rx', 'bn' => 'Rx'],
            self::Advice => ['en' => 'Advice', 'bn' => 'পরামর্শ'],
            self::FollowUp => ['en' => 'Follow-up', 'bn' => 'পরবর্তী সাক্ষাৎ'],
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
