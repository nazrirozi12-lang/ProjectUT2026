@extends('layouts.app')

@section('title', $gallery->title . ' — Galeri Naro Capture')

@section('content')
<main class="gallery-page">
    <section class="checkout-hero">
        <div class="booking-breadcrumb">
            <a href="{{ url('/') }}">Beranda</a>
            <span class="sep">/</span>
            <a href="{{ route('cek-pesanan') }}">Pesanan</a>
            <span class="sep">/</span>
            <span>{{ $gallery->title }}</span>
        </div>

        <div class="checkout-header">
            <div class="checkout-header__left">
                <span class="eyebrow">HASIL SESI</span>
                <h1 class="checkout-header__title">{{ $gallery->title }}</h1>
            </div>
            <p class="body-text checkout-header__desc">
                Galeri {{ $gallery->access_type === 'Privat' ? 'pribadi ' : '' }}{{ $gallery->client_name }}. Lihat kembali foto sesi Anda dan unduh foto pilihan Anda.
            </p>
        </div>

        <div class="tracking-results-header">
            <span class="font-20 font-normal" style="color:var(--blue);">{{ $order ? $order->order_code : $gallery->title }}</span>
            <span class="badge-tag" style="background:#E8F0FF; color:#3B5B9A; padding:6px 12px; font-size:11px;">
                {{ $gallery->access_type === 'Privat' ? 'Galeri pribadi' : 'Galeri terbuka' }}
            </span>
        </div>
        <hr class="divider">
    </section>

    <section class="gallery-content-section">
        @if ($photos->isNotEmpty())
            <div class="gallery-header-cover-grid">
                <div class="gallery-cover-img-wrap">
                    <img src="{{ route('galeri-klien.foto', ['slug' => $gallery->slug, 'photo' => $photos->first()->id, 'variant' => 'full']) }}" alt="Sampul {{ $gallery->title }}" class="gallery-cover-img">
                </div>
                <div class="gallery-meta-card">
                    <div>
                        <span class="eyebrow">DETAIL GALERI</span>
                        <h2 class="card-title font-25">{{ $order ? $order->package_name : $gallery->title }}</h2>
                    </div>
                    <div class="gallery-meta-rows">
                        <div class="detail-row"><div>
                            <span class="detail-label">Pemilik galeri</span>
                            <strong class="detail-value">{{ $gallery->client_name }}</strong>
                        </div></div>
                        @if ($sessionDate)
                            <div class="detail-row"><div>
                                <span class="detail-label">Tanggal sesi</span>
                                <strong class="detail-value">{{ $sessionDate }}</strong>
                            </div></div>
                        @endif
                        <div class="detail-row"><div>
                            <span class="detail-label">Jumlah foto</span>
                            <strong class="detail-value">{{ $photos->count() }} foto</strong>
                        </div></div>
                    </div>
                </div>
            </div>
        @endif

        <div class="tracking-results-header" style="align-items:flex-end;">
            <div>
                <h3 class="card-title font-25">Galeri foto Anda</h3>
                <p class="body-text font-13" style="margin-top:4px;">
                    {{ $photos->count() ? $photos->count() . ' foto tersedia. Klik foto untuk memperbesar.' : 'Foto belum diunggah ke galeri ini.' }}
                </p>
            </div>
            @if ($gallery->drive_url)
                <div style="display:flex; gap:12px;">
                    <a href="{{ $gallery->drive_url }}" target="_blank" rel="noopener noreferrer" class="btn btn--fill btn-sm" style="background:#3B5B9A; color:#FFF;">Buka semua foto di Google Drive ↗</a>
                </div>
            @endif
        </div>

        @if ($photos->isEmpty())
            <div class="notice-readonly-proof">
                <img src="{{ asset('images/icon-info.svg') }}" alt="" class="notice-icon" width="20" height="20">
                <div>
                    <h4 class="notice-readonly-title">Foto sedang disiapkan</h4>
                    <p class="notice-readonly-desc">
                        {{ $gallery->drive_url ? 'Foto Anda tersedia lewat tautan Google Drive di atas.' : 'Studio belum mengunggah foto ke galeri ini. Anda akan dikabari setelah siap.' }}
                    </p>
                </div>
            </div>
        @else
            <div class="gallery-grid-3">
                @foreach ($photos as $i => $photo)
                    <div class="gallery-photo-card">
                        <a href="{{ route('galeri-klien.foto', ['slug' => $gallery->slug, 'photo' => $photo->id, 'variant' => 'full']) }}" target="_blank" rel="noopener" class="gallery-photo-img-wrap" style="display:block;">
                            <img src="{{ route('galeri-klien.foto', ['slug' => $gallery->slug, 'photo' => $photo->id]) }}" alt="{{ $gallery->title }} — foto {{ $i + 1 }}" class="gallery-photo-img" loading="lazy">
                        </a>
                        <div class="gallery-photo-info">
                            <strong class="gallery-photo-title">Foto {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</strong>
                            <span class="gallery-photo-actions">
                                <a href="{{ route('galeri-klien.foto', ['slug' => $gallery->slug, 'photo' => $photo->id, 'variant' => 'full']) }}" target="_blank" rel="noopener" class="link-blue">Lihat</a>
                                &nbsp;·&nbsp;
                                <a href="{{ route('galeri-klien.foto', ['slug' => $gallery->slug, 'photo' => $photo->id, 'variant' => 'full', 'unduh' => 1]) }}" class="link-blue">Unduh ↓</a>
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="gallery-guide-card">
            <div class="gallery-guide-col">
                <h4 class="font-20 font-normal">Simpan untuk hari-hari mendatang.</h4>
                <p class="body-text font-13">Unduh foto satu per satu, atau gunakan tautan Google Drive (bila tersedia) untuk koleksi lengkap resolusi penuh.</p>
            </div>
            <div class="gallery-guide-col">
                <h4 class="font-20 font-normal">Jaga akses galeri Anda.</h4>
                <p class="body-text font-13">Bagikan tautan{{ $gallery->access_type === 'Privat' ? ' dan PIN' : '' }} hanya kepada orang yang Anda percayai. Hubungi studio bila Anda membutuhkan bantuan.</p>
            </div>
        </div>

        <div class="tracking-contact-banner">
            <div style="display: flex; align-items: center; gap: 20px;">
                <img src="{{ asset('images/icon-email.svg') }}" alt="" width="24" height="24" style="flex-shrink:0;">
                <div>
                    <h3 class="subheader-title font-20">Ada yang ingin Anda tanyakan?</h3>
                    <p class="body-text font-13">Hubungi narocapture@gmail.com dan sertakan kode pesanan Anda. Jangan kirim PIN, kata sandi, atau kode OTP.</p>
                </div>
            </div>
            <a href="mailto:narocapture@gmail.com" class="btn btn--outline" style="white-space: nowrap;">Hubungi Studio</a>
        </div>
    </section>
</main>
@endsection
