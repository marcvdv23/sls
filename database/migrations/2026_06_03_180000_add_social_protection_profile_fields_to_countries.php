<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->text('social_protection_profile_url')->nullable()->after('social_security_administration_name');
            $table->timestamp('social_protection_profile_checked_at')->nullable()->after('social_protection_profile_url');
            $table->timestamp('social_protection_profile_last_success_at')->nullable()->after('social_protection_profile_checked_at');
            $table->text('social_protection_profile_last_error')->nullable()->after('social_protection_profile_last_success_at');
        });

        DB::table('countries')
            ->whereNotNull('iso_code')
            ->where('iso_code', '<>', '')
            ->update([
                'social_protection_profile_url' => DB::raw("CONCAT('https://www.social-protection.org/gimi/gess/ShowCountryProfile.action?iso=', UPPER(iso_code))"),
            ]);
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn([
                'social_protection_profile_url',
                'social_protection_profile_checked_at',
                'social_protection_profile_last_success_at',
                'social_protection_profile_last_error',
            ]);
        });
    }
};
