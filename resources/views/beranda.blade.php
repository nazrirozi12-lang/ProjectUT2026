@extends('layouts.app')

@section('title', 'Beranda — Naro Capture')

@section('content')
    {{-- HERO --}}
    <section class="hero" style="background-image: linear-gradient(90deg, rgba(18,32,24,.8) 0%, rgba(18,32,24,0) 75%), url('{{ asset('images/hero.jpg') }}')">
        <div class="hero__message">
            <h1 class="hero__title">Cerita Anda,<br>di setiap momen.</h1>
            <p class="hero__lead">Abadikan kebersamaan dan momen berharga dengan foto yang alami, hangat, dan penuh makna.</p>
            <a href="#paket" class="btn btn--fill" style="width:240px">Paket Layanan @include('partials.arrow')</a>
        </div>
        <div class="hero__details">
            <span class="hero__location"><i class="dot"></i>BERBASIS DI JAKARTA · MELAYANI SELURUH INDONESIA</span>
            <span>Menangkap momen-momen sederhana dengan hati.</span>
        </div>
    </section>

    {{-- KARYA TERBARU --}}
    <section id="karya" class="section">
        <div class="section__intro">
            <div class="section__heading">
                <span class="eyebrow">BEBERAPA CERITA YANG TELAH KAMI ABADIKAN</span>
                <h2 class="section__title">Karya Terbaru</h2>
            </div>
        </div>

        <div class="masonry">
            @foreach ($columns as $column)
                <div class="masonry__col">
                    @foreach ($column as $work)
                        <figure class="work">
                            <div class="work__img"><img src="{{ asset('images/' . $work['image']) }}" alt="{{ $work['title'] }}" style="height:{{ $work['height'] }}px"></div>
                            <figcaption>
                                <span class="work__title">{{ $work['title'] }}</span>
                                <span class="work__cat">{{ $work['category'] }}</span>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="section__note">
            <span>Kebersamaan yang nyata, diabadikan sepenuh hati.</span>
            <span class="small-caps">KUMPULAN KARYA · 2025—2026</span>
        </div>
    </section>

    {{-- PAKET LAYANAN --}}
    <section id="paket" class="section section--tint" style="padding-bottom:48px">
        <div class="section__intro">
            <div class="section__heading">
                <span class="eyebrow">MARI BUAT KENANGAN YANG BERARTI</span>
                <h2 class="section__title">Paket Layanan</h2>
            </div>
            <p class="body-text" style="width:360px">Sesi yang sederhana, dipandu dengan lembut, dan memberi ruang bagi Anda untuk menjadi diri sendiri.</p>
        </div>

        <div class="packages">
            @foreach ($packages as $pkg)
                <article class="package {{ $pkg['featured'] ? 'package--featured' : '' }}">
                    <img class="package__img" src="{{ asset('images/' . $pkg['image']) }}" alt="{{ $pkg['name'] }}">
                    <div class="package__body">
                        <div class="package__intro">
                            @if (!empty($pkg['label']) || $pkg['featured'])
                                <div class="package__label">
                                    <span>{{ $pkg['label'] ?? '' }}</span>
                                    @if ($pkg['featured'])
                                        <span class="badge">PILIHAN TERPOPULER</span>
                                    @endif
                                </div>
                            @endif
                            <h3 class="package__name">{{ $pkg['name'] }}</h3>
                            @if (!empty($pkg['description']))
                                <p class="body-text package__desc">{{ $pkg['description'] }}</p>
                            @endif
                        </div>
                        <div class="package__price">
                            <span>{{ $pkg['price'] }}</span><small>/ sesi</small>
                        </div>
                        <hr class="divider">
                        <ul class="package__list">
                            @foreach ($pkg['items'] as $item)
                                <li><img src="{{ asset('images/check.svg') }}" alt="">{{ $item }}</li>
                            @endforeach
                        </ul>
                        @if (!empty($pkg['note']))
                            <p class="package__note">{{ $pkg['note'] }}</p>
                        @endif
                        <a href="{{ route('pemesanan', ['package' => $pkg['id'] ?? Str::slug($pkg['name'])]) }}"
                           class="btn {{ $pkg['featured'] ? 'btn--fill' : 'btn--outline' }} btn--block">
                            Pesan {{ $pkg['name'] }} @include('partials.arrow')
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="section__note" style="align-items:center">
            <span style="line-height:1.6">Setiap sesi dilengkapi dengan sentuhan yang lebih personal. Galeri biasanya dikirim dalam waktu dua minggu.</span>
            <a href="mailto:narocapture@gmail.com" class="link-blue">Butuh paket khusus?&nbsp; narocapture@gmail.com ↗</a>
        </div>
    </section>
@endsection

