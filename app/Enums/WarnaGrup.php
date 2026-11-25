<?php

namespace App\Enums;

enum WarnaGrup: string
{
    // Pastel: lembut dan lucu, teks gelap.
    case Stroberi = 'stroberi';
    case Lavender = 'lavender';
    case Mint = 'mint';
    case Peach = 'peach';
    case Langit = 'langit';
    case Lemon = 'lemon';

    // Kalem: tenang dan gelap, teks terang.
    case Navy = 'navy';
    case Abu = 'abu';
    case Kopi = 'kopi';
    case Maroon = 'maroon';
    case Hutan = 'hutan';
    case Batu = 'batu';

    // Cerah: berani dan gonjreng.
    case Indigo = 'indigo';
    case Ungu = 'ungu';
    case Pink = 'pink';
    case Oranye = 'oranye';
    case Hijau = 'hijau';
    case Biru = 'biru';
    case Neon = 'neon';

    /**
     * Kelompok warna untuk pemilih warna.
     *
     * @return array<string, list<self>>
     */
    public static function kelompok(): array
    {
        return [
            __('Pastel') => [self::Stroberi, self::Lavender, self::Mint, self::Peach, self::Langit, self::Lemon],
            __('Kalem') => [self::Navy, self::Abu, self::Kopi, self::Maroon, self::Hutan, self::Batu],
            __('Cerah') => [self::Indigo, self::Ungu, self::Pink, self::Oranye, self::Hijau, self::Biru, self::Neon],
        ];
    }

    /**
     * Nama warna yang ditampilkan ke pengguna.
     */
    public function label(): string
    {
        return match ($this) {
            self::Stroberi => __('Stroberi'),
            self::Lavender => __('Lavender'),
            self::Mint => __('Mint'),
            self::Peach => __('Peach'),
            self::Langit => __('Langit'),
            self::Lemon => __('Lemon'),
            self::Navy => __('Navy'),
            self::Abu => __('Abu'),
            self::Kopi => __('Kopi'),
            self::Maroon => __('Maroon'),
            self::Hutan => __('Hutan'),
            self::Batu => __('Batu'),
            self::Indigo => __('Indigo'),
            self::Ungu => __('Ungu'),
            self::Pink => __('Pink'),
            self::Oranye => __('Oranye'),
            self::Hijau => __('Hijau'),
            self::Biru => __('Biru'),
            self::Neon => __('Neon'),
        };
    }

    /**
     * Apakah latarnya terang, sehingga teks di atasnya harus gelap supaya tetap tajam.
     */
    public function terang(): bool
    {
        return in_array($this, [self::Stroberi, self::Lavender, self::Mint, self::Peach, self::Langit, self::Lemon, self::Neon], true);
    }

    /**
     * Kelas latar bergradasi untuk sampul grup.
     */
    public function gradasi(): string
    {
        return match ($this) {
            self::Stroberi => 'from-pink-200 via-rose-200 to-pink-300 shadow-pink-300/30',
            self::Lavender => 'from-violet-200 via-purple-200 to-fuchsia-200 shadow-purple-300/30',
            self::Mint => 'from-emerald-200 via-teal-100 to-cyan-200 shadow-teal-300/30',
            self::Peach => 'from-orange-200 via-amber-100 to-rose-200 shadow-orange-300/30',
            self::Langit => 'from-sky-200 via-blue-100 to-indigo-200 shadow-sky-300/30',
            self::Lemon => 'from-yellow-100 via-amber-100 to-lime-200 shadow-amber-300/30',
            self::Navy => 'from-slate-800 via-blue-950 to-indigo-950 shadow-blue-950/30',
            self::Abu => 'from-zinc-600 via-zinc-700 to-zinc-800 shadow-zinc-800/30',
            self::Kopi => 'from-amber-900 via-stone-800 to-stone-900 shadow-stone-900/30',
            self::Maroon => 'from-rose-900 via-red-950 to-rose-950 shadow-rose-950/30',
            self::Hutan => 'from-emerald-900 via-green-950 to-teal-950 shadow-emerald-950/30',
            self::Batu => 'from-slate-500 via-slate-600 to-slate-700 shadow-slate-700/30',
            self::Indigo => 'from-indigo-600 via-indigo-600 to-violet-600 shadow-indigo-600/20',
            self::Ungu => 'from-violet-600 via-purple-600 to-fuchsia-600 shadow-purple-600/20',
            self::Pink => 'from-pink-500 via-rose-500 to-orange-400 shadow-rose-500/20',
            self::Oranye => 'from-amber-500 via-orange-500 to-red-500 shadow-orange-500/20',
            self::Hijau => 'from-emerald-500 via-teal-500 to-cyan-600 shadow-teal-500/20',
            self::Biru => 'from-sky-500 via-blue-600 to-indigo-600 shadow-blue-600/20',
            self::Neon => 'from-lime-300 via-emerald-300 to-cyan-300 shadow-emerald-400/30',
        };
    }

    /**
     * Kelas latar lembut untuk kotak emoji grup.
     */
    public function lembut(): string
    {
        return match ($this) {
            self::Stroberi, self::Pink => 'bg-pink-100 dark:bg-pink-400/15',
            self::Lavender, self::Ungu => 'bg-purple-100 dark:bg-purple-400/15',
            self::Mint, self::Hijau, self::Neon => 'bg-emerald-100 dark:bg-emerald-400/15',
            self::Peach, self::Oranye => 'bg-orange-100 dark:bg-orange-400/15',
            self::Langit, self::Biru => 'bg-sky-100 dark:bg-sky-400/15',
            self::Lemon => 'bg-yellow-100 dark:bg-yellow-400/15',
            self::Navy, self::Indigo => 'bg-indigo-100 dark:bg-indigo-400/15',
            self::Abu => 'bg-zinc-200 dark:bg-zinc-400/15',
            self::Kopi => 'bg-amber-100 dark:bg-amber-700/25',
            self::Maroon => 'bg-rose-100 dark:bg-rose-700/25',
            self::Hutan => 'bg-green-100 dark:bg-green-700/25',
            self::Batu => 'bg-slate-200 dark:bg-slate-400/15',
        };
    }

    /**
     * Warna teks utama di atas sampul.
     */
    public function teks(): string
    {
        return $this->terang() ? 'text-zinc-900' : 'text-white';
    }

    /**
     * Warna teks keterangan di atas sampul, tetap cukup kontras.
     */
    public function teksRedup(): string
    {
        return $this->terang() ? 'text-zinc-800' : 'text-white/90';
    }

    /**
     * Latar kaca untuk tombol dan label di atas sampul.
     */
    public function kaca(): string
    {
        return $this->terang()
            ? 'bg-white/60 ring-zinc-900/10 hover:bg-white/80'
            : 'bg-white/15 ring-white/25 hover:bg-white/25';
    }

    /**
     * Jalur dan isi bilah kemajuan di atas sampul.
     *
     * @return array{jalur: string, isi: string}
     */
    public function bilah(): array
    {
        return $this->terang()
            ? ['jalur' => 'bg-zinc-900/10', 'isi' => 'bg-zinc-900']
            : ['jalur' => 'bg-white/25', 'isi' => 'bg-white'];
    }

    /**
     * Emoji pilihan untuk grup, dikelompokkan sesuai jenis patungan.
     *
     * @return array<string, list<string>>
     */
    public static function kategoriEmoji(): array
    {
        return [
            __('Makan & nongkrong') => ['🍜', '🍕', '🍔', '🍢', '🍗', '🥘', '☕', '🧋', '🍰', '🍦'],
            __('Kos & rumah') => ['🏠', '🔑', '💡', '📶', '💧', '🧺', '🧹', '🛋️'],
            __('Langganan') => ['🎬', '🎵', '🎮', '📺', '☁️', '📱'],
            __('Jalan-jalan') => ['✈️', '🏖️', '⛰️', '⛺', '🚗', '🏍️', '🚆', '🗺️'],
            __('Acara') => ['🎉', '🎂', '🎓', '🎁', '💍', '🕌', '🎤', '🎟️'],
            __('Olahraga') => ['⚽', '🏸', '🏀', '🏃', '🏊', '🚴'],
            __('Lainnya') => ['🛒', '📚', '💼', '🐾', '💸', '🤝'],
        ];
    }

    /**
     * Semua emoji yang boleh dipilih.
     *
     * @return list<string>
     */
    public static function emoji(): array
    {
        return array_merge(...array_values(self::kategoriEmoji()));
    }
}
