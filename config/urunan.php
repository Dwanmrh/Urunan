<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email admin
    |--------------------------------------------------------------------------
    |
    | Akun dengan email ini bisa membuka halaman statistik. Pisahkan dengan koma.
    |
    */

    'admin_emails' => array_values(array_filter(array_map(
        fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('URUNAN_ADMIN_EMAILS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Bahasa yang tersedia
    |--------------------------------------------------------------------------
    */

    'bahasa' => [
        'id' => 'Bahasa Indonesia',
        'en' => 'English',
    ],

];
