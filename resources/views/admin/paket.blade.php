@extends('layouts.admin')
@section('title', 'Paket Layanan — Naro Capture')
@section('breadcrumb', 'Paket Layanan / Paket layanan')
@section('content')
    <div class="admin-page-header">
        <div class="admin-header-text">
            <h1 class="admin-page-title">Paket layanan</h1>
            <p class="admin-page-subtitle">{{ $counts['aktif'] }} paket aktif{{ $counts['nonaktif'] ? ' · ' . $counts['nonaktif'] . ' nonaktif' : '' }} · Perubahan langsung tampil di halaman pelanggan.</p>
        </div>
        <button type="button" class="admin-btn-primary" id="btn-open-add-package">Tambah paket</button>
    </div>

    <form method="GET" action="{{ route('admin.paket') }}" class="admin-filter-bar">
        <div class="admin-search-input-wrap">
            <input type="text" name="q" placeholder="Cari paket layanan (Enter)" class="admin-search-input" value="{{ $search }}">
        </div>
        <select name="status" class="admin-select-dropdown" onchange="this.form.submit()">
            <option value="semua" @selected($status === 'semua')>Semua paket</option>
            <option value="aktif" @selected($status === 'aktif')>Paket aktif</option>
            <option value="nonaktif" @selected($status === 'nonaktif')>Paket nonaktif</option>
        </select>
    </form>

    <div class="admin-packages-cards-grid">
        @forelse ($packages as $p)
            <div class="admin-panel admin-package-mgmt-card">
                <img src="{{ \App\Support\Media::pkg($p['image']) }}" alt="{{ $p['name'] }}" class="admin-package-mgmt-img">
                <div class="admin-package-mgmt-body">
                    <div class="admin-package-card-header">
                        <span class="status-indicator-item {{ $p['active'] ? 'text-green' : 'text-muted' }}">● {{ $p['active'] ? 'Aktif' : 'Nonaktif' }}</span>
                        <span class="order-code-mono text-muted">{{ $p['code'] }}</span>
                    </div>
                    <h2 class="admin-package-mgmt-title">{{ $p['name'] }}</h2>
                    @if ($p['description'])<p class="admin-package-mgmt-desc">{{ $p['description'] }}</p>@endif
                    <strong class="admin-package-mgmt-price">{{ $p['price_label'] }}</strong>
                    <span class="admin-package-mgmt-meta">{{ $p['duration'] }}{{ $p['photo_label'] ? ' · ' . $p['photo_label'] : '' }} · {{ $p['orders_count'] }} pesanan</span>
                    <hr class="divider">
                    <ul class="features-checklist">
                        @foreach ($p['features'] as $f)
                            <li><span class="check-mark">✓</span> {{ $f }}</li>
                        @endforeach
                    </ul>
                    @if ($p['tagline'])<p class="admin-meta-hint">{{ $p['tagline'] }}</p>@endif
                    <hr class="divider">
                    <div class="admin-btn-group-2">
                        <button type="button" class="admin-btn-outline flex-1 js-edit-package" data-package='@json($p)'>Edit paket</button>
                        <form action="{{ route('admin.paket.toggle', ['id' => $p['id']]) }}" method="POST" class="flex-1" style="display:flex;">
                            @csrf
                            <button type="submit" class="admin-btn-outline" style="flex:1;">{{ $p['active'] ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                        </form>
                    </div>
                    <button type="button" class="admin-btn-outline btn-outline-danger w-full js-delete-package" style="margin-top:8px;" data-id="{{ $p['id'] }}" data-name="{{ $p['name'] }}" data-orders="{{ $p['orders_count'] }}">Hapus paket</button>
                </div>
            </div>
        @empty
            <div class="admin-panel" style="grid-column: 1 / -1; text-align:center; padding:40px 0; color:#6E7A8B;">Tidak ada paket yang cocok.</div>
        @endforelse
    </div>

    <p class="admin-table-footer-hint">
        Paket aktif tampil di beranda dan halaman pemesanan. Bila lebih dari 3 paket aktif, kartu di beranda dapat digeser.
        Paket yang sudah dipakai pesanan tidak dapat dihapus, cukup dinonaktifkan.
    </p>

    {{-- MODAL TAMBAH / EDIT PAKET --}}
    <div class="admin-modal-overlay" id="modal-package">
        <div class="admin-modal-dialog" style="max-height:90vh; overflow-y:auto;">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title" id="pkg-modal-title">Tambah paket</h3>
                <button type="button" class="admin-btn-close-modal" data-close="modal-package">×</button>
            </div>
            <hr class="divider">
            <form action="{{ route('admin.paket.simpan') }}" method="POST" enctype="multipart/form-data" id="form-package">
                @csrf
                <input type="hidden" name="_method" value="POST" id="pkg-method">
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Nama paket *</label>
                    <input type="text" name="name" id="pkg-name" class="admin-form-input" required maxlength="100" value="{{ old('name') }}">
                </div>
                <div class="admin-meta-row-2">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Harga (Rp) *</label>
                        <input type="text" name="price" id="pkg-price" class="admin-form-input" inputmode="numeric" required placeholder="Contoh: 2000000" value="{{ old('price') }}">
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Durasi sesi (menit) *</label>
                        <input type="number" name="duration_minutes" id="pkg-duration" class="admin-form-input" min="15" max="1440" required value="{{ old('duration_minutes', 60) }}">
                    </div>
                </div>
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Fasilitas * (satu baris = satu poin)</label>
                    <textarea name="features" id="pkg-features" class="admin-form-input" rows="6" required placeholder="Durasi Foto: 2 Jam&#10;30 Edited Photo&#10;All Photo via Google Drive">{{ old('features') }}</textarea>
                </div>
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Catatan di bawah fasilitas</label>
                    <input type="text" name="tagline" id="pkg-tagline" class="admin-form-input" maxlength="150" placeholder="Contoh: Di luar transport" value="{{ old('tagline') }}">
                </div>
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Deskripsi singkat (opsional)</label>
                    <textarea name="description" id="pkg-description" class="admin-form-input" rows="2" maxlength="600">{{ old('description') }}</textarea>
                </div>
                <div class="admin-meta-row-2">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Ringkasan foto (opsional)</label>
                        <input type="text" name="photo_count_label" id="pkg-photolabel" class="admin-form-input" maxlength="100" placeholder="30 edited photo" value="{{ old('photo_count_label') }}">
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Lencana (opsional)</label>
                        <input type="text" name="badge" id="pkg-badge" class="admin-form-input" maxlength="50" placeholder="Populer" value="{{ old('badge') }}">
                    </div>
                </div>
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Foto kartu paket (JPG/PNG/WEBP, maks. 2 MB)</label>
                    <input type="file" name="image" class="admin-form-input" accept="image/jpeg,image/png,image/webp">
                    <label style="font-size:12px; display:none;" id="pkg-remove-wrap"><input type="checkbox" name="remove_image" value="1"> Hapus foto unggahan (kembali ke foto bawaan)</label>
                </div>
                <div style="display:flex; gap:20px; flex-wrap:wrap;">
                    <label style="font-size:13px;"><input type="checkbox" name="is_active" id="pkg-active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}> Aktif (tampil ke pelanggan)</label>
                    <label style="font-size:13px;"><input type="checkbox" name="is_featured" id="pkg-featured" value="1" {{ old('is_featured') ? 'checked' : '' }}> Tandai “Pilihan terpopuler”</label>
                </div>
            </form>
            <hr class="divider">
            <div class="admin-modal-actions">
                <button type="button" class="admin-btn-outline" data-close="modal-package">Batal</button>
                <button type="submit" form="form-package" class="admin-btn-primary" id="pkg-submit">Simpan paket</button>
            </div>
        </div>
    </div>

    {{-- MODAL HAPUS PAKET --}}
    <div class="admin-modal-overlay" id="modal-delete-package">
        <div class="admin-modal-dialog">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Hapus paket?</h3>
                <button type="button" class="admin-btn-close-modal" data-close="modal-delete-package">×</button>
            </div>
            <p class="admin-modal-subtitle" id="delete-package-name"></p>
            <hr class="divider">
            <p class="admin-meta-val" id="delete-package-text">Paket akan dihapus permanen dari daftar.</p>
            <hr class="divider">
            <form method="POST" id="form-delete-package" class="admin-modal-actions">
                @csrf
                @method('DELETE')
                <button type="button" class="admin-btn-outline" data-close="modal-delete-package">Batal</button>
                <button type="submit" class="admin-btn-primary" style="background:#D32F2F; border-color:#D32F2F;">Hapus paket</button>
            </form>
        </div>
    </div>

    <div class="admin-footer-copyright">© {{ date('Y') }} Naro Capture · Akses terbatas admin</div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const open = id => document.getElementById(id).classList.add('is-open');
            const close = id => document.getElementById(id).classList.remove('is-open');
            const base = @json(url('/admin/paket'));
            const form = document.getElementById('form-package');
            const $ = id => document.getElementById(id);

            const setForm = (p) => {
                if (p) {
                    $('pkg-modal-title').textContent = 'Edit paket · ' + p.name;
                    form.action = base + '/' + p.id;
                    $('pkg-method').value = 'PUT';
                    $('pkg-name').value = p.name;
                    $('pkg-price').value = p.price;
                    $('pkg-duration').value = p.duration_minutes || 60;
                    $('pkg-features').value = (p.features || []).join('\n');
                    $('pkg-tagline').value = p.tagline || '';
                    $('pkg-description').value = p.description || '';
                    $('pkg-photolabel').value = p.photo_label || '';
                    $('pkg-badge').value = p.badge || '';
                    $('pkg-active').checked = !!p.active;
                    $('pkg-featured').checked = !!p.featured;
                    $('pkg-remove-wrap').style.display = 'block';
                    $('pkg-submit').textContent = 'Simpan perubahan';
                }
            };

            $('btn-open-add-package').addEventListener('click', () => open('modal-package'));
            document.querySelectorAll('.js-edit-package').forEach(b => b.addEventListener('click', () => {
                setForm(JSON.parse(b.dataset.package));
                open('modal-package');
            }));
            document.querySelectorAll('.js-delete-package').forEach(b => b.addEventListener('click', () => {
                $('form-delete-package').action = base + '/' + b.dataset.id;
                $('delete-package-name').textContent = b.dataset.name;
                $('delete-package-text').textContent = parseInt(b.dataset.orders, 10) > 0
                    ? 'Paket ini sudah dipakai ' + b.dataset.orders + ' pesanan, sehingga sistem akan menolak penghapusan. Gunakan “Nonaktifkan” agar paket tidak tampil ke pelanggan.'
                    : 'Paket akan dihapus permanen dari daftar.';
                open('modal-delete-package');
            }));
            document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => close(b.getAttribute('data-close'))));

            @if ($errors->any() && !session('success') && old('name') !== null)
                open('modal-package');
            @endif
        });
    </script>
@endsection
