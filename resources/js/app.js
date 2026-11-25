import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

/*
|--------------------------------------------------------------------------
| Panduan aplikasi (tur)
|--------------------------------------------------------------------------
|
| Tiap halaman yang punya <x-tur> menyimpan langkah panduannya sebagai JSON.
| Panduan jalan otomatis sekali per halaman di browser ini, dan bisa diulang
| lewat tombol Panduan yang memanggil window.mulaiTur().
|
*/

let turAktif = null;

const kunciTur = (nama) => `urunan:tur:${nama}`;

function sudahDilihat(nama) {
    try {
        return localStorage.getItem(kunciTur(nama)) !== null;
    } catch {
        return true;
    }
}

function tandaiDilihat(nama) {
    try {
        localStorage.setItem(kunciTur(nama), '1');
    } catch {
        // Penyimpanan browser tidak tersedia, panduan akan muncul lagi lain kali.
    }
}

function bacaTur() {
    const sumber = document.querySelector('script[data-tur-halaman]');

    if (!sumber) {
        return null;
    }

    try {
        return { nama: sumber.dataset.turHalaman, ...JSON.parse(sumber.textContent) };
    } catch {
        return null;
    }
}

/**
 * Elemen pertama yang benar-benar terlihat di layar, misalnya menu sidebar
 * yang tersembunyi di HP akan dilewati.
 */
function elemenTerlihat(selector) {
    const daftar = Array.isArray(selector) ? selector : [selector];

    for (const pilihan of daftar) {
        for (const elemen of document.querySelectorAll(pilihan)) {
            const kotak = elemen.getBoundingClientRect();

            if (kotak.width > 0 && kotak.height > 0 && kotak.right > 0 && kotak.left < window.innerWidth) {
                return elemen;
            }
        }
    }

    return null;
}

function jalankanTur(paksa = false) {
    const tur = bacaTur();

    if (!tur || (!paksa && sudahDilihat(tur.nama))) {
        return false;
    }

    const langkah = tur.langkah
        .map((isi) => ({ isi, elemen: isi.elemen ? elemenTerlihat(isi.elemen) : undefined }))
        .filter(({ isi, elemen }) => !isi.elemen || elemen)
        .map(({ isi, elemen }) => ({
            element: elemen ?? undefined,
            popover: {
                title: isi.judul,
                description: isi.isi,
                side: isi.sisi ?? 'bottom',
                align: isi.rata ?? 'start',
            },
        }));

    if (langkah.length === 0) {
        return false;
    }

    turAktif?.destroy();

    turAktif = driver({
        steps: langkah,
        showProgress: langkah.length > 1,
        progressText: tur.teks.progres.replace(':sekarang', '{{current}}').replace(':total', '{{total}}'),
        nextBtnText: tur.teks.lanjut,
        prevBtnText: tur.teks.kembali,
        doneBtnText: tur.teks.selesai,
        closeBtnLabel: tur.teks.tutup,
        popoverClass: 'tur-urunan',
        overlayOpacity: 0.55,
        stagePadding: 6,
        stageRadius: 12,
        smoothScroll: true,
        onDestroyed: () => {
            tandaiDilihat(tur.nama);
            turAktif = null;
        },
    });

    turAktif.drive();

    return true;
}

window.mulaiTur = (pesanKosong = '') => {
    if (!jalankanTur(true) && pesanKosong) {
        window.dispatchEvent(new CustomEvent('toast-show', {
            detail: { slots: { text: pesanKosong }, dataset: { variant: 'info' }, duration: 3000 },
        }));
    }
};

document.addEventListener('livewire:navigate', () => turAktif?.destroy());

document.addEventListener('livewire:navigated', () => {
    setTimeout(() => jalankanTur(false), 500);
});

/*
| Konfeti kecil saat pembayaran lunas. Dipicu dari Livewire dengan
| $this->dispatch('konfeti'). Tidak jalan kalau pengguna memilih kurangi animasi.
*/
window.addEventListener('konfeti', () => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const kanvas = document.createElement('canvas');
    kanvas.setAttribute('aria-hidden', 'true');
    kanvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:80';
    document.body.appendChild(kanvas);

    const konteks = kanvas.getContext('2d');
    const rasio = window.devicePixelRatio || 1;
    kanvas.width = window.innerWidth * rasio;
    kanvas.height = window.innerHeight * rasio;
    konteks.scale(rasio, rasio);

    const warna = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#0ea5e9'];
    const potongan = Array.from({ length: 120 }, () => ({
        x: window.innerWidth / 2 + (Math.random() - 0.5) * 120,
        y: window.innerHeight * 0.35,
        vx: (Math.random() - 0.5) * 14,
        vy: Math.random() * -14 - 4,
        ukuran: Math.random() * 6 + 4,
        putar: Math.random() * Math.PI,
        vputar: (Math.random() - 0.5) * 0.3,
        warna: warna[Math.floor(Math.random() * warna.length)],
    }));

    const mulai = performance.now();

    const gambar = (sekarang) => {
        const umur = sekarang - mulai;
        konteks.clearRect(0, 0, window.innerWidth, window.innerHeight);
        konteks.globalAlpha = Math.max(0, 1 - umur / 2200);

        for (const p of potongan) {
            p.vy += 0.35;
            p.vx *= 0.99;
            p.x += p.vx;
            p.y += p.vy;
            p.putar += p.vputar;

            konteks.save();
            konteks.translate(p.x, p.y);
            konteks.rotate(p.putar);
            konteks.fillStyle = p.warna;
            konteks.fillRect(-p.ukuran / 2, -p.ukuran / 4, p.ukuran, p.ukuran / 2);
            konteks.restore();
        }

        if (umur < 2200) {
            requestAnimationFrame(gambar);
        } else {
            kanvas.remove();
        }
    };

    requestAnimationFrame(gambar);
});
