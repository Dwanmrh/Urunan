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
     * Hitung bagian tiap anggota sesuai cara bagi tagihan ini.
     *
     * @param  list<int>|null  $idPeserta  Anggota yang ikut menanggung. Kosongkan untuk semua anggota grup.
     * @return array<int, int> Bagian tiap anggota, dengan id anggota sebagai kunci.
     */
    public function hitungBagian(?array $idPeserta = null): array
    {
        $idPeserta ??= $this->grup->anggota()->orderBy('id')->pluck('id')->all();

        if ($this->cara_bagi === CaraBagi::Rata) {
            return BagiRupiah::rata($this->total, $idPeserta);
        }

        $bagian = [];

        foreach ($this->item()->with('anggota')->orderBy('id')->get() as $item) {
            $idPenanggung = $item->anggota->pluck('id')->sort()->values()->all();

            if ($idPenanggung === []) {
                $idPenanggung = $idPeserta;
            }

            foreach (BagiRupiah::rata($item->harga, $idPenanggung) as $id => $jumlah) {
                $bagian[$id] = ($bagian[$id] ?? 0) + $jumlah;
            }
        }

        ksort($bagian);

        return $bagian;
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
}
