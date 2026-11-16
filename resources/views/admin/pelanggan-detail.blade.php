@extends('layouts.admin')
@section('title', 'Detail Pelanggan ' . $customer['code'] . ' — Naro Capture')
@section('breadcrumb', 'Pelanggan / Detail pelanggan')
@section('content')
    <div class="admin-page-header">
        <div class="admin-header-text">
            <h1 class="admin-page-title">{{ $customer['name'] }}</h1>
            <p class="admin-page-subtitle">Pelanggan sejak {{ $customer['since'] }} · {{ $customer['code'] }}</p>
        </div>
        <a href="{{ route('admin.pelanggan') }}" class="admin-btn-outline">← Kembali ke daftar</a>
    </div>

    <div class="admin-status-summary-bar">
        <span class="status-indicator-item {{ $customer['status'] === 'Aktif' ? 'text-green' : 'text-muted' }}">● {{ $customer['status'] }}</span>
        <span class="status-indicator-item text-blue">● {{ $customer['order_count'] }} pesanan</span>
        <span class="status-indicator-item text-gold">● {{ $customer['active_count'] }} pesanan aktif</span>
        <span class="status-indicator-item text-green">● Total terbayar {{ $customer['spent'] }}</span>
    </div>

    <div class="admin-order-detail-grid">
        <div class="admin-order-detail-left">
            {{-- RIWAYAT PESANAN --}}
            <div class="admin-panel">
                <h2 class="admin-panel-title">Riwayat pesanan</h2>
                @if ($customer['upcoming'])
                    <div class="admin-alert-box alert-success-box">
                        <strong class="admin-alert-title text-green">Sesi berikutnya</strong>
                        <p class="admin-alert-desc text-green">{{ $customer['upcoming'] }}</p>
                    </div>
                @endif
                <div class="admin-table-container">
                    <table class="admin-orders-table">
                        <thead>
                            <tr>
                                <th>Pesanan</th><th>Paket</th><th>Tanggal sesi</th><th>Nilai</th><th>Status</th><th style="text-align:right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($customer['orders'] as $o)
                                <tr>
                                    <td><span class="order-code-mono">{{ $o['code'] }}</span></td>
                                    <td>{{ $o['package'] }}</td>
                                    <td><span class="schedule-date-text">{{ $o['date'] }}</span></td>
                                    <td><span class="order-code-mono">{{ $o['value'] }}</span></td>
                                    <td>
                                        <span class="badge-status-session status-{{ Str::slug($o['session_status']) }}">{{ $o['session_status'] }}</span><br>
                                        <span class="schedule-time-text">{{ $o['payment_status'] }}</span>
                                    </td>
                                    <td style="text-align:right;"><a href="{{ route('admin.pesanan.detail', ['id' => $o['code']]) }}" class="admin-table-action-link">Buka →</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" style="text-align:center; padding:28px 0; color:#6E7A8B;">Pelanggan ini belum memiliki pesanan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="admin-order-detail-right">
            {{-- PROFIL & KONTAK --}}
            <div class="admin-panel">
                <h2 class="admin-panel-title">Profil & kontak</h2>
                <div class="admin-meta-field"><label class="admin-meta-label">Email</label><p class="admin-meta-val">{{ $customer['email'] }}</p></div>
                <div class="admin-meta-field"><label class="admin-meta-label">WhatsApp</label><p class="admin-meta-val">{{ $customer['whatsapp'] }}</p></div>
                <div class="admin-meta-field"><label class="admin-meta-label">Pesanan terakhir</label><p class="admin-meta-val">{{ $customer['last_order'] }}</p></div>
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Catatan pribadi</label>
                    <p class="admin-meta-val">{{ $customer['notes'] ?: 'Belum ada catatan.' }}</p>
                </div>
                @if ($customer['wa_link'])
                    <a href="{{ $customer['wa_link'] }}" target="_blank" rel="noopener" class="admin-btn-outline w-full text-center">Hubungi lewat WhatsApp ↗</a>
                @endif
                <a href="mailto:{{ $customer['email'] }}" class="admin-btn-outline w-full text-center">Kirim email ↗</a>
                <hr class="divider">
                <button type="button" class="admin-btn-primary w-full" id="btn-open-edit-customer">Edit data pelanggan</button>
                @if ($customer['status'] === 'Aktif')
                    <button type="button" class="admin-btn-outline btn-outline-danger w-full" id="btn-open-archive-customer">Arsipkan pelanggan</button>
                @else
                    <form action="{{ route('admin.pelanggan.aktifkan', ['id' => $customer['code']]) }}" method="POST">
                        @csrf
                        <button type="submit" class="admin-btn-outline w-full">Aktifkan kembali</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- MODAL EDIT --}}
    <div class="admin-modal-overlay" id="modal-edit-customer">
        <div class="admin-modal-dialog">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Edit data pelanggan</h3>
                <button type="button" class="admin-btn-close-modal" data-close="modal-edit-customer">×</button>
            </div>
            <hr class="divider">
            <form action="{{ route('admin.pelanggan.update', ['id' => $customer['code']]) }}" method="POST" id="form-edit-customer">
                @csrf
                @method('PUT')
                <div class="admin-meta-row-2">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Nama lengkap *</label>
                        <input type="text" name="name" class="admin-form-input" required maxlength="255" value="{{ old('name', $customer['name']) }}">
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">WhatsApp *</label>
                        <input type="text" name="whatsapp" class="admin-form-input" required maxlength="50" value="{{ old('whatsapp', $customer['whatsapp']) }}">
                    </div>
                </div>
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Email *</label>
                    <input type="email" name="email" class="admin-form-input" required maxlength="255" value="{{ old('email', $customer['email']) }}">
                </div>
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Catatan pribadi</label>
                    <textarea name="notes" class="admin-form-input" rows="3" maxlength="1000">{{ old('notes', $customer['notes']) }}</textarea>
                </div>
            </form>
            <hr class="divider">
            <div class="admin-modal-actions">
                <button type="button" class="admin-btn-outline" data-close="modal-edit-customer">Batal</button>
                <button type="submit" form="form-edit-customer" class="admin-btn-primary">Simpan perubahan</button>
            </div>
        </div>
    </div>

    {{-- MODAL ARSIP --}}
    <div class="admin-modal-overlay" id="modal-archive-customer">
        <div class="admin-modal-dialog">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Arsipkan pelanggan?</h3>
                <button type="button" class="admin-btn-close-modal" data-close="modal-archive-customer">×</button>
            </div>
            <p class="admin-modal-subtitle">{{ $customer['name'] }} · {{ $customer['code'] }}</p>
            <hr class="divider">
            <p class="admin-meta-val">Pelanggan dipindahkan ke tab Arsip. Riwayat pesanan dan pembayaran tetap tersimpan, dan pelanggan dapat diaktifkan kembali kapan saja.</p>
            <hr class="divider">
            <form action="{{ route('admin.pelanggan.arsipkan', ['id' => $customer['code']]) }}" method="POST" class="admin-modal-actions">
                @csrf
                <button type="button" class="admin-btn-outline" data-close="modal-archive-customer">Batal</button>
                <button type="submit" class="admin-btn-primary">Arsipkan pelanggan</button>
            </form>
        </div>
    </div>

    <div class="admin-footer-copyright">© {{ date('Y') }} Naro Capture · Akses terbatas admin</div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const open = id => document.getElementById(id).classList.add('is-open');
            document.getElementById('btn-open-edit-customer').addEventListener('click', () => open('modal-edit-customer'));
            const arch = document.getElementById('btn-open-archive-customer');
            if (arch) arch.addEventListener('click', () => open('modal-archive-customer'));
            document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => document.getElementById(b.getAttribute('data-close')).classList.remove('is-open')));
            @if ($errors->has('name') || $errors->has('email') || $errors->has('whatsapp'))
                open('modal-edit-customer');
            @endif
        });
    </script>
@endsection
