<?php

namespace App\Models;

use Database\Factories\AnggotaGrupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $grup_id
 * @property int|null $user_id
 * @property string $nama
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('anggota_grup')]
#[Fillable(['grup_id', 'user_id', 'nama'])]
class AnggotaGrup extends Model
{
    /** @use HasFactory<AnggotaGrupFactory> */
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
            'user_id' => 'integer',
        ];
    }

    /**
     * Apakah anggota ini sudah tercatat di tagihan atau pembayaran.
     *
     * Anggota yang sudah tercatat tidak boleh dihapus supaya saldo grup tidak berubah diam-diam.
     */
    public function sudahTercatat(): bool
    {
        return Tagihan::query()->where('dibayar_oleh', $this->id)->exists()
            || BagianTagihan::query()->where('anggota_grup_id', $this->id)->exists()
            || Pembayaran::query()->where('dari_anggota_id', $this->id)->orWhere('ke_anggota_id', $this->id)->exists();
    }

    /**
     * Apakah anggota ini boleh dikeluarkan dari grup.
     */
    public function bisaDihapus(): bool
    {
        return $this->user_id !== $this->grup->dibuat_oleh && ! $this->sudahTercatat();
    }

    /**
     * @return BelongsTo<Grup, $this>
     */
    public function grup(): BelongsTo
    {
        return $this->belongsTo(Grup::class, 'grup_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
