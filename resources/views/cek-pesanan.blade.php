@extends('layouts.app')

@section('title', 'Cek Status Pesanan — Naro Capture')

@section('content')
<main class="tracking-page">
    {{-- BAGIAN PENCARIAN PESANAN (FIGMA 95:1501) --}}
    <section class="tracking-search-section">
        <div class="booking-breadcrumb">
            <a href="{{ url('/') }}">Beranda</a>
            <span class="sep">/</span>
            <span>Cek Pesanan</span>
        </div>

        <div class="checkout-header">
            <div class="checkout-header__left">
                <span class="eyebrow">KABAR TERBARU TENTANG SESI ANDA</span>
                <h1 class="checkout-header__title">Cek status pesanan.</h1>
            </div>
            <p class="body-text checkout-header__desc">
                Pantau perjalanan pesanan Anda dengan kode dan kontak saat pemesanan. Tanpa perlu membuat akun atau masuk.
            </p>
        </div>

        {{-- FORMULIR PENCARIAN --}}
        <div class="tracking-search-box">
            <div class="checkout-card-header">
                <h2 class="card-title">Temukan pesanan Anda</h2>
                <span class="form-required-note">* Wajib diisi</span>
            </div>

            <form action="{{ route('cek-pesanan') }}" method="GET" class="tracking-search-form">
                <div class="tracking-search-row">
                    <div class="tracking-search-col">
                        <label for="searchKode" class="form-label">Kode Pesanan *</label>
                        <input type="text" id="searchKode" name="kode" class="form-control" placeholder="Masukkan kode dari email atau WhatsApp" value="{{ $searchCode ?: ($data['order_code'] ?? '') }}" required>
                    </div>

                    <div class="tracking-search-col">
                        <label for="searchKontak" class="form-label">Email atau nomor WhatsApp saat pemesanan *</label>
                        <input type="text" id="searchKontak" name="kontak" class="form-control" placeholder="Gunakan salah satu kontak asli Anda" value="{{ $searchContact ?: ($data['email'] ?? '') }}" required>
                    </div>

                    <button type="submit" class="btn btn--fill btn-search-tracking" style="height: 48px; padding: 0 24px;">
                        Cek status pesanan @include('partials.arrow')
                    </button>
                </div>
            </form>

            <div class="tracking-search-privacy">
                <img src="{{ asset('images/icon-shield-check.svg') }}" alt="" width="16" height="16">
                <span>Temukan kode pesanan di checkout, invoice, atau konfirmasi pembayaran, serta email/WhatsApp pemesanan. Gunakan kontak yang sama saat memesan.</span>
            </div>
        </div>
    </section>

    @if(!empty($notFound))
        <section class="tracking-results-section">
            <div class="tracking-results-header">
                <h2 class="card-title font-25">Pesanan tidak ditemukan</h2>
            </div>
            <p class="body-text">Periksa kembali kode pesanan dan gunakan email atau nomor WhatsApp yang sama seperti saat pemesanan.</p>
        </section>
    @endif

    @if($hasSearched)
        {{-- HASIL PENCARIAN PESANAN (FIGMA 95:1533) --}}
        <section class="tracking-results-section">
            <div class="tracking-results-header">
                <div class="tracking-title-wrap">
                    <h2 class="card-title font-25">Pesanan ditemukan</h2>
                    <span class="badge-tag" style="background:#E8F0FF; color:#3B5B9A; padding:6px 12px; font-size:11px;">
                        {{ $statusKey }}
                    </span>
                </div>
                <span class="body-text font-13 muted">Kode pesanan: {{ $data['order_code'] }}</span>
            </div>

            <div class="checkout-split-grid">
                {{-- KOLOM KIRI: STATUS PESANAN & LINIMASA (FIGMA 95:1541) --}}
                <div class="checkout-box status-tracking-main-box">
                    <div class="status-current-box">
                        <div class="status-eyebrow-row">
                            <img src="{{ asset('images/' . ($track['icon'] === 'check' ? 'check.svg' : 'icon-clock.svg')) }}" alt="" width="18" height="18">
                            <span class="eyebrow" @if($track['tone'] === 'warn') style="color:#B7791F;" @endif>{{ $track['eyebrow'] }}</span>
                        </div>
                        <h2 class="status-main-heading">{{ $track['heading'] }}</h2>
                        <p class="body-text">{{ $track['text'] }}</p>
                    </div>

                    <div class="notice-readonly-proof" @if($track['tone'] === 'warn') style="border-left:4px solid #B7791F;" @endif>
                        <img src="{{ asset('images/icon-info.svg') }}" alt="" class="notice-icon" width="18" height="18">
                        <div>
                            <h4 class="notice-readonly-title">{{ $track['notice'][0] }}</h4>
                            <p class="notice-readonly-desc">{{ $track['notice'][1] }}</p>
                        </div>
                    </div>

                    @if (!empty($track['cta']))
                        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:16px;">
                            @foreach ($track['cta'] as $c)
                                <a href="{{ $c['url'] }}" class="btn {{ !empty($c['primary']) ? 'btn--fill' : 'btn--outline' }}">{{ $c['label'] }} @include('partials.arrow')</a>
                            @endforeach
                        </div>
                    @endif

                    <hr class="divider">

                    {{-- LINIMASA PERJALANAN PESANAN (data nyata) --}}
                    <div class="tracking-timeline-wrap">
                        <h3 class="subheader-title font-20" style="margin-bottom: 20px;">Perjalanan pesanan</h3>
                        <div class="timeline-vertical">
                            @foreach ($track['timeline'] as $step)
                                <div class="timeline-step">
                                    <div class="timeline-visual-col">
                                        <span class="timeline-node is-{{ in_array($step['state'], ['warn', 'cancelled']) ? 'current' : $step['state'] }}" @if($step['state'] === 'warn') style="border-color:#B7791F; color:#B7791F;" @elseif($step['state'] === 'cancelled') style="border-color:#D32F2F; color:#D32F2F;" @endif>
                                            @if ($step['state'] === 'done') ✓ @elseif ($step['state'] === 'warn') ! @elseif ($step['state'] === 'cancelled') × @endif
                                        </span>
                                        @if (!$loop->last)
                                            <span class="timeline-line {{ $step['state'] === 'done' ? 'is-done' : 'is-upcoming' }}"></span>
                                        @endif
                                    </div>
                                    <div class="timeline-text-col">
                                        <strong class="timeline-step-title">{{ $step['title'] }}</strong>
                                        @if ($step['state'] === 'current')<span class="badge-current">SAAT INI</span>@elseif ($step['state'] === 'warn')<span class="badge-current" style="background:#FFF4DD; color:#B7791F;">PERLU TINDAKAN</span>@endif
                                        <p class="timeline-step-desc">{{ $step['desc'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if($track['show_prep'])
                        <hr class="divider">

                        {{-- PERSIAPAN SESI --}}
                        <div style="display: flex; flex-direction: column; gap: 16px;">
                            <h3 class="subheader-title font-20">Persiapan sesi Anda</h3>
                            <div class="detail-row">
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <span class="detail-label">Panggilan perencanaan</span>
                                    <p class="body-text font-13" style="color:var(--ink);">Tim studio akan menghubungi Anda untuk menyepakati lokasi dan kebutuhan sesi.</p>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <span class="detail-label">Kostum &amp; kenyamanan</span>
                                    <p class="body-text font-13" style="color:var(--ink);">Siapkan pilihan busana yang nyaman dan selaras, serta keperluan pribadi yang dibutuhkan selama sesi.</p>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <span class="detail-label">Sebelum sesi</span>
                                    <p class="body-text font-13" style="color:var(--ink);">Tiba atau bersiap sebelum pukul {{ explode(' ', (string) $data['session_time'])[0] }} WIB agar sesi berjalan santai.</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <hr class="divider">

                    {{-- DOKUMEN PESANAN --}}
                    <div class="tracking-doc-section">
                        <h4 class="subheader-title font-13 font-medium">Dokumen pesanan</h4>
                        <div class="tracking-doc-actions">
                            <a href="{{ route('checkout') }}" class="btn btn--outline btn-sm">
                                {{ $track['icon'] === 'check' && $statusKey === 'Dikonfirmasi' ? 'Lihat invoice lunas' : 'Lihat invoice' }} @include('partials.arrow')
                            </a>
                        </div>
                    </div>
                </div>

                {{-- KOLOM KANAN: RINCIAN SESI DAN INVOICE (FIGMA 95:1620) --}}
                <div class="checkout-split-right">
                    <div class="checkout-box">
                        <h3 class="subheader-title font-25" style="margin-bottom: 20px;">Sesi yang Anda rencanakan</h3>

                        {{-- Paket Terpilih --}}
                        <div class="mini-package-row">
                            <img src="{{ \App\Support\Media::pkg($data['package_image'] ?? 'paket-2.jpg') }}" alt="{{ $data['package_name'] }}" class="mini-package-img">
                            <div class="mini-package-info">
                                <span class="eyebrow">PAKET TERPILIH</span>
                                <h4 class="mini-package-name">{{ $data['package_name'] }}</h4>
                                <span class="mini-package-meta">1 sesi · {{ $data['package_duration'] ?? 90 }} menit</span>
                            </div>
                        </div>

                        {{-- Layanan Termasuk --}}
                        <div style="display: flex; flex-direction: column; gap: 8px; margin: 16px 0;">
                            @foreach($data['package_items'] ?? [] as $item)
                                <div style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--ink);">
                                    <img src="{{ asset('images/check.svg') }}" alt="" width="14" height="14">
                                    <span>{{ $item }}</span>
                                </div>
                            @endforeach
                        </div>

                        <hr class="divider">

                        {{-- Detail Pesanan --}}
                        <div style="display: flex; flex-direction: column; gap: 14px; margin: 16px 0;">
                            <div class="detail-row">
                                <img src="{{ asset('images/icon-calendar.svg') }}" alt="" width="16" height="16" style="margin-top:2px;">
                                <div>
                                    <span class="detail-label">Tanggal yang diminta</span>
                                    <strong class="detail-value">{{ $data['session_date'] }}</strong>
                                </div>
                            </div>

                            <div class="detail-row">
                                <img src="{{ asset('images/icon-clock.svg') }}" alt="" width="16" height="16" style="margin-top:2px;">
                                <div>
                                    <span class="detail-label">Waktu sesi</span>
                                    <strong class="detail-value">{{ $data['session_time'] }}</strong>
                                </div>
                            </div>

                            <div class="detail-row">
                                <img src="{{ asset('images/icon-map-pin.svg') }}" alt="" width="16" height="16" style="margin-top:2px;">
                                <div>
                                    <span class="detail-label">Lokasi sesi</span>
                                    <strong class="detail-value">{{ $data['lokasi'] }}</strong>
                                </div>
                            </div>
                            <span class="form-helper-text" style="font-size: 12px;">{{ $data['lokasi_hint'] }}</span>
                        </div>

                        <hr class="divider">

                        {{-- Pemesan & Kontak Tersamarkan --}}
                        <div style="display: flex; flex-direction: column; gap: 12px; margin: 16px 0;">
                            <div class="detail-row">
                                <div>
                                    <span class="detail-label">Pemesan</span>
                                    <strong class="detail-value">{{ $data['nama_lengkap'] }}</strong>
                                </div>
                            </div>

                            <div class="masked-contact-row">
                                <div>
                                    <span class="detail-label">Email</span>
                                    <span class="detail-value">{{ $maskedEmail }}</span>
                                </div>
                                <div>
                                    <span class="detail-label">WhatsApp</span>
                                    <span class="detail-value">{{ $maskedPhone }}</span>
                                </div>
                            </div>
                            <span class="form-helper-text" style="font-size: 11px;">Kontak disamarkan untuk menjaga privasi Anda.</span>
                        </div>

                        <hr class="divider">

                        {{-- Nomor Invoice & Total --}}
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <div>
                                <span class="detail-label">Nomor invoice</span>
                                <strong class="detail-value">{{ $data['invoice_number'] ?? 'INV-CH-2026-001' }}</strong>
                            </div>

                            <div class="total-invoice-card" style="background:#F5F8FF; padding:20px; border-radius:6px; display:flex; flex-direction:column; gap:8px;">
                                <span class="font-13" style="color:#3B5B9A;">Total pada invoice</span>
                                <span class="font-36 font-semibold" style="color:var(--ink);">{{ $data['package_price'] }}</span>
                                <span class="font-11 muted">Dalam rupiah (IDR) · sesuai harga paket</span>
                                @if(in_array($statusKey, ['Dikonfirmasi', 'Selesai', 'Diarsipkan']) || $payStatus === 'Terverifikasi')
                                    <span class="font-12 font-medium" style="color:#3B5B9A; margin-top:4px;">Pembayaran terverifikasi{{ $statusKey === 'Dikonfirmasi' ? ' · Jadwal sesi telah disetujui studio' : '' }}</span>
                                @else
                                    <span class="font-12 font-medium" style="color:#3B5B9A; margin-top:4px;">Pembayaran belum terverifikasi · bukan tanda lunas</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- BANTUAN PESANAN & PANDUAN PENELUSURAN (FIGMA 95:1684) --}}
        <section class="tracking-faq-section">
            {{-- Banner Hubungi Studio --}}
            <div class="tracking-contact-banner">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <img src="{{ asset('images/icon-email.svg') }}" alt="" width="24" height="24" style="flex-shrink:0;">
                    <div>
                        <h3 class="subheader-title font-20">Ada yang ingin Anda tanyakan?</h3>
                        <p class="body-text" style="font-size: 13px;">Hubungi narocapture@gmail.com dan sertakan kode pesanan Anda. Jangan kirim PIN, kata sandi, atau kode OTP.</p>
                    </div>
                </div>
                <a href="mailto:narocapture@gmail.com" class="btn btn--outline" style="white-space: nowrap;">
                    Hubungi studio @include('partials.arrow')
                </a>
            </div>

            {{-- 3 Kolom FAQ --}}
            <div class="tracking-faq-grid">
                <div class="tracking-faq-card">
                    <h4>Lupa kode pesanan?</h4>
                    <p>Kode tersedia di checkout, invoice, dan konfirmasi pembayaran. Periksa juga email (termasuk spam) atau WhatsApp dari Naro Capture. Kode pesanan berbeda dari nomor invoice.</p>
                    <a href="mailto:narocapture@gmail.com" class="link-blue font-12">Belum menemukannya? Hubungi studio dari kontak yang digunakan saat pemesanan.</a>
                </div>

                <div class="tracking-faq-card">
                    <h4>Jika status Anda “Menunggu pembayaran”</h4>
                    <p>Ikuti instruksi pembayaran resmi pada invoice atau dari studio. Setelah transfer, kirim satu bukti melalui halaman konfirmasi pembayaran.</p>
                </div>

                <div class="tracking-faq-card">
                    <h4>Jika status Anda “Perlu perbaikan bukti”</h4>
                    <p>Baca catatan dari studio, lalu kirim bukti yang lebih jelas atau data yang diperbaiki. Status ini tidak berlaku untuk contoh pesanan di atas.</p>
                </div>
            </div>
        </section>
    @endif
</main>
@endsection

