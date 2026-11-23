<?php

namespace App\Models;

use App\Enums\StatusPembayaran;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $grup_id
 * @property int|null $tagihan_id
 * @property int $dari_anggota_id
 * @property int $ke_anggota_id
 * @property int $jumlah
 * @property StatusPembayaran|null $status
 * @property string|null $bukti_path
 * @property int|null $dicatat_oleh
 * @property Carbon $dibayar_pada
 * @property Carbon|null $dikonfirmasi_pada
 * @property string|null $catatan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('pembayaran')]
#[Fillable(['grup_id', 'tagihan_id', 'dari_anggota_id', 'ke_anggota_id', 'jumlah', 'status', 'bukti_path', 'dicatat_oleh', 'dibayar_pada', 'dikonfirmasi_pada', 'catatan'])]
class Pembayaran extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grup_id' => 'integer',
            'tagihan_id' => 'integer',
            'dari_anggota_id' => 'integer',
            'ke_anggota_id' => 'integer',
            'jumlah' => 'integer',
            'status' => StatusPembayaran::class,
            'dibayar_pada' => 'datetime',
            'dikonfirmasi_pada' => 'datetime',
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
     * @return BelongsTo<Tagihan, $this>
     */
    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class, 'tagihan_id');
    }

    /**
     * @return BelongsTo<AnggotaGrup, $this>
     */
    public function dari(): BelongsTo
    {
        return $this->belongsTo(AnggotaGrup::class, 'dari_anggota_id');
    }

    /**
     * @return BelongsTo<AnggotaGrup, $this>
     */
    public function ke(): BelongsTo
    {
        return $this->belongsTo(AnggotaGrup::class, 'ke_anggota_id');
    }

    /**
     * Hapus pembayaran ini beserta file bukti transfernya.
     */
    public function hapusDenganBukti(): void
    {
        if ($this->bukti_path !== null) {
            Storage::disk('local')->delete($this->bukti_path);
        }

        $this->delete();
    }
}
