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
        Schema::create('grup', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('kode_undangan', 16)->unique();
            $table->foreignId('dibuat_oleh')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('anggota_grup', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grup_id')->constrained('grup')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama');
            $table->timestamps();

            $table->unique(['grup_id', 'user_id']);
        });

        Schema::create('tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grup_id')->constrained('grup')->cascadeOnDelete();
            $table->string('judul');
            $table->unsignedBigInteger('total');
            $table->foreignId('dibayar_oleh')->constrained('anggota_grup')->cascadeOnDelete();
            $table->string('cara_bagi', 20)->default('rata');
            $table->date('tanggal');
            $table->timestamps();
        });

        Schema::create('item_tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->constrained('tagihan')->cascadeOnDelete();
            $table->string('nama');
            $table->unsignedBigInteger('harga');
            $table->unsignedInteger('jumlah')->default(1);
            $table->timestamps();
        });

        Schema::create('item_tagihan_anggota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_tagihan_id')->constrained('item_tagihan')->cascadeOnDelete();
            $table->foreignId('anggota_grup_id')->constrained('anggota_grup')->cascadeOnDelete();

            $table->unique(['item_tagihan_id', 'anggota_grup_id'], 'item_anggota_unique');
        });

        Schema::create('bagian_tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->constrained('tagihan')->cascadeOnDelete();
            $table->foreignId('anggota_grup_id')->constrained('anggota_grup')->cascadeOnDelete();
            $table->unsignedBigInteger('jumlah');
            $table->timestamps();

            $table->unique(['tagihan_id', 'anggota_grup_id']);
        });

        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grup_id')->constrained('grup')->cascadeOnDelete();
            $table->foreignId('tagihan_id')->nullable()->constrained('tagihan')->nullOnDelete();
            $table->foreignId('dari_anggota_id')->constrained('anggota_grup')->cascadeOnDelete();
            $table->foreignId('ke_anggota_id')->constrained('anggota_grup')->cascadeOnDelete();
            $table->unsignedBigInteger('jumlah');
            $table->dateTime('dibayar_pada');
            $table->string('catatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
        Schema::dropIfExists('bagian_tagihan');
        Schema::dropIfExists('item_tagihan_anggota');
        Schema::dropIfExists('item_tagihan');
        Schema::dropIfExists('tagihan');
        Schema::dropIfExists('anggota_grup');
        Schema::dropIfExists('grup');
    }
};
