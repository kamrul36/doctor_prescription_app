<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** chambers.phone (one comma-separated string) becomes chambers.phones (a JSON list). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chambers', function (Blueprint $table) {
            $table->json('phones')->nullable()->after('address_bn');
        });

        foreach (DB::table('chambers')->whereNotNull('phone')->get(['id', 'phone']) as $row) {
            $list = array_values(array_filter(array_map(trim(...), explode(',', $row->phone)), fn ($p) => $p !== ''));

            DB::table('chambers')->where('id', $row->id)->update(['phones' => json_encode($list)]);
        }

        Schema::table('chambers', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }

    public function down(): void
    {
        Schema::table('chambers', function (Blueprint $table) {
            $table->string('phone', 64)->nullable()->after('address_bn');
        });

        foreach (DB::table('chambers')->whereNotNull('phones')->get(['id', 'phones']) as $row) {
            $list = json_decode($row->phones, true) ?: [];

            DB::table('chambers')->where('id', $row->id)->update(['phone' => $list ? mb_substr(implode(', ', $list), 0, 64) : null]);
        }

        Schema::table('chambers', function (Blueprint $table) {
            $table->dropColumn('phones');
        });
    }
};
