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
        Schema::create('tagihan_rutin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grup_id')->constrained('grup')->cascadeOnDelete();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('judul');
            $table->unsignedBigInteger('total');
            $table->foreignId('dibayar_oleh')->constrained('anggota_grup')->cascadeOnDelete();
            $table->json('peserta');
            $table->string('frekuensi', 20);
            $table->date('tanggal_mulai');
            $table->unsignedInteger('jumlah_dibuat')->default(0);
            $table->date('jadwal_berikutnya')->index();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::table('tagihan', function (Blueprint $table) {
            $table->foreignId('tagihan_rutin_id')->nullable()->after('grup_id')->constrained('tagihan_rutin')->nullOnDelete();
            $table->timestamp('diingatkan_pada')->nullable()->after('tanggal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tagihan_rutin_id');
            $table->dropColumn('diingatkan_pada');
        });

        Schema::dropIfExists('tagihan_rutin');
    }
};
