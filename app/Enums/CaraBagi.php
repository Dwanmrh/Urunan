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
            self::Rata => __('Bagi rata'),
            self::PerItem => __('Per item'),
        };
    }
}
