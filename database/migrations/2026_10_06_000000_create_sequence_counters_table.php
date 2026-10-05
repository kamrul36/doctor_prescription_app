<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequence_counters', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();

            $table->unique(['key', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequence_counters');
    }
};
