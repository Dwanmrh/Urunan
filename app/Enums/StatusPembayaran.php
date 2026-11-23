<?php

namespace App\Enums;

enum StatusPembayaran: string
{
    case Menunggu = 'menunggu';
    case Diterima = 'diterima';
}
