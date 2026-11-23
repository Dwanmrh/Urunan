<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tagihan_id
 * @property int $anggota_grup_id
 * @property int $jumlah
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('bagian_tagihan')]
#[Fillable(['tagihan_id', 'anggota_grup_id', 'jumlah'])]
class BagianTagihan extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tagihan_id' => 'integer',
            'anggota_grup_id' => 'integer',
            'jumlah' => 'integer',
        ];
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
    public function anggota(): BelongsTo
    {
        return $this->belongsTo(AnggotaGrup::class, 'anggota_grup_id');
    }
}
