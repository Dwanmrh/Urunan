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
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->string('status', 20)->default('diterima')->after('jumlah');
            $table->string('bukti_path')->nullable()->after('status');
            $table->foreignId('dicatat_oleh')->nullable()->after('bukti_path')->constrained('users')->nullOnDelete();
            $table->dateTime('dikonfirmasi_pada')->nullable()->after('dibayar_pada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dicatat_oleh');
            $table->dropColumn(['status', 'bukti_path', 'dikonfirmasi_pada']);
        });
    }
};
