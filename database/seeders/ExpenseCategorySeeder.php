<?php

namespace Database\Seeders;

use App\Domain\Finance\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

/**
 * Starter expense categories, added only while the list is empty, so a rename
 * or a deactivation by the user is never undone.
 */
class ExpenseCategorySeeder extends Seeder
{
    private const CATEGORIES = [
        ['Rent', 'ভাড়া'],
        ['Salary', 'বেতন'],
        ['Utilities (electricity, water, internet)', 'বিল (বিদ্যুৎ, পানি, ইন্টারনেট)'],
        ['Medical supplies', 'চিকিৎসা সামগ্রী'],
        ['Stationery and printing', 'স্টেশনারি ও প্রিন্টিং'],
        ['Other', 'অন্যান্য'],
    ];

    public function run(): void
    {
        if (ExpenseCategory::query()->exists()) {
            return;
        }

        foreach (self::CATEGORIES as [$en, $bn]) {
            ExpenseCategory::create(['name_en' => $en, 'name_bn' => $bn, 'is_active' => true]);
        }
    }
}
