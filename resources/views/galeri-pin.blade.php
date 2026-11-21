@extends('layouts.app')
@section('title', 'Buka Galeri — Naro Capture')
@section('content')
<main class="gallery-page">
    <section class="checkout-hero">
        <div class="booking-breadcrumb">
            <a href="{{ url('/') }}">Beranda</a>
            <span class="sep">/</span>
            <a href="{{ route('cek-pesanan') }}">Pesanan</a>
            <span class="sep">/</span>
            <span>Galeri pribadi</span>
        </div>
        <div class="checkout-header">
            <div class="checkout-header__left">
                <span class="eyebrow">GALERI PRIBADI</span>
                <h1 class="checkout-header__title">Masukkan PIN galeri</h1>
            </div>
            <p class="body-text checkout-header__desc">
                Galeri “{{ $gallery->title }}” bersifat pribadi. Masukkan PIN yang diberikan studio untuk membukanya.
            </p>
        </div>
        <hr class="divider">
    </section>

    <section class="gallery-content-section" style="max-width: 520px;">
        <div class="checkout-box">
            @if ($locked)
                <div class="summary-notice" style="margin-bottom:16px;">
                    <img src="{{ asset('images/icon-info.svg') }}" alt="" class="notice-icon">
                    <p><strong>Terlalu banyak percobaan.</strong> Demi keamanan, akses galeri ini dikunci sementara. Coba lagi beberapa menit lagi atau hubungi studio.</p>
                </div>
            @endif
            <form action="{{ route('galeri-klien.pin') }}" method="POST" novalidate>
                @csrf
                <input type="hidden" name="slug" value="{{ $gallery->slug }}">
                <div class="form-group">
                    <label class="form-label" for="pin">PIN galeri *</label>
                    <input type="password" id="pin" name="pin" class="form-control" inputmode="numeric" autocomplete="off" maxlength="20" required autofocus {{ $locked ? 'disabled' : '' }}>
                    @error('pin')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn btn--fill btn--block btn--lg" {{ $locked ? 'disabled' : '' }}>Buka galeri @include('partials.arrow')</button>
            </form>
            <p class="body-text font-12" style="margin-top:16px;">Belum menerima PIN? Cek pesan dari studio atau hubungi <a href="mailto:narocapture@gmail.com" class="link-blue">narocapture@gmail.com</a> dengan menyertakan kode pesanan. Jangan bagikan PIN kepada orang yang tidak Anda percayai.</p>
        </div>
    </section>
</main>
@endsection
