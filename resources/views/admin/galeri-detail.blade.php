@extends('layouts.admin')
@section('title', 'Galeri ' . $gallery->title . ' — Naro Capture')
@section('breadcrumb', 'Galeri / ' . $gallery->title)
@section('content')
    <div class="admin-page-header">
        <div class="admin-header-text">
            <h1 class="admin-page-title">{{ $gallery->title }}</h1>
            <p class="admin-page-subtitle">
                {{ $gallery->client_name }}{{ $gallery->order ? ' · ' . $gallery->order->order_code : '' }} ·
                {{ $gallery->status }} · Akses {{ $gallery->access_type }}
            </p>
        </div>
        <a href="{{ route('admin.galeri', ['tab' => \Illuminate\Support\Str::lower($gallery->status)]) }}" class="admin-btn-outline">← Kembali ke daftar galeri</a>
    </div>

    <div class="admin-order-detail-grid">
        <div class="admin-order-detail-left">
            <div class="admin-panel">
                <h2 class="admin-panel-title">Foto galeri ({{ $photos->count() }})</h2>

                {{-- Unggah foto: dikecilkan di browser, dikirim satu per satu --}}
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Tambah foto (JPG/PNG/WEBP — otomatis dikecilkan agar ringan)</label>
                    <input type="file" id="photo-input" class="admin-form-input" accept="image/jpeg,image/png,image/webp" multiple>
                    <div id="upload-status" class="admin-meta-hint" style="display:none;"></div>
                    <div id="upload-bar" style="display:none; height:6px; background:#E6E8E1; border-radius:3px; overflow:hidden;"><div id="upload-bar-fill" style="height:100%; width:0; background:#3B5B9A; transition:width .2s;"></div></div>
                </div>

                @if ($photos->isEmpty())
                    <div class="admin-doc-preview-box">
                        <strong class="doc-preview-title">Belum ada foto di galeri ini</strong>
                        <p class="doc-preview-desc">Pilih foto di atas untuk mengunggah. Foto pertama otomatis menjadi sampul; Anda dapat mengganti sampul kapan saja.</p>
                    </div>
                @else
                    <div class="admin-gallery-grid">
                        @foreach ($photos as $i => $photo)
                            <div class="admin-gallery-item">
                                <a href="{{ route('admin.galeri.foto', ['id' => $gallery->id, 'photo' => $photo->id, 'variant' => 'full']) }}" target="_blank" rel="noopener">
                                    <img src="{{ route('admin.galeri.foto', ['id' => $gallery->id, 'photo' => $photo->id]) }}" alt="Foto {{ $i + 1 }}" loading="lazy">
                                </a>
                                <div class="admin-gallery-item__meta">
                                    <span title="{{ $photo->file_name }}">{{ $i === 0 ? 'Sampul' : 'Foto ' . ($i + 1) }}</span>
                                    <span style="display:flex; gap:8px;">
                                        @if ($i > 0)
                                            <form action="{{ route('admin.galeri.foto.sampul', ['id' => $gallery->id, 'photo' => $photo->id]) }}" method="POST">@csrf<button type="submit" class="admin-table-action-link" style="background:none;border:0;cursor:pointer;">Sampul</button></form>
                                        @endif
                                        <form action="{{ route('admin.galeri.foto.hapus', ['id' => $gallery->id, 'photo' => $photo->id]) }}" method="POST" onsubmit="return confirm('Hapus foto ini dari galeri?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="admin-table-action-link" style="background:none;border:0;cursor:pointer;color:#D32F2F;">Hapus</button>
                                        </form>
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="admin-order-detail-right">
            <div class="admin-panel">
                <h2 class="admin-panel-title">Akses pelanggan</h2>
                <div class="admin-meta-field"><label class="admin-meta-label">Status</label><p class="admin-meta-val">{{ $gallery->status }}{{ $gallery->status === 'Diterbitkan' ? ' · terlihat pelanggan' : ' · belum terlihat pelanggan' }}</p></div>
                <div class="admin-meta-field"><label class="admin-meta-label">Tipe akses</label><p class="admin-meta-val">{{ $gallery->access_type === 'Privat' ? 'Privat — wajib PIN' : 'Terbuka — tampil di Karya Terbaru' }}</p></div>
                @if ($gallery->access_type === 'Privat')
                    <div class="admin-meta-field"><label class="admin-meta-label">PIN akses</label><p class="admin-meta-val font-mono">{{ $gallery->pin_code ?: '—' }}</p></div>
                @endif
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Tautan galeri</label>
                    <input type="text" class="admin-form-input font-mono" readonly value="{{ $shareUrl }}" onclick="this.select()">
                    <span class="admin-meta-hint">Pelanggan juga menemukan galeri dari halaman Cek Pesanan setelah galeri diterbitkan.</span>
                </div>
                @if ($gallery->drive_url)
                    <a href="{{ $gallery->drive_url }}" target="_blank" rel="noopener" class="admin-btn-outline w-full text-center">Buka Google Drive ↗</a>
                @endif
                @if ($gallery->status === 'Diterbitkan')
                    <a href="{{ $shareUrl }}" target="_blank" rel="noopener" class="admin-btn-primary w-full text-center">Lihat sebagai pelanggan ↗</a>
                @else
                    <span class="admin-meta-hint">Ubah status menjadi “Diterbitkan” lewat tombol Kelola di daftar galeri agar pelanggan dapat membukanya.</span>
                @endif
            </div>
        </div>
    </div>

    <div class="admin-footer-copyright">© {{ date('Y') }} Naro Capture · Akses terbatas admin</div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const input = document.getElementById('photo-input');
            const statusEl = document.getElementById('upload-status');
            const bar = document.getElementById('upload-bar');
            const fill = document.getElementById('upload-bar-fill');
            const url = @json(route('admin.galeri.foto.unggah', ['id' => $gallery->id]));
            const token = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
            const MAX_FULL = 1800, MAX_THUMB = 640;

            const load = file => new Promise((res, rej) => {
                const img = new Image(); const u = URL.createObjectURL(file);
                img.onload = () => { URL.revokeObjectURL(u); res(img); };
                img.onerror = () => rej(new Error('File bukan gambar yang valid'));
                img.src = u;
            });
            const shrink = (img, max, quality) => new Promise(res => {
                const r = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
                const c = document.createElement('canvas');
                c.width = Math.round(img.naturalWidth * r); c.height = Math.round(img.naturalHeight * r);
                c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                c.toBlob(b => res(b), 'image/jpeg', quality);
            });

            input.addEventListener('change', async () => {
                const files = Array.from(input.files);
                if (!files.length) return;
                statusEl.style.display = 'block'; bar.style.display = 'block';
                let ok = 0, fail = 0, lastError = '';
                for (let i = 0; i < files.length; i++) {
                    statusEl.textContent = `Mengunggah ${i + 1} dari ${files.length}… (${files[i].name})`;
                    try {
                        const img = await load(files[i]);
                        const full = await shrink(img, MAX_FULL, 0.86);
                        const thumb = await shrink(img, MAX_THUMB, 0.78);
                        const fd = new FormData();
                        fd.append('foto', full, files[i].name.replace(/\.[^.]+$/, '') + '.jpg');
                        fd.append('thumb', thumb, 'thumb.jpg');
                        const resp = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }, body: fd, credentials: 'same-origin' });
                        const json = await resp.json().catch(() => ({}));
                        if (!resp.ok || !json.ok) throw new Error(json.message || (json.errors ? Object.values(json.errors)[0][0] : 'Gagal mengunggah'));
                        ok++;
                    } catch (e) { fail++; lastError = e.message; }
                    fill.style.width = Math.round(((i + 1) / files.length) * 100) + '%';
                }
                statusEl.textContent = `${ok} foto berhasil diunggah` + (fail ? `, ${fail} gagal (${lastError})` : '') + '. Memuat ulang…';
                setTimeout(() => location.reload(), fail ? 2500 : 700);
            });
        });
    </script>
@endsection
