@extends('layouts.admin')

@section('title', 'Dashboard — Admin Backoffice Naro Capture')
@section('breadcrumb', 'Dashboard / ' . $greeting . ', ' . $name)

@section('content')
{{-- PENGANTAR HALAMAN (FIGMA 141:885) --}}
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">{{ $greeting }}, {{ $name }}</h1>
        <p class="admin-page-subtitle">{{ $dateLine }} · Semua kebutuhan studio, dalam satu ruang.</p>
    </div>
    <a href="{{ route('admin.pesanan') }}" class="btn btn--fill btn-sm" style="background:#3B5B9A; color:#FFF; height:40px; padding:0 22px;">
        Tambah pesanan
    </a>
</div>

{{-- BANNER PEMBERITAHUAN (data nyata) --}}
@if ($pendingPayments > 0)
    <div class="admin-alert-box">
        <strong class="admin-alert-title">{{ $pendingPayments }} bukti pembayaran perlu ditinjau</strong>
        <span class="admin-alert-desc">
            Periksa bukti, lalu konfirmasi jadwal secara terpisah.
            <a href="{{ route('admin.pembayaran', ['tab' => 'bukti-terkirim']) }}" style="color:#3B5B9A; text-decoration:underline; font-weight:500;">Lihat antrean pembayaran →</a>
        </span>
    </div>
@else
    <div class="admin-alert-box alert-success-box">
        <strong class="admin-alert-title text-green">Tidak ada bukti pembayaran yang menunggu</strong>
        <span class="admin-alert-desc text-green">Semua bukti yang masuk sudah ditinjau.</span>
    </div>
@endif

{{-- METRIK RINGKASAN (FIGMA 141:899) --}}
<div class="admin-metrics-grid">
    {{-- Metrik 1: Pesanan Oktober --}}
    <div class="admin-metric-card">
        <div class="admin-metric-header">
            <span class="admin-metric-label">{{ $metrics['orders']['label'] }}</span>
            <img src="{{ asset('images/icon-clipboard-list.svg') }}" alt="" width="20" height="20">
        </div>
        <strong class="admin-metric-value">{{ $metrics['orders']['value'] }}</strong>
        <span class="admin-metric-footnote">{{ $metrics['orders']['note'] }}</span>
    </div>

    {{-- Metrik 2: Nilai Pesanan --}}
    <div class="admin-metric-card">
        <div class="admin-metric-header">
            <span class="admin-metric-label">Nilai pesanan</span>
            <img src="{{ asset('images/icon-wallet.svg') }}" alt="" width="20" height="20">
        </div>
        <strong class="admin-metric-value">{{ $metrics['value']['value'] }}</strong>
        <span class="admin-metric-footnote">{{ $metrics['value']['note'] }}</span>
    </div>

    {{-- Metrik 3: Sesi Mendatang --}}
    <div class="admin-metric-card">
        <div class="admin-metric-header">
            <span class="admin-metric-label">Sesi mendatang</span>
            <img src="{{ asset('images/icon-calendar.svg') }}" alt="" width="20" height="20">
        </div>
        <strong class="admin-metric-value">{{ $metrics['upcoming']['value'] }}</strong>
        <span class="admin-metric-footnote">{{ $metrics['upcoming']['note'] }}</span>
    </div>

    {{-- Metrik 4: Galeri dalam proses --}}
    <div class="admin-metric-card">
        <div class="admin-metric-header">
            <span class="admin-metric-label">Galeri dalam proses</span>
            <img src="{{ asset('images/icon-images.svg') }}" alt="" width="20" height="20">
        </div>
        <strong class="admin-metric-value">{{ $metrics['gallery']['value'] }}</strong>
        <span class="admin-metric-footnote">{{ $metrics['gallery']['note'] }}</span>
    </div>
</div>

{{-- ROW 2: TREN SESI & PRIORITAS HARI INI (FIGMA 141:924) --}}
<div class="admin-grid-2">
    {{-- Panel Tren Permintaan Sesi --}}
    <div class="admin-panel">
        <h3 class="admin-panel-title">Tren permintaan sesi</h3>
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <span class="admin-metric-label">{{ $trendRange }} · Jumlah pesanan masuk per bulan</span>
            <span class="badge-status-session status-bukti-terkirim">● Bulan berjalan</span>
        </div>

        {{-- Bar Chart (data nyata) --}}
        <div class="admin-bar-chart">
            @foreach ($trend as $t)
                <div class="admin-bar-item">
                    <span class="admin-bar-val" @if($t['current']) style="font-weight:600;" @endif>{{ $t['count'] }}</span>
                    <div class="admin-bar-pillar {{ $t['current'] ? 'is-highlight' : '' }}" style="height: {{ $t['height'] }}px;"></div>
                    <span class="admin-bar-label" @if($t['current']) style="font-weight:600; color:#253247;" @endif>{{ $t['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Panel Prioritas Hari Ini (data nyata) --}}
    <div class="admin-panel">
        <h3 class="admin-panel-title">Prioritas hari ini</h3>
        @forelse ($priorities as $pr)
            <div style="display:flex; flex-direction:column; gap:6px; {{ !$loop->first ? 'margin-top:14px; padding-top:14px; border-top:1px solid #E6E8E1;' : '' }}">
                <strong style="font-size:15px; color:#253247;">{{ $pr['title'] }}</strong>
                <span class="admin-metric-label">{{ $pr['meta'] }}</span>
                <span class="{{ $pr['pill_class'] }}">● {{ $pr['pill'] }}</span>
                <p class="body-text font-12" style="color:#253247;">{{ $pr['text'] }}</p>
                <div>
                    <a href="{{ $pr['url'] }}" class="btn btn--outline btn-sm" style="height:40px; border-color:#DDDFD8;">{{ $pr['cta'] }}</a>
                </div>
            </div>
        @empty
            <p class="body-text font-12" style="color:#6E7A8B;">Tidak ada tindakan mendesak. Semua pembayaran sudah ditinjau dan tidak ada sesi yang menunggu konfirmasi.</p>
        @endforelse
    </div>
</div>

{{-- ROW 3: SESI TERDEKAT & AKTIVITAS TERBARU (FIGMA 141:957) --}}
<div class="admin-grid-2">
    {{-- Panel Sesi Terdekat --}}
    <div class="admin-panel">
        <h3 class="admin-panel-title">Sesi terdekat</h3>
        
        <div class="admin-table-simple">
            <div class="admin-table-head">
                <span>Pelanggan / paket</span>
                <span>Jadwal</span>
                <span>Status</span>
            </div>

            @forelse ($nearest as $n)
                <a href="{{ $n['url'] }}" class="admin-table-row" style="text-decoration:none; color:inherit;">
                    <strong style="font-weight:500;">{{ $n['who'] }}</strong>
                    <span>{{ $n['when'] }}</span>
                    <span class="{{ $n['class'] }}">● {{ $n['status'] }}</span>
                </a>
            @empty
                <div class="admin-table-row"><span style="color:#6E7A8B;">Belum ada sesi terjadwal.</span></div>
            @endforelse
        </div>
        <a href="{{ route('admin.jadwal') }}" class="link-blue font-12" style="margin-top:auto;">
            Lihat kalender dan permintaan →
        </a>
    </div>

    {{-- Panel Aktivitas Terbaru --}}
    <div class="admin-panel">
        <h3 class="admin-panel-title">Aktivitas terbaru</h3>
        
        <div class="admin-activity-list">
            @forelse ($activity as $a)
                <div class="admin-activity-item">
                    <span class="admin-activity-time">● {{ $a['time'] }}</span>
                    <div class="admin-activity-content">
                        <strong class="admin-activity-title">{{ $a['title'] }}</strong>
                        <span class="admin-activity-meta">{{ $a['meta'] }}</span>
                    </div>
                </div>
            @empty
                <span class="admin-activity-meta">Belum ada aktivitas.</span>
            @endforelse
        </div>
        <a href="{{ route('admin.pesanan') }}" class="link-blue font-12" style="margin-top:auto;">
            Lihat seluruh aktivitas →
        </a>
    </div>
</div>

<p class="admin-footer-copyright">
    © {{ date('Y') }} Naro Capture · Akses terbatas admin
</p>
@endsection

