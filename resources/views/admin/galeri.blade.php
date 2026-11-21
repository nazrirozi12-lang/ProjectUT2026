@extends('layouts.admin')
@section('title', 'Galeri Pelanggan — Naro Capture')
@section('breadcrumb', 'Galeri / Galeri pelanggan')
@section('content')
    <div class="admin-page-header">
        <div class="admin-header-text">
            <h1 class="admin-page-title">Galeri pelanggan</h1>
            <p class="admin-page-subtitle">Kelola hasil sesi: buat galeri dari pesanan yang sudah dikonfirmasi, pasang tautan Google Drive, lalu terbitkan.</p>
        </div>
        <button type="button" class="admin-btn-primary" id="btn-open-create-gallery">Buat galeri</button>
    </div>

    {{-- RINGKASAN STATUS (nyata) --}}
    <div class="admin-status-summary-bar">
        <span class="status-indicator-item text-blue">● {{ $counts['penyuntingan'] }} dalam penyuntingan</span>
        <span class="status-indicator-item text-muted">● {{ $counts['draf'] }} draf</span>
        <span class="status-indicator-item text-green">● {{ $counts['siap'] }} siap · {{ $counts['diterbitkan'] }} diterbitkan</span>
    </div>

    <div class="admin-panel admin-orders-panel">
        @php
            $tabDefs = ['semua' => 'Semua galeri', 'draf' => 'Draf', 'penyuntingan' => 'Penyuntingan', 'siap' => 'Siap', 'diterbitkan' => 'Diterbitkan'];
        @endphp
        <div class="admin-tabs">
            @foreach ($tabDefs as $key => $label)
                <a href="{{ route('admin.galeri', array_filter(['tab' => $key, 'q' => $search, 'bulan' => $period])) }}" class="admin-tab-item {{ $activeTab === $key ? 'is-active' : '' }}">
                    {{ $label }} ({{ $counts[$key] }})
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.galeri') }}" class="admin-filter-bar">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <div class="admin-search-input-wrap">
                <input type="text" name="q" placeholder="Cari judul, pelanggan, atau kode pesanan (Enter)" class="admin-search-input" value="{{ $search }}">
            </div>
            <select name="bulan" class="admin-select-dropdown" onchange="this.form.submit()">
                <option value="">Semua periode</option>
                @foreach ($periods as $m)
                    <option value="{{ $m['value'] }}" @selected($period === $m['value'])>{{ $m['label'] }}</option>
                @endforeach
            </select>
            @if ($search || $period)
                <a href="{{ route('admin.galeri', ['tab' => $activeTab]) }}" class="admin-btn-outline btn-sm">Reset filter</a>
            @endif
        </form>

        <div class="admin-table-container">
            <table class="admin-orders-table">
                <thead>
                    <tr>
                        <th style="width: 26%;">Galeri</th>
                        <th style="width: 24%;">Pesanan / pemilik</th>
                        <th style="width: 18%;">Foto / target</th>
                        <th style="width: 14%;">Status</th>
                        <th style="width: 10%;">Akses</th>
                        <th style="width: 8%; text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($galleries as $g)
                        <tr>
                            <td><strong class="order-customer-name">{{ $g['title'] }}</strong></td>
                            <td>
                                <div class="table-cell-order">
                                    <span class="order-code-mono">{{ $g['order_code'] }}</span>
                                    <span class="schedule-time-text">{{ $g['owner'] }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="table-cell-package">
                                    <span class="order-code-mono">{{ $g['photos'] }}</span>
                                    <span class="schedule-time-text">{{ $g['note'] }}</span>
                                </div>
                            </td>
                            <td><span class="badge-status-session status-{{ Str::slug($g['status']) }}">{{ $g['status'] }}</span></td>
                            <td><span class="schedule-date-text">{{ $g['access'] }}</span></td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="{{ route('admin.galeri.lihat', ['id' => $g['id']]) }}" class="admin-table-action-link">Lihat →</a>
                                <button type="button" class="admin-table-action-link js-edit-gallery" style="background:none; border:0; cursor:pointer;"
                                    data-id="{{ $g['id'] }}" data-title="{{ $g['title'] }}" data-status="{{ $g['status'] }}" data-access="{{ $g['access'] }}"
                                    data-target="{{ $g['target'] }}" data-count="{{ $g['photo_count'] }}" data-drive="{{ $g['drive_url'] }}"
                                    data-notes="{{ $g['notes'] }}" data-pin="{{ $g['pin'] }}" data-order="{{ $g['order_label'] }}">Kelola</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center; padding:40px 0; color:#6E7A8B;">
                                Belum ada galeri pada tab atau filter ini. Klik “Buat galeri” untuk memulai.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('admin.partials.pagination', ['paginator' => $paginator, 'label' => 'galeri'])
    </div>

    <p class="admin-table-footer-hint">
        Unggah foto lewat tombol “Lihat” (foto dikecilkan otomatis), dan/atau pasang tautan Google Drive. Galeri “Diterbitkan” wajib punya minimal satu foto atau tautan Drive.
    </p>

    {{-- MODAL BUAT GALERI --}}
    <div class="admin-modal-overlay" id="modal-create-gallery">
        <div class="admin-modal-dialog">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Buat galeri pelanggan</h3>
                <button type="button" class="admin-btn-close-modal" data-close="modal-create-gallery">×</button>
            </div>
            <p class="admin-modal-subtitle">Galeri dibuat dari pesanan yang sesinya sudah dikonfirmasi atau selesai</p>
            <hr class="divider">
            @if ($orders->isEmpty())
                <div class="admin-alert-box alert-warning-box">
                    <strong class="admin-alert-title text-gold">Belum ada pesanan yang memenuhi syarat</strong>
                    <p class="admin-alert-desc text-gold">Konfirmasi sesi sebuah pesanan terlebih dahulu di halaman Pesanan. Pesanan yang sudah punya galeri tidak ditampilkan.</p>
                </div>
            @endif
            <form action="{{ route('admin.galeri.simpan') }}" method="POST" id="form-create-gallery">
                @csrf
                <div class="admin-meta-row-2">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Judul galeri *</label>
                        <input type="text" name="title" class="admin-form-input" placeholder="Contoh: Wisuda Sinta" required maxlength="150" value="{{ old('title') }}">
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Pesanan terkait *</label>
                        <select name="order_id" class="admin-form-input" required>
                            <option value="">— Pilih pesanan —</option>
                            @foreach ($orders as $o)
                                <option value="{{ $o->id }}" @selected((string) old('order_id') === (string) $o->id)>{{ $o->order_code }} · {{ $o->customer_name }} · {{ $o->package_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="admin-meta-row-2">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Target jumlah foto</label>
                        <input type="number" name="target_photos" class="admin-form-input" min="1" max="1000" value="{{ old('target_photos', 30) }}">
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Tipe akses</label>
                        <select name="access_type" class="admin-form-input">
                            @foreach ($accessTypes as $a)
                                <option value="{{ $a }}" @selected(old('access_type', 'Privat') === $a)>{{ $a === 'Privat' ? 'Privat (PIN)' : 'Terbuka (portofolio)' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>
            <hr class="divider">
            <div class="admin-modal-actions">
                <button type="button" class="admin-btn-outline" data-close="modal-create-gallery">Batal</button>
                <button type="submit" form="form-create-gallery" class="admin-btn-primary" @disabled($orders->isEmpty())>Buat galeri draf</button>
            </div>
        </div>
    </div>

    {{-- MODAL KELOLA GALERI --}}
    <div class="admin-modal-overlay" id="modal-edit-gallery">
        <div class="admin-modal-dialog">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Kelola galeri</h3>
                <button type="button" class="admin-btn-close-modal" data-close="modal-edit-gallery">×</button>
            </div>
            <p class="admin-modal-subtitle" id="edit-gallery-order"></p>
            <hr class="divider">
            <form method="POST" id="form-edit-gallery">
                @csrf
                @method('PUT')
                <div class="admin-meta-row-2">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Judul galeri *</label>
                        <input type="text" name="title" id="eg-title" class="admin-form-input" required maxlength="150">
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Status *</label>
                        <select name="status" id="eg-status" class="admin-form-input">
                            @foreach ($statuses as $st)<option value="{{ $st }}">{{ $st }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Tautan Google Drive</label>
                    <input type="url" name="drive_url" id="eg-drive" class="admin-form-input" placeholder="https://drive.google.com/..." maxlength="500">
                </div>
                <div class="admin-meta-row-2">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Foto sudah diunggah</label>
                        <input type="number" name="photo_count" id="eg-count" class="admin-form-input" min="0" value="0">
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Target jumlah foto</label>
                        <input type="number" name="target_photos" id="eg-target" class="admin-form-input" min="1" value="30">
                    </div>
                </div>
                <div class="admin-meta-row-2">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Tipe akses</label>
                        <select name="access_type" id="eg-access" class="admin-form-input">
                            @foreach ($accessTypes as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach
                        </select>
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">PIN akses</label>
                        <p class="admin-meta-val font-mono" id="eg-pin">—</p>
                        <label style="font-size:12px;"><input type="checkbox" name="reset_pin" value="1"> Buat PIN baru</label>
                    </div>
                </div>
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Catatan internal</label>
                    <textarea name="notes" id="eg-notes" class="admin-form-input" rows="2" maxlength="1000"></textarea>
                </div>
            </form>
            <hr class="divider">
            <div class="admin-modal-actions">
                <button type="button" class="admin-btn-outline btn-outline-danger" id="btn-delete-gallery" style="margin-right:auto;">Hapus galeri</button>
                <button type="button" class="admin-btn-outline" data-close="modal-edit-gallery">Batal</button>
                <button type="submit" form="form-edit-gallery" class="admin-btn-primary">Simpan perubahan</button>
            </div>
        </div>
    </div>

    {{-- MODAL HAPUS GALERI --}}
    <div class="admin-modal-overlay" id="modal-delete-gallery">
        <div class="admin-modal-dialog">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Hapus galeri?</h3>
                <button type="button" class="admin-btn-close-modal" data-close="modal-delete-gallery">×</button>
            </div>
            <p class="admin-modal-subtitle" id="delete-gallery-label"></p>
            <hr class="divider">
            <p class="admin-meta-val">Galeri akan dihapus dari daftar. Pesanan terkait tidak terpengaruh, dan Anda dapat membuat galeri baru.</p>
            <hr class="divider">
            <form method="POST" id="form-delete-gallery" class="admin-modal-actions">
                @csrf
                @method('DELETE')
                <button type="button" class="admin-btn-outline" data-close="modal-delete-gallery">Batal</button>
                <button type="submit" class="admin-btn-primary" style="background:#D32F2F; border-color:#D32F2F;">Hapus galeri</button>
            </form>
        </div>
    </div>

    <div class="admin-footer-copyright">© {{ date('Y') }} Naro Capture · Akses terbatas admin</div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const open = id => document.getElementById(id).classList.add('is-open');
            const close = id => document.getElementById(id).classList.remove('is-open');
            const base = @json(url('/admin/galeri'));
            let current = null;

            document.getElementById('btn-open-create-gallery').addEventListener('click', () => open('modal-create-gallery'));
            document.querySelectorAll('.js-edit-gallery').forEach(b => b.addEventListener('click', () => {
                current = b.dataset;
                document.getElementById('form-edit-gallery').action = base + '/' + b.dataset.id;
                document.getElementById('edit-gallery-order').textContent = (b.dataset.order || '') + ' · ' + b.dataset.title;
                document.getElementById('eg-title').value = b.dataset.title;
                document.getElementById('eg-status').value = b.dataset.status;
                document.getElementById('eg-drive').value = b.dataset.drive || '';
                document.getElementById('eg-count').value = b.dataset.count;
                document.getElementById('eg-target').value = b.dataset.target;
                document.getElementById('eg-access').value = b.dataset.access;
                document.getElementById('eg-notes').value = b.dataset.notes || '';
                document.getElementById('eg-pin').textContent = b.dataset.pin || '—';
                open('modal-edit-gallery');
            }));
            document.getElementById('btn-delete-gallery').addEventListener('click', () => {
                if (!current) return;
                document.getElementById('form-delete-gallery').action = base + '/' + current.id;
                document.getElementById('delete-gallery-label').textContent = current.title;
                close('modal-edit-gallery');
                open('modal-delete-gallery');
            });
            document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => close(b.getAttribute('data-close'))));

            @if ($errors->has('title') || $errors->has('order_id') || $errors->has('target_photos') || $errors->has('access_type'))
                @if (!old('_method'))
                    open('modal-create-gallery');
                @endif
            @endif
        });
    </script>
@endsection
