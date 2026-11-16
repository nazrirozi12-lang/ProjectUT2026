@extends('layouts.admin')

@section('title', 'Pesanan — Naro Capture')
@section('breadcrumb', 'Pesanan / Pesanan')

@section('content')
    {{-- PENGANTAR HALAMAN (FIGMA 141:1035) --}}
    <div class="admin-page-header">
        <div class="admin-header-text">
            <h1 class="admin-page-title">Pesanan</h1>
            <p class="admin-page-subtitle">Kelola permintaan, persiapan sesi, dan riwayat pelanggan.</p>
        </div>
        <a href="{{ route('pemesanan') }}" target="_blank" class="admin-btn-primary">
            Tambah pesanan ↗
        </a>
    </div>

    {{-- RINGKASAN STATUS (data nyata) --}}
    <div class="admin-status-summary-bar">
        <span class="status-indicator-item text-blue">● {{ $summary['month'] }} pesanan dibuat bulan ini</span>
        <span class="status-indicator-item text-gold">● {{ $summary['review'] }} bukti pembayaran perlu ditinjau</span>
        <span class="status-indicator-item text-green">● {{ $summary['approved'] }} sesi dikonfirmasi</span>
    </div>

    {{-- PANEL UTAMA TABEL (FIGMA 141:1050) --}}
    <div class="admin-panel admin-orders-panel">
        {{-- PILIHAN TAB STATUS (hitungan nyata) --}}
        @php
            $tabDefs = ['semua' => 'Semua pesanan', 'permintaan' => 'Permintaan', 'dikonfirmasi' => 'Dikonfirmasi', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan', 'arsip' => 'Arsip'];
        @endphp
        <div class="admin-tabs">
            @foreach ($tabDefs as $key => $label)
                <a href="{{ route('admin.pesanan', array_filter(['tab' => $key, 'q' => $search, 'pembayaran' => $payment, 'bulan' => $month])) }}" class="admin-tab-item {{ $activeTab === $key ? 'is-active' : '' }}">
                    {{ $label }} ({{ $counts[$key] }})
                </a>
            @endforeach
        </div>

        {{-- FILTER & PENCARIAN (berfungsi) --}}
        <form method="GET" action="{{ route('admin.pesanan') }}" class="admin-filter-bar" id="orderFilterForm">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <div class="admin-search-input-wrap">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6E7A8B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" name="q" placeholder="Cari nama, kode, email, atau paket (Enter)" class="admin-search-input" value="{{ $search }}">
            </div>
            <select name="pembayaran" class="admin-select-dropdown" onchange="this.form.submit()">
                <option value="">Semua pembayaran</option>
                @foreach ($paymentStatuses as $st)
                    <option value="{{ $st }}" @selected($payment === $st)>{{ $st }}</option>
                @endforeach
            </select>
            <select name="bulan" class="admin-select-dropdown" onchange="this.form.submit()">
                <option value="">Semua bulan</option>
                @foreach ($months as $m)
                    <option value="{{ $m['value'] }}" @selected($month === $m['value'])>{{ $m['label'] }}</option>
                @endforeach
            </select>
            @if ($search || $payment || $month)
                <a href="{{ route('admin.pesanan', ['tab' => $activeTab]) }}" class="admin-btn-outline btn-sm">Reset filter</a>
            @endif
        </form>

        {{-- TABEL RECORD (FIGMA 141:1072) --}}
        <div class="admin-table-container">
            <table class="admin-orders-table">
                <thead>
                    <tr>
                        <th style="width: 24%;">Pesanan / pelanggan</th>
                        <th style="width: 22%;">Paket / nilai</th>
                        <th style="width: 18%;">Jadwal WIB</th>
                        <th style="width: 14%;">Sesi</th>
                        <th style="width: 14%;">Pembayaran</th>
                        <th style="width: 8%; text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $item)
                        <tr>
                            <td>
                                <div class="table-cell-order">
                                    <span class="order-code-mono">{{ $item['code'] }}</span>
                                    <strong class="order-customer-name">{{ $item['customer'] }}</strong>
                                </div>
                            </td>
                            <td>
                                <div class="table-cell-package">
                                    <span class="package-name-text">{{ $item['package'] }}</span>
                                    <span class="package-value-text">{{ $item['value'] }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="table-cell-schedule">
                                    <span class="schedule-date-text">{{ $item['date'] }}</span>
                                    <span class="schedule-time-text">{{ $item['time'] }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge-status-session status-{{ Str::slug($item['session_status']) }}">
                                    {{ $item['session_status'] }}
                                </span>
                            </td>
                            <td>
                                <span class="badge-status-payment status-{{ Str::slug($item['payment_status']) }}">
                                    {{ $item['payment_status'] }}
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <a href="{{ route('admin.pesanan.detail', ['id' => $item['code']]) }}" class="admin-table-action-link">
                                    Detail →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center; padding:40px 0; color:#6E7A8B;">
                                Tidak ada pesanan yang cocok dengan tab atau filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('admin.partials.pagination', ['paginator' => $paginator, 'label' => 'pesanan'])
    </div>

    {{-- FOOTER COPYRIGHT (FIGMA 141:1104) --}}
    <div class="admin-footer-copyright">
        © {{ date('Y') }} Naro Capture · Akses terbatas admin
    </div>
@endsection

