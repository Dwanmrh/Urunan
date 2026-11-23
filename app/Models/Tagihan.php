<?php

namespace App\Models;

use App\Actions\Urunan\BagiRupiah;
use App\Enums\CaraBagi;
use App\Enums\StatusPembayaran;
use Database\Factories\TagihanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $grup_id
 * @property string $judul
 * @property int $total
 * @property int $dibayar_oleh
 * @property CaraBagi $cara_bagi
 * @property Carbon $tanggal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('tagihan')]
#[Fillable(['grup_id', 'judul', 'total', 'dibayar_oleh', 'cara_bagi', 'tanggal'])]
class Tagihan extends Model
{
    /** @use HasFactory<TagihanFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grup_id' => 'integer',
            'dibayar_oleh' => 'integer',
            'total' => 'integer',
            'cara_bagi' => CaraBagi::class,
            'tanggal' => 'date',
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
     * @return HasMany<ItemTagihan, $this>
     */
    public function item(): HasMany
    {
        return $this->hasMany(ItemTagihan::class, 'tagihan_id');
    }

    /**
     * @return HasMany<BagianTagihan, $this>
     */
    public function bagian(): HasMany
    {
        return $this->hasMany(BagianTagihan::class, 'tagihan_id');
    }

    /**
     * @return HasMany<Pembayaran, $this>
     */
    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'tagihan_id');
    }

    /**
     * Catat tagihan baru beserta item dan bagian tiap anggota.
     *
     * @param  array{judul: string, tanggal: string, dibayar_oleh: int, cara_bagi: CaraBagi, total: int, peserta: list<int>, item: list<array{nama: string, harga: int, penanggung: list<int>}>}  $data
     */
    public static function catat(Grup $grup, array $data): self
    {
        return DB::transaction(function () use ($grup, $data): self {
            $tagihan = $grup->tagihan()->make();
            $tagihan->isiDari($data);

            return $tagihan;
        });
    }

    /**
     * Ubah isi tagihan lalu hitung ulang bagian tiap anggota.
     *
     * Bukti bayar yang masih menunggu konfirmasi dibatalkan karena nominalnya bisa berubah.
     * Pembayaran yang sudah diterima tetap tercatat.
     *
     * @param  array{judul: string, tanggal: string, dibayar_oleh: int, cara_bagi: CaraBagi, total: int, peserta: list<int>, item: list<array{nama: string, harga: int, penanggung: list<int>}>}  $data
     */
    public function perbarui(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $this->batalkanPembayaranMenunggu();
            $this->item()->delete();
            $this->isiDari($data);
        });
    }

    /**
     * Hapus tagihan beserta bukti yang masih menunggu konfirmasi.
     *
     * Pembayaran yang sudah diterima tetap tercatat di grup supaya saldo tetap benar.
     */
    public function hapus(): void
    {
        DB::transaction(function (): void {
            $this->batalkanPembayaranMenunggu();
            $this->delete();
        });
    }

    /**
     * Apakah user boleh mengubah, menghapus, dan mengonfirmasi pembayaran tagihan ini.
     *
     * Yang berhak adalah pembayar tagihan. Kalau pembayar tidak punya akun,
     * hak ini dipegang pembuat grup.
     */
    public function bisaDikelolaOleh(User $user): bool
    {
        $idUserPembayar = $this->pembayar->user_id;

        if ($idUserPembayar !== null) {
            return $idUserPembayar === $user->id;
        }

        return $this->grup->dibuat_oleh === $user->id;
    }

    /**
     * Hitung bagian tiap anggota sesuai cara bagi tagihan ini.
     *
     * @param  list<int>|null  $idPeserta  Anggota yang ikut menanggung. Kosongkan untuk semua anggota grup.
     * @return array<int, int> Bagian tiap anggota, dengan id anggota sebagai kunci.
     */
    public function hitungBagian(?array $idPeserta = null): array
    {
        $idPeserta ??= $this->grup->anggota()->orderBy('id')->pluck('id')->all();
        sort($idPeserta);

        if ($this->cara_bagi === CaraBagi::Rata) {
            return BagiRupiah::rata($this->total, $idPeserta);
        }

        $item = $this->item()->with('anggota')->orderBy('id')->get()
            ->map(fn (ItemTagihan $item): array => [
                'harga' => $item->harga,
                'penanggung' => $item->anggota->pluck('id')->all(),
            ])
            ->all();

        return BagiRupiah::perItem($item, $idPeserta);
    }

    /**
     * Hitung ulang lalu simpan bagian tiap anggota untuk tagihan ini.
     *
     * @param  list<int>|null  $idPeserta  Anggota yang ikut menanggung. Kosongkan untuk semua anggota grup.
     */
    public function simpanBagian(?array $idPeserta = null): void
    {
        DB::transaction(function () use ($idPeserta): void {
            $this->bagian()->delete();

            foreach ($this->hitungBagian($idPeserta) as $id => $jumlah) {
                $this->bagian()->create([
                    'anggota_grup_id' => $id,
                    'jumlah' => $jumlah,
                ]);
            }
        });
    }

    /**
     * Status pelunasan tiap anggota yang menanggung tagihan ini.
     *
     * @return Collection<int, array{anggota: AnggotaGrup, bagian: int, dibayar: int, sisa: int, pembayar: bool, menunggu: Pembayaran|null}>
     */
    public function statusPelunasan(): Collection
    {
        $pembayaran = $this->pembayaran()->where('ke_anggota_id', $this->dibayar_oleh)->get();

        $diterima = $pembayaran
            ->filter(fn (Pembayaran $pembayaran): bool => $pembayaran->status === StatusPembayaran::Diterima)
            ->groupBy('dari_anggota_id')
            ->map(fn (Collection $daftar): int => $daftar->sum('jumlah'));

        $menunggu = $pembayaran
            ->filter(fn (Pembayaran $pembayaran): bool => $pembayaran->status === StatusPembayaran::Menunggu)
            ->keyBy('dari_anggota_id');

        return $this->bagian()->with('anggota')->orderBy('anggota_grup_id')->get()
            ->map(function (BagianTagihan $bagian) use ($diterima, $menunggu): array {
                $pembayar = $bagian->anggota_grup_id === $this->dibayar_oleh;
                $sudahDibayar = $pembayar ? $bagian->jumlah : (int) ($diterima[$bagian->anggota_grup_id] ?? 0);
                $sisa = max(0, $bagian->jumlah - $sudahDibayar);

                return [
                    'anggota' => $bagian->anggota,
                    'bagian' => $bagian->jumlah,
                    'dibayar' => $sudahDibayar,
                    'sisa' => $sisa,
                    'pembayar' => $pembayar,
                    'menunggu' => $sisa > 0 ? $menunggu->get($bagian->anggota_grup_id) : null,
                ];
            });
    }

    /**
     * Catat bahwa anggota sudah melunasi sisa bagiannya, misalnya bayar tunai.
     *
     * Kalau anggota sudah mengirim bukti, bukti itu yang dikonfirmasi.
     * Tidak mencatat apa pun kalau anggota adalah pembayar atau bagiannya sudah lunas.
     */
    public function tandaiLunas(AnggotaGrup $anggota, User $oleh): ?Pembayaran
    {
        $status = $this->statusAnggota($anggota);

        if ($status === null || $status['pembayar'] || $status['sisa'] === 0) {
            return null;
        }

        if ($status['menunggu'] !== null) {
            return $this->konfirmasi($status['menunggu']);
        }

        return $this->pembayaran()->create([
            'grup_id' => $this->grup_id,
            'dari_anggota_id' => $anggota->id,
            'ke_anggota_id' => $this->dibayar_oleh,
            'jumlah' => $status['sisa'],
            'status' => StatusPembayaran::Diterima,
            'dicatat_oleh' => $oleh->id,
            'dibayar_pada' => now(),
            'dikonfirmasi_pada' => now(),
        ]);
    }

    /**
     * Simpan bukti transfer dari anggota sebagai pembayaran yang menunggu konfirmasi.
     *
     * Tidak mencatat apa pun kalau anggota adalah pembayar, sudah lunas, atau buktinya masih menunggu.
     */
    public function kirimBukti(AnggotaGrup $anggota, string $pathBukti, User $oleh): ?Pembayaran
    {
        $status = $this->statusAnggota($anggota);

        if ($status === null || $status['pembayar'] || $status['sisa'] === 0 || $status['menunggu'] !== null) {
            return null;
        }

        return $this->pembayaran()->create([
            'grup_id' => $this->grup_id,
            'dari_anggota_id' => $anggota->id,
            'ke_anggota_id' => $this->dibayar_oleh,
            'jumlah' => $status['sisa'],
            'status' => StatusPembayaran::Menunggu,
            'bukti_path' => $pathBukti,
            'dicatat_oleh' => $oleh->id,
            'dibayar_pada' => now(),
        ]);
    }

    /**
     * Terima bukti bayar yang menunggu konfirmasi.
     */
    public function konfirmasi(Pembayaran $pembayaran): Pembayaran
    {
        if ($pembayaran->status === StatusPembayaran::Menunggu) {
            $pembayaran->update([
                'status' => StatusPembayaran::Diterima,
                'dikonfirmasi_pada' => now(),
            ]);
        }

        return $pembayaran;
    }

    /**
     * Tolak bukti bayar yang menunggu konfirmasi, lalu hapus filenya.
     */
    public function tolak(Pembayaran $pembayaran): void
    {
        if ($pembayaran->status === StatusPembayaran::Menunggu) {
            $pembayaran->hapusDenganBukti();
        }
    }

    /**
     * @return array{anggota: AnggotaGrup, bagian: int, dibayar: int, sisa: int, pembayar: bool, menunggu: Pembayaran|null}|null
     */
    private function statusAnggota(AnggotaGrup $anggota): ?array
    {
        return $this->statusPelunasan()
            ->first(fn (array $baris): bool => $baris['anggota']->id === $anggota->id);
    }

    /**
     * Isi kolom, item, dan bagian tiap anggota dari data form.
     *
     * @param  array{judul: string, tanggal: string, dibayar_oleh: int, cara_bagi: CaraBagi, total: int, peserta: list<int>, item: list<array{nama: string, harga: int, penanggung: list<int>}>}  $data
     */
    private function isiDari(array $data): void
    {
        $perItem = $data['cara_bagi'] === CaraBagi::PerItem;

        $this->fill([
            'judul' => $data['judul'],
            'tanggal' => $data['tanggal'],
            'dibayar_oleh' => $data['dibayar_oleh'],
            'cara_bagi' => $data['cara_bagi'],
            'total' => $perItem ? array_sum(array_column($data['item'], 'harga')) : $data['total'],
        ])->save();

        if ($perItem) {
            foreach ($data['item'] as $baris) {
                $item = $this->item()->create([
                    'nama' => $baris['nama'],
                    'harga' => $baris['harga'],
                ]);

                $item->anggota()->attach($baris['penanggung']);
            }
        }

        $this->unsetRelation('pembayar');
        $this->simpanBagian($perItem ? null : $data['peserta']);
    }

    /**
     * Hapus semua bukti bayar tagihan ini yang masih menunggu konfirmasi.
     */
    private function batalkanPembayaranMenunggu(): void
    {
        $this->pembayaran()
            ->where('status', StatusPembayaran::Menunggu->value)
            ->get()
            ->each(fn (Pembayaran $pembayaran) => $pembayaran->hapusDenganBukti());
    }
}
