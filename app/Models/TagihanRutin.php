<?php

namespace App\Models;

use App\Enums\CaraBagi;
use App\Enums\Frekuensi;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tagihan yang otomatis dicatat ulang tiap minggu atau tiap bulan,
 * misalnya wifi kos atau langganan bareng.
 *
 * @property int $id
 * @property int $grup_id
 * @property int|null $dibuat_oleh
 * @property string $judul
 * @property int $total
 * @property int $dibayar_oleh
 * @property list<int> $peserta
 * @property Frekuensi $frekuensi
 * @property CarbonImmutable $tanggal_mulai
 * @property int $jumlah_dibuat
 * @property CarbonImmutable $jadwal_berikutnya
 * @property bool $aktif
 */
#[Table('tagihan_rutin')]
#[Fillable(['grup_id', 'dibuat_oleh', 'judul', 'total', 'dibayar_oleh', 'peserta', 'frekuensi', 'tanggal_mulai', 'jumlah_dibuat', 'jadwal_berikutnya', 'aktif'])]
class TagihanRutin extends Model
{
    /**
     * Batas tagihan yang dikejar dalam sekali jalan kalau server sempat mati lama.
     */
    public const BATAS_SUSULAN = 6;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grup_id' => 'integer',
            'dibuat_oleh' => 'integer',
            'total' => 'integer',
            'dibayar_oleh' => 'integer',
            'peserta' => 'array',
            'frekuensi' => Frekuensi::class,
            'tanggal_mulai' => 'date',
            'jumlah_dibuat' => 'integer',
            'jadwal_berikutnya' => 'date',
            'aktif' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Grup, $this>
     */
    public function grup(): BelongsTo
    {
        return $this->belongsTo(Grup::class, 'grup_id');
    }

    /**
     * @return BelongsTo<AnggotaGrup, $this>
     */
    public function pembayar(): BelongsTo
    {
        return $this->belongsTo(AnggotaGrup::class, 'dibayar_oleh');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * @return HasMany<Tagihan, $this>
     */
    public function tagihan(): HasMany
    {
        return $this->hasMany(Tagihan::class, 'tagihan_rutin_id');
    }

    /**
     * Mulai tagihan rutin dari tagihan pertama yang baru dicatat.
     */
    public static function mulaiDari(Tagihan $tagihan, Frekuensi $frekuensi, User $oleh): self
    {
        $rutin = self::create([
            'grup_id' => $tagihan->grup_id,
            'dibuat_oleh' => $oleh->id,
            'judul' => $tagihan->judul,
            'total' => $tagihan->total,
            'dibayar_oleh' => $tagihan->dibayar_oleh,
            'peserta' => $tagihan->bagian()->orderBy('anggota_grup_id')->pluck('anggota_grup_id')->all(),
            'frekuensi' => $frekuensi,
            'tanggal_mulai' => $tagihan->tanggal,
            'jumlah_dibuat' => 1,
            'jadwal_berikutnya' => $frekuensi->tanggalKe($tagihan->tanggal, 1),
        ]);

        $tagihan->forceFill(['tagihan_rutin_id' => $rutin->id])->save();

        return $rutin;
    }

    /**
     * Catat semua tagihan yang sudah jatuh tempo sampai hari ini.
     *
     * @return int Jumlah tagihan yang dicatat.
     */
    public function jalankan(?CarbonInterface $hariIni = null): int
    {
        $hariIni = ($hariIni ?? now())->copy()->startOfDay();
        $dicatat = 0;

        while ($this->aktif && $this->jadwal_berikutnya->lte($hariIni) && $dicatat < self::BATAS_SUSULAN) {
            $peserta = $this->pesertaYangMasihAda();

            if ($peserta === [] || ! $this->grup->anggota()->whereKey($this->dibayar_oleh)->exists()) {
                $this->update(['aktif' => false]);

                break;
            }

            $tagihan = Tagihan::catat($this->grup, [
                'judul' => $this->judul,
                'tanggal' => $this->jadwal_berikutnya->toDateString(),
                'dibayar_oleh' => $this->dibayar_oleh,
                'cara_bagi' => CaraBagi::Rata,
                'total' => $this->total,
                'peserta' => $peserta,
                'item' => [],
            ], $this->pembuat ?? $this->grup->pembuat);

            $tagihan->forceFill(['tagihan_rutin_id' => $this->id])->save();

            $this->jumlah_dibuat++;
            $this->jadwal_berikutnya = $this->frekuensi->tanggalKe($this->tanggal_mulai, $this->jumlah_dibuat);
            $this->save();

            $dicatat++;
        }

        // Kalau masih tertinggal setelah batas susulan, lompat ke jadwal setelah hari ini.
        while ($this->aktif && $this->jadwal_berikutnya->lte($hariIni)) {
            $this->jumlah_dibuat++;
            $this->jadwal_berikutnya = $this->frekuensi->tanggalKe($this->tanggal_mulai, $this->jumlah_dibuat);
            $this->save();
        }

        return $dicatat;
    }

    /**
     * Ubah isi tagihan rutin. Tagihan yang sudah tercatat tidak ikut berubah.
     *
     * Kalau frekuensinya berganti, hitungan jadwal dimulai lagi dari jadwal berikutnya.
     *
     * @param  array{judul: string, total: int, frekuensi: Frekuensi, peserta: list<int>}  $data
     */
    public function ubah(array $data): void
    {
        if ($data['frekuensi'] !== $this->frekuensi) {
            $this->tanggal_mulai = $this->jadwal_berikutnya;
            $this->jumlah_dibuat = 0;
        }

        $peserta = $data['peserta'];
        sort($peserta);

        $this->fill([
            'judul' => $data['judul'],
            'total' => $data['total'],
            'frekuensi' => $data['frekuensi'],
            'peserta' => array_values(array_unique($peserta)),
        ])->save();
    }

    /**
     * Lanjutkan tagihan yang dijeda. Jadwal yang terlewat selama dijeda tidak ditagih.
     */
    public function lanjutkan(?CarbonInterface $hariIni = null): void
    {
        $hariIni = ($hariIni ?? now())->copy()->startOfDay();
        $this->aktif = true;

        while ($this->jadwal_berikutnya->lt($hariIni)) {
            $this->jumlah_dibuat++;
            $this->jadwal_berikutnya = $this->frekuensi->tanggalKe($this->tanggal_mulai, $this->jumlah_dibuat);
        }

        $this->save();
    }

    /**
     * Apakah user ini boleh menjeda atau menghapus tagihan rutin.
     */
    public function bisaDiubahOleh(User $user): bool
    {
        return $this->dibuat_oleh === $user->id
            || $this->pembayar?->user_id === $user->id
            || $this->grup->dibuat_oleh === $user->id;
    }

    /**
     * Peserta yang masih menjadi anggota grup.
     *
     * @return list<int>
     */
    private function pesertaYangMasihAda(): array
    {
        $ada = $this->grup->anggota()->whereIn('id', $this->peserta)->orderBy('id')->pluck('id')->all();

        return array_values(array_map('intval', $ada));
    }
}
