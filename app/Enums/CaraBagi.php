<?php

namespace App\Enums;

enum CaraBagi: string
{
    case Rata = 'rata';
    case PerItem = 'per_item';

    /**
     * Label yang ditampilkan ke pengguna.
     */
    public function label(): string
    {
        return match ($this) {
            self::Rata => 'Bagi rata',
            self::PerItem => 'Per item',
        };
    }
}
