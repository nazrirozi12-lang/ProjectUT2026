@extends('layouts.admin')

@section('title', 'Detail Pesanan ' . $order['code'] . ' — Naro Capture')
@section('breadcrumb', 'Pesanan / Detail pesanan')

@section('content')
    {{-- PENGANTAR HALAMAN (FIGMA 141:1125) --}}
    <div class="admin-page-header">
        <div class="admin-header-text">
            <h1 class="admin-page-title">Detail pesanan</h1>
            <p class="admin-page-subtitle">{{ $order['customer'] }} · Dibuat {{ $order['created_date'] }}</p>
        </div>
        <a href="{{ route('admin.pesanan') }}" class="admin-btn-outline">
            ← Kembali ke daftar
        </a>
    </div>

    {{-- BARIS KONTEN STATUS BADGES (FIGMA 141:1129) --}}
    <div class="admin-status-summary-bar">
        <span class="status-indicator-item text-blue">● {{ $order['code'] }}</span>
        <span class="status-indicator-item text-gold">● {{ $order['session_status'] }}</span>
        <span class="status-indicator-item text-blue">● {{ $order['payment_summary'] }}</span>
    </div>

    {{-- GRID 2 KOLOM: KIRI (DETAIL SESI & PELANGGAN) / KANAN (PEMBAYARAN & LINIMASA) --}}
    <div class="admin-order-detail-grid">
        {{-- KOLOM KIRI --}}
        <div class="admin-order-detail-left">
            {{-- PANEL DETAIL SESI (FIGMA 141:1135) --}}
            <div class="admin-panel">
                <h2 class="admin-panel-title">Detail sesi</h2>

                <div class="admin-package-card">
                    <img src="{{ \App\Support\Media::pkg($order['package_image']) }}" alt="{{ $order['package_name'] }}" class="package-card-thumb">
                    <div class="package-card-info">
                        <h3 class="package-card-name">{{ $order['package_name'] }}</h3>
                        @if ($order['package_desc'])<p class="package-card-desc">{{ $order['package_desc'] }}</p>@endif
                        <strong class="package-card-price">{{ $order['package_price'] }} / sesi</strong>
                    </div>
                </div>

                <hr class="divider">

                <div class="admin-meta-row-2">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Tanggal sesi</label>
                        <p class="admin-meta-val">{{ $order['date_formatted'] }}</p>
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Waktu · durasi</label>
                        <p class="admin-meta-val">{{ $order['time_formatted'] }}</p>
                    </div>
                </div>

                <div class="admin-meta-field">
                    <label class="admin-meta-label">Lokasi sesi</label>
                    <p class="admin-meta-val">{{ $order['location'] }}</p>
                    <span class="admin-meta-hint">Alamat akhir dibahas saat perencanaan.</span>
                </div>

                <div class="admin-package-features-block">
                    <h4 class="features-block-title">Termasuk dalam harga yang dipesan</h4>
                    <ul class="features-checklist">
                        @foreach($order['features'] as $feature)
                            <li>
                                <span class="check-mark">✓</span>
                                <span>{{ $feature }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- PANEL PELANGGAN & CATATAN (FIGMA 141:1151) --}}
            <div class="admin-panel">
                <h2 class="admin-panel-title">Pelanggan & catatan</h2>

                <div class="admin-meta-row-2">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Pemesan</label>
                        <p class="admin-meta-val">{{ $order['customer'] }} · {{ $order['customer_code'] }}</p>
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Kontak</label>
                        <p class="admin-meta-val">{{ $order['email'] }} · {{ $order['phone'] }}</p>
                    </div>
                </div>

                <div class="admin-meta-field">
                    <label class="admin-meta-label">Catatan pelanggan</label>
                    <p class="admin-meta-val">{{ $order['customer_note'] }}</p>
                </div>

                <div class="admin-internal-note-box">
                    <label class="internal-note-label">Catatan internal</label>
                    <p class="internal-note-text">{{ $order['internal_note'] ?: 'Belum ada catatan internal. Tambahkan lewat tombol Edit.' }}</p>
                </div>
                <span class="admin-meta-hint">Hanya admin · {{ $order['updated_note'] }}</span>
            </div>
        </div>

        {{-- KOLOM KANAN: RINGKASAN DAN TINDAK LANJUT (FIGMA 141:1160) --}}
        <div class="admin-order-detail-right">
            {{-- PANEL PEMBAYARAN & INVOICE (FIGMA 141:1161) --}}
            <div class="admin-panel">
                <h2 class="admin-panel-title">Pembayaran & invoice</h2>

                <div class="admin-meta-field">
                    <label class="admin-meta-label">Nomor invoice</label>
                    <p class="admin-meta-val font-mono">{{ $order['invoice_number'] }}</p>
                </div>

                <div class="admin-meta-field">
                    <label class="admin-meta-label">Harga dipesan / total</label>
                    <p class="admin-meta-val">{{ $order['package_price'] }} · 1 sesi</p>
                </div>

                <div class="admin-meta-field">
                    <span class="status-indicator-item text-gold">● {{ $order['payment_status_badge'] }}</span>
                </div>

                <div class="admin-meta-field">
                    <label class="admin-meta-label">Nominal diajukan</label>
                    <p class="admin-meta-val">{{ $order['submitted_nominal'] }} · {{ $order['submitted_date'] }}</p>
                </div>

                @if ($order['payment_code'])
                    <a href="{{ route('admin.pembayaran.detail', ['id' => $order['payment_code']]) }}" class="admin-btn-outline w-full text-center">
                        Lihat bukti pembayaran
                    </a>
                @else
                    <span class="admin-meta-hint">Belum ada bukti pembayaran dari pelanggan.</span>
                @endif

                {{-- STATUS & TINDAKAN SESI (sesuai status nyata) --}}
                @if ($order['session_status'] === 'Permintaan')
                    <div class="admin-alert-box">
                        <strong class="admin-alert-title">Jadwal belum disetujui</strong>
                        <p class="admin-alert-desc">Verifikasi pembayaran tidak otomatis mengonfirmasi sesi. Sesi dapat dikonfirmasi setelah pembayaran terverifikasi.</p>
                    </div>
                    @if ($order['can_confirm'])
                        <form action="{{ route('admin.pesanan.konfirmasi-sesi', ['id' => $order['code']]) }}" method="POST">
                            @csrf
                            <button type="submit" class="admin-btn-primary w-full">Konfirmasi sesi</button>
                        </form>
                    @else
                        <button type="button" class="admin-btn-primary w-full" disabled style="opacity:.5; cursor:not-allowed;">Konfirmasi sesi</button>
                        <span class="admin-meta-hint">{{ $order['confirm_hint'] }}</span>
                    @endif
                @elseif ($order['session_status'] === 'Dikonfirmasi')
                    <div class="admin-alert-box alert-success-box">
                        <strong class="admin-alert-title text-green">Jadwal sudah dikonfirmasi</strong>
                        <p class="admin-alert-desc text-green">Pelanggan melihat status “Dikonfirmasi” di halaman cek pesanan.</p>
                    </div>
                    <form action="{{ route('admin.pesanan.selesai-sesi', ['id' => $order['code']]) }}" method="POST">
                        @csrf
                        <button type="submit" class="admin-btn-primary w-full">Tandai sesi selesai</button>
                    </form>
                @elseif ($order['session_status'] === 'Selesai')
                    <div class="admin-alert-box alert-success-box">
                        <strong class="admin-alert-title text-green">Sesi sudah selesai</strong>
                        <p class="admin-alert-desc text-green">Pesanan dapat diarsipkan atau dihubungkan ke galeri klien.</p>
                    </div>
                @elseif ($order['session_status'] === 'Dibatalkan')
                    <div class="admin-alert-box alert-warning-box">
                        <strong class="admin-alert-title text-gold">Pesanan dibatalkan</strong>
                        <p class="admin-alert-desc text-gold">{{ $order['cancel_reason'] ?: 'Tanpa alasan tercatat.' }}</p>
                    </div>
                @else
                    <div class="admin-alert-box">
                        <strong class="admin-alert-title">Pesanan diarsipkan</strong>
                        <p class="admin-alert-desc">Riwayat tetap tersimpan dan dapat dilihat di tab Arsip.</p>
                    </div>
                @endif

                <p class="admin-meta-disclaimer">
                    Tinjau ketersediaan dan pembayaran sebelum menyetujui. Konfirmasi mengubah status yang dilihat pelanggan.
                </p>
            </div>

            {{-- PANEL LINIMASA PESANAN (FIGMA 141:1182) --}}
            <div class="admin-panel">
                <h2 class="admin-panel-title">Linimasa pesanan</h2>

                <div class="admin-order-timeline">
                    @foreach ($order['timeline'] as $step)
                        <div class="timeline-step is-{{ $step['state'] === 'complete' ? 'complete' : ($step['state'] === 'pending' ? 'pending' : $step['state']) }}">
                            @if ($step['state'] === 'complete')
                                <span class="timeline-check">✓</span>
                            @elseif ($step['state'] === 'warn')
                                <span class="timeline-circle">!</span>
                            @elseif ($step['state'] === 'cancelled')
                                <span class="timeline-circle">×</span>
                            @else
                                <span class="timeline-circle">○</span>
                            @endif
                            <div class="timeline-text">
                                @if ($step['state'] === 'complete' || $step['state'] === 'cancelled')
                                    <strong>{{ $step['text'] }}</strong>
                                @else
                                    <span>{{ $step['text'] }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <hr class="divider">

                <div class="admin-btn-group-2">
                    @if ($order['can_edit'])
                        <button type="button" class="admin-btn-outline flex-1" id="btn-open-edit-order">Edit</button>
                    @endif
                    @if ($order['can_cancel'])
                        <button type="button" class="admin-btn-outline btn-outline-danger flex-1" id="btn-open-cancel-order">Batalkan</button>
                    @endif
                </div>

                @if ($order['can_archive'])
                    <button type="button" class="admin-btn-outline w-full" id="btn-open-archive-order">Arsipkan pesanan</button>
                @elseif ($order['session_status'] !== 'Diarsipkan')
                    <span class="admin-meta-hint">Pesanan dapat diarsipkan setelah sesi selesai atau dibatalkan.</span>
                @endif
            </div>
        </div>
    </div>

    {{-- MODAL DIALOG BATALKAN PESANAN (FIGMA 141:1411) --}}
    <div class="admin-modal-overlay" id="modal-cancel-order">
        <div class="admin-modal-dialog">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Batalkan pesanan?</h3>
                <button type="button" class="admin-btn-close-modal" data-close="modal-cancel-order">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#253247" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <p class="admin-modal-subtitle">{{ $order['code'] }} · {{ $order['customer'] }} · {{ $order['package_name'] }}</p>

            <hr class="divider">

            <div class="admin-meta-field">
                <label class="admin-meta-label">Sesi yang akan dibatalkan</label>
                <p class="admin-meta-val">{{ $order['date_formatted'] }} · {{ $order['time_formatted'] }}</p>
            </div>

            <form action="{{ route('admin.pesanan.batalkan-sesi', ['id' => $order['code']]) }}" method="POST" id="form-cancel-order">
            @csrf
            <div class="admin-meta-field">
                <label class="admin-meta-label">Alasan pembatalan *</label>
                <textarea class="admin-form-input" name="alasan" rows="3" required minlength="3" maxlength="500" placeholder="Contoh: Pelanggan meminta membatalkan rencana sesi.">{{ old('alasan') }}</textarea>
            </div>
            </form>

            <div class="admin-alert-box alert-warning-box">
                <strong class="admin-alert-title text-gold">Pembayaran ditinjau terpisah</strong>
                <p class="admin-alert-desc text-gold">
                    Pembayaran saat ini: {{ $order['payment_summary'] }} ({{ $order['submitted_nominal'] }}). Pembatalan tidak otomatis mengembalikan dana. Tinjau kelayakan dan catat ketentuan refund yang disetujui.
                </p>
            </div>

            <p class="admin-meta-disclaimer">
                Status pelanggan menjadi “Dibatalkan”. Jadwal permintaan dilepas; invoice, bukti dan riwayat audit tetap tersimpan.
            </p>

            <hr class="divider">

            <div class="admin-modal-actions">
                <button type="button" class="admin-btn-outline" data-close="modal-cancel-order">Batal</button>
                <button type="submit" form="form-cancel-order" class="admin-btn-primary" style="background:#D32F2F; border-color:#D32F2F;">
                    Batalkan pesanan
                </button>
            </div>
        </div>
    </div>

    {{-- MODAL DIALOG ARSIPKAN PESANAN (FIGMA 141:1533) --}}
    <div class="admin-modal-overlay" id="modal-archive-order">
        <div class="admin-modal-dialog">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Arsipkan pesanan selesai?</h3>
                <button type="button" class="admin-btn-close-modal" data-close="modal-archive-order">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#253247" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <p class="admin-modal-subtitle">{{ $order['code'] }} · {{ $order['customer'] }} · {{ $order['package_name'] }}</p>

            <hr class="divider">

            <div class="admin-meta-field">
                <label class="admin-meta-label">Status saat ini</label>
                <p class="admin-meta-val">{{ $order['session_status'] }} · {{ $order['date_formatted'] }} · {{ $order['payment_summary'] }}</p>
            </div>

            <div class="admin-alert-box alert-success-box">
                <strong class="admin-alert-title text-green">Riwayat tetap tersimpan</strong>
                <p class="admin-alert-desc text-green">
                    Pesanan berpindah ke tab Arsip. Invoice {{ $order['invoice_number'] }}, bukti pembayaran, dan audit tidak dihapus.
                </p>
            </div>

            <p class="admin-meta-disclaimer">
                Pesanan aktif tidak bisa dihapus atau diarsipkan sebelum penyelesaian ditinjau. Arsip tidak memicu pembayaran atau refund.
            </p>

            <hr class="divider">

            <div class="admin-modal-actions">
                <button type="button" class="admin-btn-outline" data-close="modal-archive-order">Batal</button>
                <form action="{{ route('admin.pesanan.arsipkan-sesi', ['id' => $order['code']]) }}" method="POST" style="display:inline;">
                    @csrf
                    <button type="submit" class="admin-btn-primary">Arsipkan pesanan</button>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL DIALOG EDIT PESANAN (FIGMA 141:1318) --}}
    <div class="admin-modal-overlay" id="modal-edit-order">
        <div class="admin-modal-dialog">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Edit pesanan</h3>
                <button type="button" class="admin-btn-close-modal" data-close="modal-edit-order">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#253247" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <p class="admin-modal-subtitle">{{ $order['code'] }} · {{ $order['customer'] }}</p>

            <hr class="divider">

            <form action="{{ route('admin.pesanan.update', ['id' => $order['code']]) }}" method="POST" id="form-edit-order">
                @csrf
                @method('PUT')
                <div class="admin-meta-row-2">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Tanggal sesi</label>
                        <input type="date" name="session_date" class="admin-form-input" required value="{{ old('session_date', $order['date_input']) }}">
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Jam mulai (WIB)</label>
                        <select name="session_time" class="admin-form-input" required>
                            @foreach ($order['slots'] as $slot)
                                <option value="{{ $slot }}" @selected(old('session_time', $order['time_start']) === $slot)>{{ $slot }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <span class="admin-meta-hint">Durasi mengikuti paket. Jadwal baru dicek terhadap pesanan lain dan blokir studio.</span>

                <div class="admin-meta-field">
                    <label class="admin-meta-label">Lokasi sesi</label>
                    <input type="text" name="location" class="admin-form-input" required maxlength="255" value="{{ old('location', $order['location']) }}">
                </div>

                <div class="admin-meta-field">
                    <label class="admin-meta-label">Catatan internal admin</label>
                    <textarea name="internal_notes" class="admin-form-input" rows="3" maxlength="2000">{{ old('internal_notes', $order['internal_note']) }}</textarea>
                </div>
            </form>

            <hr class="divider">

            <div class="admin-modal-actions">
                <button type="button" class="admin-btn-outline" data-close="modal-edit-order">Batal</button>
                <button type="submit" form="form-edit-order" class="admin-btn-primary">Simpan perubahan</button>
            </div>
        </div>
    </div>

    {{-- FOOTER COPYRIGHT (FIGMA 141:1199) --}}
    <div class="admin-footer-copyright">
        © {{ date('Y') }} Naro Capture · Akses terbatas admin
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const bindModal = (btnId, modalId) => {
                const btn = document.getElementById(btnId);
                const modal = document.getElementById(modalId);
                if (btn && modal) {
                    btn.addEventListener('click', () => modal.classList.add('is-open'));
                }
            };

            bindModal('btn-open-cancel-order', 'modal-cancel-order');
            bindModal('btn-open-archive-order', 'modal-archive-order');
            bindModal('btn-open-edit-order', 'modal-edit-order');
            @if ($errors->has('session_date') || $errors->has('session_time') || $errors->has('location'))
                document.getElementById('modal-edit-order')?.classList.add('is-open');
            @endif
            @if ($errors->has('alasan'))
                document.getElementById('modal-cancel-order')?.classList.add('is-open');
            @endif

            document.querySelectorAll('[data-close]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const targetId = btn.getAttribute('data-close');
                    const targetModal = document.getElementById(targetId);
                    if (targetModal) targetModal.classList.remove('is-open');
                });
            });
        });
    </script>
@endsection

