<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tagihan_id
 * @property string $nama
 * @property int $harga Harga total baris ini dalam rupiah.
 * @property int $jumlah
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('item_tagihan')]
#[Fillable(['tagihan_id', 'nama', 'harga', 'jumlah'])]
class ItemTagihan extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'harga' => 'integer',
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
     * Anggota yang ikut menanggung item ini.
     *
     * @return BelongsToMany<AnggotaGrup, $this>
     */
    public function anggota(): BelongsToMany
    {
        return $this->belongsToMany(AnggotaGrup::class, 'item_tagihan_anggota', 'item_tagihan_id', 'anggota_grup_id');
    }
}
