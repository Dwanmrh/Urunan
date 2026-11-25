<?php

namespace App\Models;

use App\Enums\StatusPembayaran;
use App\Enums\WarnaGrup;
use Database\Factories\GrupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $nama
 * @property string $emoji
 * @property WarnaGrup $warna
 * @property string $kode_undangan
 * @property int $dibuat_oleh
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('grup')]
#[Fillable(['nama', 'emoji', 'warna', 'kode_undangan', 'dibuat_oleh'])]
class Grup extends Model
{
    /** @use HasFactory<GrupFactory> */
    use HasFactory;

    /**
     * Nilai bawaan sebelum disimpan, sama dengan bawaan kolom di database.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'emoji' => '🎉',
        'warna' => 'indigo',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dibuat_oleh' => 'integer',
            'warna' => WarnaGrup::class,
        ];
    }

    /**
     * Isi kode undangan secara otomatis saat grup dibuat.
     */
    protected static function booted(): void
    {
        static::creating(function (Grup $grup): void {
            $grup->kode_undangan ??= Str::lower(Str::random(8));
        });
    }

    /**
     * Buat grup baru dan langsung masukkan pembuatnya sebagai anggota.
     */
    public static function buatUntuk(User $user, string $nama, ?string $emoji = null, ?WarnaGrup $warna = null): self
    {
        return DB::transaction(function () use ($user, $nama, $emoji, $warna): self {
            $grup = self::create([
                'nama' => $nama,
                'emoji' => $emoji ?? '🎉',
                'warna' => $warna ?? WarnaGrup::Indigo,
                'dibuat_oleh' => $user->id,
            ]);

            $grup->anggota()->create([
                'user_id' => $user->id,
                'nama' => $user->name,
            ]);

            return $grup;
        });
    }

    /**
     * Apakah user ini termasuk anggota grup.
     */
    public function punyaAnggota(User $user): bool
    {
        return $this->anggota()->where('user_id', $user->id)->exists();
    }

    /**
     * Apakah user ini boleh mengganti nama, menghapus grup, dan mengatur anggota.
     */
    public function bisaDikelolaOleh(User $user): bool
    {
        return $this->dibuat_oleh === $user->id;
    }

    /**
     * Keluarkan user dari grup.
     *
     * Kalau namanya sudah tercatat di tagihan atau pembayaran, data itu tetap ada
     * dan dia menjadi anggota tanpa akun supaya saldo grup tetap benar.
     */
    public function keluarkan(User $user): void
    {
        $anggota = $this->anggota()->where('user_id', $user->id)->first();

        if ($anggota === null) {
            return;
        }

        if ($anggota->sudahTercatat()) {
            $anggota->update(['user_id' => null]);

            return;
        }

        $anggota->delete();
    }

    /**
     * Hapus grup beserta semua tagihan, pembayaran, dan foto bukti bayarnya.
     */
    public function hapusSemua(): void
    {
        $fotoBukti = $this->pembayaran()->whereNotNull('bukti_path')->pluck('bukti_path')->all();

        DB::transaction(fn () => $this->delete());

        Storage::disk('local')->delete($fotoBukti);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * @return HasMany<AnggotaGrup, $this>
     */
    public function anggota(): HasMany
    {
        return $this->hasMany(AnggotaGrup::class, 'grup_id');
    }

    /**
     * @return HasMany<Tagihan, $this>
     */
    public function tagihan(): HasMany
    {
        return $this->hasMany(Tagihan::class, 'grup_id');
    }

    /**
     * @return HasMany<TagihanRutin, $this>
     */
    public function tagihanRutin(): HasMany
    {
        return $this->hasMany(TagihanRutin::class, 'grup_id');
    }

    /**
     * @return HasMany<Pembayaran, $this>
     */
    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'grup_id');
    }

    /**
     * Hitung saldo tiap anggota di grup ini.
     *
     * Saldo positif berarti anggota akan menerima uang, saldo negatif berarti
     * anggota masih harus membayar.
     *
     * @return array<int, int> Saldo tiap anggota, dengan id anggota sebagai kunci.
     */
    public function saldo(): array
    {
        $saldo = $this->anggota()->orderBy('id')->pluck('id')
            ->mapWithKeys(fn (int $id): array => [$id => 0])
            ->all();

        $idTagihan = $this->tagihan()->pluck('id');

        $menalangi = $this->tagihan()
            ->selectRaw('dibayar_oleh, SUM(total) as jumlah')
            ->groupBy('dibayar_oleh')
            ->pluck('jumlah', 'dibayar_oleh');

        $bagian = BagianTagihan::query()
            ->whereIn('tagihan_id', $idTagihan)
            ->selectRaw('anggota_grup_id, SUM(jumlah) as jumlah')
            ->groupBy('anggota_grup_id')
            ->pluck('jumlah', 'anggota_grup_id');

        $membayar = $this->pembayaran()
            ->where('status', StatusPembayaran::Diterima->value)
            ->selectRaw('dari_anggota_id, SUM(jumlah) as jumlah')
            ->groupBy('dari_anggota_id')
            ->pluck('jumlah', 'dari_anggota_id');

        $menerima = $this->pembayaran()
            ->where('status', StatusPembayaran::Diterima->value)
            ->selectRaw('ke_anggota_id, SUM(jumlah) as jumlah')
            ->groupBy('ke_anggota_id')
            ->pluck('jumlah', 'ke_anggota_id');

        foreach (array_keys($saldo) as $id) {
            $saldo[$id] = (int) ($menalangi[$id] ?? 0)
                - (int) ($bagian[$id] ?? 0)
                + (int) ($membayar[$id] ?? 0)
                - (int) ($menerima[$id] ?? 0);
        }

        return $saldo;
    }
}
