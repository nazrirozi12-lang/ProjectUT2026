@extends('layouts.admin')

@section('title', 'Pembayaran — Naro Capture')
@section('breadcrumb', 'Pembayaran / Pembayaran')

@section('content')
    {{-- PENGANTAR HALAMAN (FIGMA 141:1701) --}}
    <div class="admin-page-header">
        <div class="admin-header-text">
            <h1 class="admin-page-title">Pembayaran</h1>
            <p class="admin-page-subtitle">Cocokkan bukti dengan invoice. Verifikasi tidak menyetujui jadwal sesi.</p>
        </div>
        <button type="button" class="admin-btn-primary" id="btn-open-manual-payment">
            Catat pembayaran manual
        </button>
    </div>

    {{-- 4 KARTU METRIK PEMBAYARAN (data nyata) --}}
    <div class="admin-metrics-grid">
        <div class="admin-panel admin-payment-metric-card">
            <span class="payment-metric-label">Bukti menunggu</span>
            <strong class="payment-metric-val">{{ $metrics['waiting']['count'] }}</strong>
            <span class="payment-metric-sub">Rp{{ number_format($metrics['waiting']['sum'], 0, ',', '.') }} diajukan</span>
        </div>
        <div class="admin-panel admin-payment-metric-card">
            <span class="payment-metric-label">Perlu perbaikan</span>
            <strong class="payment-metric-val">{{ $metrics['fix']['count'] }}</strong>
            <span class="payment-metric-sub">Rp{{ number_format($metrics['fix']['sum'], 0, ',', '.') }} diajukan</span>
        </div>
        <div class="admin-panel admin-payment-metric-card">
            <span class="payment-metric-label">Terverifikasi {{ $metrics['verified']['label'] }}</span>
            <strong class="payment-metric-val">{{ $metrics['verified']['count'] }}</strong>
            <span class="payment-metric-sub">Rp{{ number_format($metrics['verified']['sum'], 0, ',', '.') }} tercatat</span>
        </div>
        <div class="admin-panel admin-payment-metric-card">
            <span class="payment-metric-label">Belum dibayar</span>
            <strong class="payment-metric-val">{{ $metrics['unpaid']['count'] }}</strong>
            <span class="payment-metric-sub">Rp{{ number_format($metrics['unpaid']['sum'], 0, ',', '.') }} pada invoice</span>
        </div>
    </div>

    {{-- PANEL UTAMA TABEL PEMBAYARAN (FIGMA 141:1729) --}}
    <div class="admin-panel admin-orders-panel">
        {{-- TABS (hitungan nyata) --}}
        @php
            $tabDefs = ['semua' => 'Semua', 'bukti-terkirim' => 'Bukti terkirim', 'belum-dibayar' => 'Belum dibayar', 'terverifikasi' => 'Terverifikasi', 'perlu-perbaikan' => 'Perlu perbaikan'];
        @endphp
        <div class="admin-tabs">
            @foreach ($tabDefs as $key => $label)
                <a href="{{ route('admin.pembayaran', array_filter(['tab' => $key, 'q' => $search, 'sumber' => $source, 'bulan' => $month])) }}" class="admin-tab-item {{ $activeTab === $key ? 'is-active' : '' }}">
                    {{ $label }} ({{ $counts[$key] }})
                </a>
            @endforeach
        </div>

        {{-- FILTER & PENCARIAN (berfungsi) --}}
        <form method="GET" action="{{ route('admin.pembayaran') }}" class="admin-filter-bar">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <div class="admin-search-input-wrap">
                <input type="text" name="q" placeholder="Cari ID pembayaran, invoice, atau pelanggan (Enter)" class="admin-search-input" value="{{ $search }}">
            </div>
            <select name="sumber" class="admin-select-dropdown" onchange="this.form.submit()">
                <option value="">Semua sumber</option>
                @foreach ($sources as $src)
                    <option value="{{ $src }}" @selected($source === $src)>{{ $src }}</option>
                @endforeach
            </select>
            <select name="bulan" class="admin-select-dropdown" onchange="this.form.submit()">
                <option value="">Semua bulan</option>
                @foreach ($months as $m)
                    <option value="{{ $m['value'] }}" @selected($month === $m['value'])>{{ $m['label'] }}</option>
                @endforeach
            </select>
            @if ($search || $source || $month)
                <a href="{{ route('admin.pembayaran', ['tab' => $activeTab]) }}" class="admin-btn-outline btn-sm">Reset filter</a>
            @endif
        </form>

        @if ($oldestLabel)
            <div class="admin-alert-box alert-warning-box">
                <strong class="admin-alert-title text-gold">Bukti tertua diterima {{ $oldestLabel }}</strong>
                <p class="admin-alert-desc text-gold">Tinjau antrean sesuai waktu kirim. Penanda ini bukan janji waktu penyelesaian.</p>
            </div>
        @endif

        {{-- TABEL RECORD PEMBAYARAN (FIGMA 141:1754) --}}
        <div class="admin-table-container">
            <table class="admin-orders-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Pembayaran / pengirim</th>
                        <th style="width: 25%;">Invoice / pesanan</th>
                        <th style="width: 15%;">Nominal</th>
                        <th style="width: 15%;">Diterima</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 8%; text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $p)
                        <tr>
                            <td>
                                <div class="table-cell-order">
                                    <span class="order-code-mono">{{ $p['id'] }}</span>
                                    <strong class="order-customer-name">{{ $p['sender'] }}</strong>
                                </div>
                            </td>
                            <td>
                                <div class="table-cell-package">
                                    <span class="order-code-mono">{{ $p['invoice'] }}</span>
                                    <span class="schedule-time-text">{{ $p['order_code'] }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="order-code-mono">{{ $p['nominal'] }}</span>
                            </td>
                            <td>
                                <div class="table-cell-schedule">
                                    <span class="schedule-date-text">{{ $p['received_date'] }}</span>
                                    <span class="schedule-time-text">{{ $p['received_time'] }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge-status-payment status-{{ Str::slug($p['status']) }}">
                                    {{ $p['status'] }}
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <a href="{{ $p['link'] }}" class="admin-table-action-link">
                                    {{ $p['link_label'] }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center; padding:40px 0; color:#6E7A8B;">
                                Tidak ada pembayaran yang cocok dengan tab atau filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('admin.partials.pagination', ['paginator' => $paginator, 'label' => 'pembayaran'])
        <p class="admin-table-footer-hint">
            ID pembayaran berbeda dari kode pesanan dan nomor invoice. Transaksi terverifikasi tidak dapat dihapus.
        </p>
    </div>

    {{-- MODAL DIALOG CATAT PEMBAYARAN MANUAL (FIGMA 141:2208) --}}
    <div class="admin-modal-overlay" id="modal-manual-payment">
        <div class="admin-modal-dialog">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Catat pembayaran manual</h3>
                <button type="button" class="admin-btn-close-modal" data-close="modal-manual-payment">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#253247" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <p class="admin-modal-subtitle">Rekam transaksi tunai atau transfer langsung di studio</p>

            <hr class="divider">
            <form action="{{ route('admin.pembayaran.manual') }}" method="POST" id="form-manual-payment">
                @csrf
                <div class="admin-meta-row-2">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Nomor invoice terkait *</label>
                        <input type="text" name="invoice" class="admin-form-input font-mono" placeholder="INV-CH-2026-XXXXX" required value="{{ old('invoice') }}">
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Metode penerimaan *</label>
                        <select name="metode" class="admin-form-input" required>
                            @foreach ($manualMethods as $mm)
                                <option value="{{ $mm }}" @selected(old('metode') === $mm)>{{ $mm }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="admin-meta-row-2">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Nominal diterima *</label>
                        <input type="text" name="nominal" class="admin-form-input font-mono" placeholder="Contoh: 2000000" inputmode="numeric" required value="{{ old('nominal') }}">
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Tanggal penerimaan *</label>
                        <input type="date" name="tanggal" class="admin-form-input" required value="{{ old('tanggal', date('Y-m-d')) }}">
                    </div>
                </div>
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Catatan kasir / administrasi</label>
                    <textarea name="catatan" class="admin-form-input" rows="3" maxlength="500" placeholder="Contoh: Diterima langsung di studio.">{{ old('catatan') }}</textarea>
                </div>
            </form>
            <div class="admin-alert-box alert-success-box">
                <strong class="admin-alert-title text-green">Pencatatan resmi</strong>
                <p class="admin-alert-desc text-green">
                    Pembayaran manual langsung berstatus terverifikasi dan memperbarui status pembayaran invoice terkait. Nomor invoice ada di halaman Pesanan.
                </p>
            </div>
            <hr class="divider">
            <div class="admin-modal-actions">
                <button type="button" class="admin-btn-outline" data-close="modal-manual-payment">Batal</button>
                <button type="submit" form="form-manual-payment" class="admin-btn-primary">Simpan pembayaran</button>
            </div>
        </div>
    </div>
    {{-- FOOTER COPYRIGHT (FIGMA 141:1780) --}}
    <div class="admin-footer-copyright">
        © {{ date('Y') }} Naro Capture · Akses terbatas admin
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('btn-open-manual-payment');
            const modal = document.getElementById('modal-manual-payment');
            if (btn && modal) {
                btn.addEventListener('click', () => modal.classList.add('is-open'));
            }
            @if ($errors->has('invoice') || $errors->has('metode') || $errors->has('nominal') || $errors->has('tanggal'))
                modal?.classList.add('is-open');
            @endif

            document.querySelectorAll('[data-close]').forEach(b => {
                b.addEventListener('click', () => {
                    const id = b.getAttribute('data-close');
                    const m = document.getElementById(id);
                    if (m) m.classList.remove('is-open');
                });
            });
        });
    </script>
@endsection

