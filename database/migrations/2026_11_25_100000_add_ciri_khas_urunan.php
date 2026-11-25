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
        Schema::table('grup', function (Blueprint $table) {
            $table->string('emoji', 16)->default('🎉')->after('nama');
            $table->string('warna', 20)->default('indigo')->after('emoji');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('info_bayar')->nullable()->after('locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grup', function (Blueprint $table) {
            $table->dropColumn(['emoji', 'warna']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('info_bayar');
        });
    }
};
