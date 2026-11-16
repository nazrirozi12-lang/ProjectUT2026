@extends('layouts.admin')

@section('title', 'Detail Bukti Pembayaran ' . $payment['id'] . ' — Naro Capture')
@section('breadcrumb', 'Pembayaran / Detail bukti pembayaran')

@section('content')
    {{-- PENGANTAR HALAMAN (FIGMA 141:1801) --}}
    <div class="admin-page-header">
        <div class="admin-header-text">
            <h1 class="admin-page-title">Detail bukti pembayaran</h1>
            <p class="admin-page-subtitle">Tinjau sumber, nominal, dan invoice yang cocok sebelum mengambil keputusan.</p>
        </div>
        <a href="{{ route('admin.pembayaran') }}" class="admin-btn-outline">
            ← Kembali ke daftar
        </a>
    </div>

    {{-- STATUS BADGES (FIGMA 141:1805) --}}
    <div class="admin-status-summary-bar">
        <span class="status-indicator-item text-blue">● {{ $payment['id'] }}</span>
        <span class="status-indicator-item text-gold">● {{ $payment['status_badge'] }}</span>
    </div>

    {{-- GRID 2 KOLOM (FIGMA 141:1808) --}}
    <div class="admin-order-detail-grid">
        {{-- KOLOM KIRI: BUKTI & DATA TRANSFER --}}
        <div class="admin-order-detail-left">
            {{-- PANEL BUKTI YANG DIAJUKAN (FIGMA 141:1810) --}}
            <div class="admin-panel">
                <h2 class="admin-panel-title">Bukti yang diajukan</h2>

                <div class="admin-doc-toolbar">
                    <strong class="doc-filename-text">{{ $payment['file_name'] }}</strong>
                    <span class="doc-meta-text">{{ $payment['file_meta'] }}</span>
                </div>

                {{-- PRATINJAU DOKUMEN BUKTI (nyata) --}}
                @if ($payment['proof']['available'] && $payment['proof']['kind'] === 'image')
                    <div class="admin-doc-preview-box" style="padding:12px; align-items:center;">
                        <a href="{{ $payment['proof']['url'] }}" target="_blank" rel="noopener">
                            <img src="{{ $payment['proof']['url'] }}" alt="Bukti transfer {{ $payment['sender'] }}" style="max-width:100%; max-height:560px; border-radius:6px; display:block; margin:0 auto;">
                        </a>
                    </div>
                @elseif ($payment['proof']['available'] && $payment['proof']['kind'] === 'pdf')
                    <iframe src="{{ $payment['proof']['url'] }}" title="Bukti transfer PDF" style="width:100%; height:560px; border:1px solid var(--line); border-radius:6px;"></iframe>
                @elseif ($payment['proof']['available'])
                    <div class="admin-doc-preview-box">
                        <strong class="doc-preview-title">Pratinjau tidak tersedia untuk jenis berkas ini</strong>
                        <p class="doc-preview-desc">Unduh berkas untuk memeriksanya.</p>
                    </div>
                @elseif ($payment['has_proof_file'])
                    <div class="admin-doc-preview-box">
                        <strong class="doc-preview-title">Berkas bukti tidak ditemukan di server</strong>
                        <p class="doc-preview-desc">
                            Berkas tidak tersimpan (kemungkinan diunggah sebelum penyimpanan bukti diaktifkan). Minta pelanggan mengunggah ulang bukti transfer lewat “Minta perbaikan bukti”.
                        </p>
                    </div>
                @else
                    <div class="admin-doc-preview-box">
                        <strong class="doc-preview-title">Tanpa berkas bukti</strong>
                        <p class="doc-preview-desc">Pembayaran ini dicatat manual oleh admin sehingga tidak ada berkas unggahan pelanggan.</p>
                    </div>
                @endif

                @if ($payment['proof']['available'])
                    <a href="{{ $payment['proof']['url'] }}" target="_blank" rel="noopener" class="admin-btn-outline" style="text-align:center;">
                        Buka berkas di tab baru ↗
                    </a>
                @endif
            </div>

            {{-- PANEL DATA TRANSFER YANG DIAJUKAN (FIGMA 141:1824) --}}
            <div class="admin-panel">
                <h2 class="admin-panel-title">Data transfer yang diajukan</h2>

                <div class="admin-meta-row-2">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Nama pengirim</label>
                        <p class="admin-meta-val">{{ $payment['sender'] }}</p>
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Tanggal transfer</label>
                        <p class="admin-meta-val">{{ $payment['transfer_date'] }}</p>
                    </div>
                </div>

                <div class="admin-meta-row-2">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Sumber pembayaran</label>
                        <p class="admin-meta-val">{{ $payment['bank_source'] }}</p>
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Nominal diajukan</label>
                        <p class="admin-meta-val font-mono">{{ $payment['nominal'] }}</p>
                    </div>
                </div>

                <div class="admin-meta-field">
                    <label class="admin-meta-label">Catatan pengirim</label>
                    <p class="admin-meta-val">{{ $payment['sender_note'] }}</p>
                </div>

                <span class="admin-meta-hint">
                    Dikirim · {{ $payment['submitted_at'] }} · Bukti asli dipertahankan
                </span>
            </div>
        </div>

        {{-- KOLOM KANAN: INVOICE TERKAIT & RIWAYAT TINJAUAN --}}
        <div class="admin-order-detail-right">
            {{-- PANEL INVOICE TERKAIT (FIGMA 141:1835) --}}
            <div class="admin-panel">
                <h2 class="admin-panel-title">Invoice terkait</h2>

                <div class="admin-meta-field">
                    <label class="admin-meta-label">Invoice</label>
                    <p class="admin-meta-val font-mono">{{ $payment['invoice'] }}</p>
                </div>

                <div class="admin-meta-field">
                    <label class="admin-meta-label">Pesanan</label>
                    <p class="admin-meta-val">{{ $payment['order_code'] }} · {{ $payment['sender'] }}</p>
                </div>

                <div class="admin-meta-field">
                    <label class="admin-meta-label">Paket / total invoice</label>
                    <p class="admin-meta-val">{{ $payment['package_name'] }} · {{ $payment['nominal'] }}</p>
                </div>

                <div class="admin-meta-field">
                    <label class="admin-meta-label">Jadwal diminta</label>
                    <p class="admin-meta-val">{{ $payment['session_schedule'] }}</p>
                </div>

                <div class="admin-meta-field">
                    <label class="admin-meta-label">Total invoice</label>
                    <p class="admin-meta-val font-mono">{{ $payment['invoice_total'] }}</p>
                </div>

                {{-- KECOCOKAN NOMINAL (dihitung dari data) --}}
                @if ($payment['nominal_matches'])
                    <div class="admin-alert-box alert-success-box">
                        <strong class="admin-alert-title text-green">Nominal cocok dengan invoice</strong>
                        <p class="admin-alert-desc text-green">Tetap periksa bukti dan kecocokan tujuan menggunakan data resmi studio.</p>
                    </div>
                @else
                    <div class="admin-alert-box alert-warning-box">
                        <strong class="admin-alert-title text-gold">Nominal tidak sama dengan invoice</strong>
                        <p class="admin-alert-desc text-gold">Bukti {{ $payment['nominal'] }} · Invoice {{ $payment['invoice_total'] }}. Periksa kembali sebelum memverifikasi.</p>
                    </div>
                @endif

                @if ($payment['customer_message'])
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Pesan perbaikan ke pelanggan</label>
                        <p class="admin-meta-val">{{ $payment['customer_message'] }}</p>
                    </div>
                @endif

                @if ($payment['can_review'])
                    <button type="button" class="admin-btn-primary w-full" id="btn-open-verify-modal">Verifikasi pembayaran</button>
                    <button type="button" class="admin-btn-outline w-full" id="btn-open-request-fix-modal">Minta perbaikan bukti</button>
                @else
                    <div class="admin-alert-box alert-success-box">
                        <strong class="admin-alert-title text-green">Pembayaran sudah terverifikasi</strong>
                        <p class="admin-alert-desc text-green">Konfirmasi jadwal dilakukan terpisah di halaman Pesanan.</p>
                    </div>
                    <a href="{{ route('admin.pesanan.detail', ['id' => $payment['order_code']]) }}" class="admin-btn-outline w-full text-center">Buka pesanan terkait →</a>
                @endif

                <button type="button" class="admin-btn-outline w-full" id="btn-open-edit-note-modal">Edit catatan</button>
            </div>

            {{-- PANEL RIWAYAT TINJAUAN (FIGMA 141:1859) --}}
            <div class="admin-panel">
                <h2 class="admin-panel-title">Riwayat tinjauan</h2>

                <div class="admin-meta-field">
                    <span class="status-indicator-item text-muted">● {{ $payment['decision'] }}</span>
                </div>

                <div class="admin-review-history-steps">
                    @foreach ($payment['history'] as $line)
                        <p class="review-step-text">{{ $line }}</p>
                    @endforeach
                    @if ($payment['internal_note'])
                        <p class="review-step-text">Catatan admin: {{ $payment['internal_note'] }}</p>
                    @endif
                </div>

                <hr class="divider">

                <p class="admin-meta-disclaimer">
                    Keputusan verifikasi, permintaan perbaikan, dan catatan admin akan ditambahkan ke audit, bukan menimpa bukti asli.
                </p>
            </div>
        </div>
    </div>

    {{-- MODAL DIALOG VERIFIKASI PEMBAYARAN (FIGMA 141:1867) --}}
    <div class="admin-modal-overlay {{ request('modal') === 'verifikasi' ? 'is-open' : '' }}" id="modal-verify">
        <div class="admin-modal-dialog">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Verifikasi pembayaran?</h3>
                <button type="button" class="admin-btn-close-modal" data-close="modal-verify">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#253247" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <p class="admin-modal-subtitle">{{ $payment['sender'] }} · {{ $payment['id'] }}</p>

            <hr class="divider">

            <div class="admin-meta-field">
                <label class="admin-meta-label">Invoice / pesanan</label>
                <p class="admin-meta-val font-mono">{{ $payment['invoice'] }} / {{ $payment['order_code'] }}</p>
            </div>

            <div class="admin-meta-field">
                <label class="admin-meta-label">Nominal bukti / invoice</label>
                <p class="admin-meta-val">{{ $payment['nominal'] }} / {{ $payment['nominal'] }} · Cocok</p>
            </div>

            <form action="{{ route('admin.pembayaran.verifikasi-action', ['id' => $payment['id']]) }}" method="POST" id="form-verify-payment">
                @csrf
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Nominal bukti / invoice</label>
                    <p class="admin-meta-val">{{ $payment['nominal'] }} / {{ $payment['invoice_total'] }} · {{ $payment['nominal_matches'] ? 'Cocok' : 'Tidak sama' }}</p>
                </div>

                <label class="admin-checkbox-label">
                    <input type="checkbox" name="konfirmasi" value="1" required>
                    <span>Saya telah meninjau nominal, pengirim, bukti, dan kecocokan referensi invoice menggunakan data resmi studio.</span>
                </label>

                <div class="admin-meta-field">
                    <label class="admin-meta-label">Catatan internal (opsional)</label>
                    <input type="text" name="catatan" class="admin-form-input" maxlength="500" placeholder="Contoh: Sesuai mutasi rekening studio.">
                </div>
            </form>

            <div class="admin-alert-box alert-warning-box">
                <strong class="admin-alert-title text-gold">Pembayaran terverifikasi ≠ jadwal dikonfirmasi</strong>
                <p class="admin-alert-desc text-gold">
                    Setelah disimpan, pembayaran menjadi terverifikasi; jadwal masih memerlukan konfirmasi terpisah di Pesanan. Riwayat keuangan tidak dapat dihapus.
                </p>
            </div>

            <hr class="divider">

            <div class="admin-modal-actions">
                <button type="button" class="admin-btn-outline" data-close="modal-verify">Batal</button>
                <button type="submit" form="form-verify-payment" class="admin-btn-primary">Ya, verifikasi pembayaran</button>
            </div>
        </div>
    </div>

    {{-- MODAL DIALOG MINTA PERBAIKAN BUKTI (FIGMA 141:2007) --}}
    <div class="admin-modal-overlay" id="modal-request-fix">
        <div class="admin-modal-dialog">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Minta perbaikan bukti?</h3>
                <button type="button" class="admin-btn-close-modal" data-close="modal-request-fix">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#253247" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <p class="admin-modal-subtitle">{{ $payment['sender'] }} · {{ $payment['id'] }}</p>

            <hr class="divider">

            <form action="{{ route('admin.pembayaran.minta-perbaikan', ['id' => $payment['id']]) }}" method="POST" id="form-request-fix">
                @csrf
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Alasan perbaikan *</label>
                    <select name="alasan" class="admin-form-input" required>
                        <option>Nominal atau nomor rekening tujuan tidak terbaca jelas</option>
                        <option>Nominal transfer tidak sesuai dengan total invoice</option>
                        <option>Struk tidak memuat waktu atau referensi bank yang valid</option>
                        <option>Berkas bukti tidak dapat dibuka</option>
                        <option>Lainnya (tulis di pesan)</option>
                    </select>
                </div>

                <div class="admin-meta-field">
                    <label class="admin-meta-label">Pesan instruksi untuk pelanggan *</label>
                    <textarea name="pesan" class="admin-form-input" rows="3" required minlength="5" maxlength="500">{{ old('pesan', 'Mohon unggah kembali bukti transfer yang menampilkan tanggal, nama penerima, dan nominal secara lengkap.') }}</textarea>
                </div>
            </form>

            <p class="admin-meta-disclaimer">
                Status pembayaran pelanggan akan berubah menjadi “Perlu perbaikan bukti”. Pelanggan dapat mengunggah struk baru tanpa membuat pesanan ulang.
            </p>

            <hr class="divider">

            <div class="admin-modal-actions">
                <button type="button" class="admin-btn-outline" data-close="modal-request-fix">Batal</button>
                <button type="submit" form="form-request-fix" class="admin-btn-primary" style="background:#94671E; border-color:#94671E;">Kirim permintaan perbaikan</button>
            </div>
        </div>
    </div>

    {{-- MODAL DIALOG EDIT CATATAN (FIGMA 141:2336) --}}
    <div class="admin-modal-overlay" id="modal-edit-note">
        <div class="admin-modal-dialog">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Edit catatan pembayaran</h3>
                <button type="button" class="admin-btn-close-modal" data-close="modal-edit-note">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#253247" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <p class="admin-modal-subtitle">{{ $payment['sender'] }} · {{ $payment['id'] }}</p>

            <hr class="divider">

            <form action="{{ route('admin.pembayaran.catatan', ['id' => $payment['id']]) }}" method="POST" id="form-edit-note">
                @csrf
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Catatan internal admin</label>
                    <textarea name="catatan" class="admin-form-input" rows="4" maxlength="1000" placeholder="Tulis catatan untuk tim admin.">{{ old('catatan', $payment['internal_note']) }}</textarea>
                </div>
            </form>

            <span class="admin-meta-hint">
                Catatan ini hanya dapat dilihat oleh tim admin dan tidak tampil ke pelanggan.
            </span>

            <hr class="divider">

            <div class="admin-modal-actions">
                <button type="button" class="admin-btn-outline" data-close="modal-edit-note">Batal</button>
                <button type="submit" form="form-edit-note" class="admin-btn-primary">Simpan catatan</button>
            </div>
        </div>
    </div>

    {{-- FOOTER COPYRIGHT (FIGMA 141:1865) --}}
    <div class="admin-footer-copyright">
        © {{ date('Y') }} Naro Capture · Akses terbatas admin
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            @if ($errors->has('konfirmasi'))
                document.getElementById('modal-verify')?.classList.add('is-open');
            @endif
            @if ($errors->has('alasan') || $errors->has('pesan'))
                document.getElementById('modal-request-fix')?.classList.add('is-open');
            @endif
            const bindModal = (btnId, modalId) => {
                const btn = document.getElementById(btnId);
                const modal = document.getElementById(modalId);
                if (btn && modal) {
                    btn.addEventListener('click', () => modal.classList.add('is-open'));
                }
            };

            bindModal('btn-open-verify-modal', 'modal-verify');
            bindModal('btn-open-request-fix-modal', 'modal-request-fix');
            bindModal('btn-open-edit-note-modal', 'modal-edit-note');

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

