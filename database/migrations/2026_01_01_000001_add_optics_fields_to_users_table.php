<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 25)->nullable()->after('email');
            $table->string('role', 30)->default('customer')->after('password');
            $table->string('membership_tier', 30)->default('Silver')->after('role');
            $table->string('prescription_od', 60)->nullable()->after('membership_tier');
            $table->string('prescription_os', 60)->nullable()->after('prescription_od');
            $table->date('last_exam_date')->nullable()->after('prescription_os');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'role',
                'membership_tier',
                'prescription_od',
                'prescription_os',
                'last_exam_date',
            ]);
        });
    }
};
