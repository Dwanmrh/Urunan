<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $grup_id
 * @property int|null $tagihan_id
 * @property int $dari_anggota_id
 * @property int $ke_anggota_id
 * @property int $jumlah
 * @property Carbon $dibayar_pada
 * @property string|null $catatan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('pembayaran')]
#[Fillable(['grup_id', 'tagihan_id', 'dari_anggota_id', 'ke_anggota_id', 'jumlah', 'dibayar_pada', 'catatan'])]
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
            'jumlah' => 'integer',
            'dibayar_pada' => 'datetime',
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
}
