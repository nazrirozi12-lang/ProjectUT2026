@extends('layouts.app')
@section('title', 'Galeri Tidak Ditemukan — Naro Capture')
@section('content')
<main class="gallery-page">
    <section class="checkout-hero">
        <div class="checkout-header">
            <div class="checkout-header__left">
                <span class="eyebrow">GALERI</span>
                <h1 class="checkout-header__title">Galeri tidak ditemukan</h1>
            </div>
            <p class="body-text checkout-header__desc">Tautan galeri tidak valid, atau galeri belum diterbitkan oleh studio. Periksa kembali tautan Anda atau cek status pesanan.</p>
        </div>
        <div style="display:flex; gap:12px; margin-top:24px;">
            <a href="{{ route('cek-pesanan') }}" class="btn btn--fill">Cek status pesanan @include('partials.arrow')</a>
            <a href="{{ url('/') }}" class="btn btn--outline">Kembali ke beranda</a>
        </div>
    </section>
</main>
@endsection
