<?php

namespace App\Models;

use App\Actions\Urunan\BagiRupiah;
use App\Enums\CaraBagi;
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
            $perItem = $data['cara_bagi'] === CaraBagi::PerItem;

            $tagihan = $grup->tagihan()->create([
                'judul' => $data['judul'],
                'tanggal' => $data['tanggal'],
                'dibayar_oleh' => $data['dibayar_oleh'],
                'cara_bagi' => $data['cara_bagi'],
                'total' => $perItem ? array_sum(array_column($data['item'], 'harga')) : $data['total'],
            ]);

            if ($perItem) {
                foreach ($data['item'] as $baris) {
                    $item = $tagihan->item()->create([
                        'nama' => $baris['nama'],
                        'harga' => $baris['harga'],
                    ]);

                    $item->anggota()->attach($baris['penanggung']);
                }
            }

            $tagihan->simpanBagian($perItem ? null : $data['peserta']);

            return $tagihan;
        });
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
     * @return Collection<int, array{anggota: AnggotaGrup, bagian: int, dibayar: int, sisa: int, pembayar: bool}>
     */
    public function statusPelunasan(): Collection
    {
        $dibayar = $this->pembayaran()
            ->where('ke_anggota_id', $this->dibayar_oleh)
            ->selectRaw('dari_anggota_id, SUM(jumlah) as jumlah')
            ->groupBy('dari_anggota_id')
            ->pluck('jumlah', 'dari_anggota_id');

        return $this->bagian()->with('anggota')->orderBy('anggota_grup_id')->get()
            ->map(function (BagianTagihan $bagian) use ($dibayar): array {
                $pembayar = $bagian->anggota_grup_id === $this->dibayar_oleh;
                $sudahDibayar = $pembayar ? $bagian->jumlah : (int) ($dibayar[$bagian->anggota_grup_id] ?? 0);

                return [
                    'anggota' => $bagian->anggota,
                    'bagian' => $bagian->jumlah,
                    'dibayar' => $sudahDibayar,
                    'sisa' => max(0, $bagian->jumlah - $sudahDibayar),
                    'pembayar' => $pembayar,
                ];
            });
    }

    /**
     * Catat bahwa anggota sudah melunasi sisa bagiannya ke pembayar tagihan.
     *
     * Tidak mencatat apa pun kalau anggota adalah pembayar atau bagiannya sudah lunas.
     */
    public function tandaiLunas(AnggotaGrup $anggota): ?Pembayaran
    {
        $status = $this->statusPelunasan()
            ->first(fn (array $baris): bool => $baris['anggota']->id === $anggota->id);

        if ($status === null || $status['pembayar'] || $status['sisa'] === 0) {
            return null;
        }

        return $this->pembayaran()->create([
            'grup_id' => $this->grup_id,
            'dari_anggota_id' => $anggota->id,
            'ke_anggota_id' => $this->dibayar_oleh,
            'jumlah' => $status['sisa'],
            'dibayar_pada' => now(),
        ]);
    }
}
