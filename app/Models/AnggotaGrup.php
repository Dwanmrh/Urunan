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
