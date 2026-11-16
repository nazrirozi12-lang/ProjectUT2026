@extends('layouts.admin')
@section('title', 'Kalender Studio — Naro Capture')
@section('breadcrumb', 'Jadwal / Kalender studio')
@section('content')
    {{-- PENGANTAR HALAMAN --}}
    <div class="admin-page-header">
        <div class="admin-header-text">
            <h1 class="admin-page-title">Kalender studio</h1>
            <p class="admin-page-subtitle">Sesi disetujui, permintaan tentatif, dan blokir studio dari data nyata. Zona waktu WIB.</p>
        </div>
        <button type="button" class="admin-btn-primary" id="btn-open-add-slot">Blokir jadwal</button>
    </div>

    {{-- FILTER & PENCARIAN (berfungsi) --}}
    <form method="GET" action="{{ route('admin.jadwal') }}" class="admin-filter-bar">
        <input type="hidden" name="tanggal" value="{{ $detail['date'] ?? '' }}">
        <div class="admin-search-input-wrap">
            <input type="text" name="q" placeholder="Cari sesi, pelanggan, atau kode pesanan (Enter)" class="admin-search-input" value="{{ $search }}">
        </div>
        <select name="jenis" class="admin-select-dropdown" onchange="this.form.submit()">
            <option value="semua" @selected($type === 'semua')>Semua jenis</option>
            <option value="approved" @selected($type === 'approved')>Sesi disetujui</option>
            <option value="tentative" @selected($type === 'tentative')>Permintaan tentatif</option>
            <option value="blocked" @selected($type === 'blocked')>Blokir studio</option>
        </select>
        <select name="bulan" class="admin-select-dropdown" onchange="this.form.submit()">
            @foreach ($monthOptions as $m)
                <option value="{{ $m['value'] }}" @selected($monthValue === $m['value'])>{{ $m['label'] }}</option>
            @endforeach
            @if (!collect($monthOptions)->contains('value', $monthValue))
                <option value="{{ $monthValue }}" selected>{{ $monthTitle }}</option>
            @endif
        </select>
    </form>

    <div class="admin-calendar-layout-grid">
        {{-- KALENDER BULAN --}}
        <div class="admin-panel admin-calendar-main-panel">
            <div class="admin-calendar-nav-bar">
                <h2 class="admin-calendar-month-heading">{{ $monthTitle }}</h2>
                <div class="admin-calendar-nav-actions">
                    <a href="{{ route('admin.jadwal', array_filter(['bulan' => $prevMonth, 'jenis' => $type !== 'semua' ? $type : null, 'q' => $search])) }}" class="admin-btn-outline btn-sm" aria-label="Bulan sebelumnya">‹</a>
                    <a href="{{ route('admin.jadwal', array_filter(['bulan' => $todayMonth, 'jenis' => $type !== 'semua' ? $type : null, 'q' => $search])) }}" class="admin-btn-outline btn-sm">Hari ini</a>
                    <a href="{{ route('admin.jadwal', array_filter(['bulan' => $nextMonth, 'jenis' => $type !== 'semua' ? $type : null, 'q' => $search])) }}" class="admin-btn-outline btn-sm" aria-label="Bulan berikutnya">›</a>
                </div>
            </div>

            <div class="admin-calendar-weekdays">
                <span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span><span>Min</span>
            </div>

            <div class="admin-calendar-month-grid">
                @foreach ($weeks as $cell)
                    <a href="{{ route('admin.jadwal', array_filter(['bulan' => $monthValue, 'tanggal' => $cell['date'], 'jenis' => $type !== 'semua' ? $type : null, 'q' => $search])) }}"
                       class="calendar-day-cell {{ $cell['muted'] ? 'is-muted' : '' }} {{ $cell['selected'] ? 'is-selected' : '' }} {{ $cell['today'] ? 'is-today' : '' }}"
                       style="text-decoration:none; color:inherit; display:block;" aria-label="{{ $cell['date'] }}">
                        <span class="day-num">{{ $cell['day'] }}</span>
                        @foreach (array_slice($cell['events'], 0, 2) as $ev)
                            <div class="cal-event-pill event-{{ $ev['kind'] }}">{{ $ev['text'] }}<br>{{ $ev['sub'] }}</div>
                        @endforeach
                        @if (count($cell['events']) > 2)
                            <div class="cal-event-more">+{{ count($cell['events']) - 2 }} lagi</div>
                        @endif
                    </a>
                @endforeach
            </div>

            <div class="admin-calendar-legend-bar">
                <span class="legend-item text-green">● Sesi disetujui</span>
                <span class="legend-item text-gold">● Permintaan tentatif</span>
                <span class="legend-item text-muted">● Blokir studio</span>
            </div>
        </div>

        {{-- DETAIL TANGGAL TERPILIH --}}
        <div class="admin-calendar-sidebar-detail">
            @if ($detail)
                <div class="admin-panel">
                    <h3 class="admin-panel-title">{{ $detail['label'] }}</h3>
                    <span class="status-indicator-item text-blue">● Tanggal dipilih{{ $detail['is_past'] ? ' · sudah lewat' : '' }}</span>

                    @forelse ($detail['orders'] as $o)
                        <div class="admin-selected-session-info" style="{{ !$loop->first ? 'margin-top:14px; padding-top:14px; border-top:1px solid #E6E8E1;' : '' }}">
                            <strong class="selected-customer-name">{{ $o['customer'] }}</strong>
                            <div class="admin-meta-field">
                                <label class="admin-meta-label">Pesanan / paket</label>
                                <p class="admin-meta-val font-mono">{{ $o['code'] }} · {{ $o['package'] }}</p>
                            </div>
                            <div class="admin-meta-field">
                                <label class="admin-meta-label">Waktu</label>
                                <p class="admin-meta-val">{{ $o['time'] }}</p>
                            </div>
                            <div class="admin-meta-field">
                                <label class="admin-meta-label">Lokasi</label>
                                <p class="admin-meta-val">{{ $o['location'] }}</p>
                            </div>
                            <span class="status-indicator-item {{ $o['status'] === 'Permintaan' ? 'text-gold' : 'text-green' }}">● {{ $o['status'] === 'Permintaan' ? 'Tentatif · Belum dikonfirmasi' : $o['status'] }} · Pembayaran: {{ $o['payment'] }}</span>
                            <a href="{{ route('admin.pesanan.detail', ['id' => $o['code']]) }}" class="admin-btn-outline w-full text-center" style="margin-top:8px;">Buka detail pesanan</a>
                        </div>
                    @empty
                        <p class="admin-meta-hint">Belum ada sesi pada tanggal ini.</p>
                    @endforelse
                </div>

                <div class="admin-panel">
                    <h3 class="admin-panel-title">Slot ketersediaan</h3>
                    @foreach ($detail['slots'] as $slot)
                        <div class="admin-meta-field">
                            <label class="admin-meta-label">Mulai {{ $slot['time'] }} WIB</label>
                            <span class="status-indicator-item {{ $slot['status'] === 'available' ? 'text-blue' : ($slot['status'] === 'full' ? 'text-gold' : 'text-muted') }}">
                                ● {{ ['available' => 'Tersedia', 'full' => 'Terpesan', 'blocked' => 'Diblokir'][$slot['status']] }}
                            </span>
                        </div>
                    @endforeach

                    @foreach ($detail['blocks'] as $b)
                        <hr class="divider">
                        <div class="admin-meta-field">
                            <label class="admin-meta-label">Blokir studio</label>
                            <p class="admin-meta-val">{{ $b['label'] }}{{ $b['note'] ? ' · ' . $b['note'] : '' }}</p>
                        </div>
                        <div class="admin-btn-group-2">
                            <button type="button" class="admin-btn-outline flex-1 js-edit-block"
                                    data-id="{{ $b['id'] }}" data-start="{{ $b['start'] }}" data-end="{{ $b['end'] }}"
                                    data-whole="{{ $b['whole_day'] ? 1 : 0 }}" data-note="{{ $b['note'] }}" data-label="{{ $detail['label'] }} · {{ $b['label'] }}">Edit</button>
                            <button type="button" class="admin-btn-outline btn-outline-danger flex-1 js-delete-block"
                                    data-id="{{ $b['id'] }}" data-label="{{ $detail['label'] }} · {{ $b['label'] }}">Hapus</button>
                        </div>
                    @endforeach

                    <hr class="divider">
                    @if (!$detail['is_past'])
                        <button type="button" class="admin-btn-outline w-full" id="btn-block-selected" data-date="{{ $detail['date'] }}">Blokir tanggal ini</button>
                    @endif
                    <p class="admin-meta-hint">Sesi yang sudah dipesan tidak dihapus oleh blokir; tinjau di halaman Pesanan.</p>
                </div>
            @else
                <div class="admin-panel">
                    <h3 class="admin-panel-title">Pilih tanggal</h3>
                    <p class="admin-meta-hint">Klik salah satu tanggal pada kalender untuk melihat sesi dan slot hari itu.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- MODAL BLOKIR JADWAL --}}
    <div class="admin-modal-overlay" id="modal-add-slot">
        <div class="admin-modal-dialog">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Blokir jadwal studio</h3>
                <button type="button" class="admin-btn-close-modal" data-close="modal-add-slot">×</button>
            </div>
            <p class="admin-modal-subtitle">Pelanggan tidak dapat memesan pada waktu yang diblokir (libur, perawatan, acara pribadi).</p>
            <hr class="divider">
            <form action="{{ route('admin.jadwal.blokir') }}" method="POST" id="form-add-block">
                @csrf
                <div class="admin-meta-row-2">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Tanggal *</label>
                        <input type="date" name="tanggal" id="add-block-date" class="admin-form-input" required value="{{ old('tanggal', $defaultBlockDate) }}">
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Cakupan *</label>
                        <select name="cakupan" id="add-block-scope" class="admin-form-input" required>
                            <option value="hari" @selected(old('cakupan', 'hari') === 'hari')>Seluruh hari (studio tutup)</option>
                            <option value="jam" @selected(old('cakupan') === 'jam')>Rentang jam tertentu</option>
                        </select>
                    </div>
                </div>
                <div class="admin-meta-row-2" id="add-block-times" style="{{ old('cakupan', 'hari') === 'jam' ? '' : 'display:none;' }}">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Jam mulai *</label>
                        <input type="time" name="mulai" class="admin-form-input" value="{{ old('mulai', '09:00') }}">
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Jam selesai *</label>
                        <input type="time" name="selesai" class="admin-form-input" value="{{ old('selesai', '12:00') }}">
                    </div>
                </div>
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Catatan internal (opsional)</label>
                    <input type="text" name="catatan" class="admin-form-input" maxlength="255" placeholder="Contoh: Libur nasional" value="{{ old('catatan') }}">
                </div>
            </form>
            <hr class="divider">
            <div class="admin-modal-actions">
                <button type="button" class="admin-btn-outline" data-close="modal-add-slot">Batal</button>
                <button type="submit" form="form-add-block" class="admin-btn-primary">Simpan blokir</button>
            </div>
        </div>
    </div>

    {{-- MODAL EDIT BLOKIR --}}
    <div class="admin-modal-overlay" id="modal-edit-slot">
        <div class="admin-modal-dialog">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Edit blokir jadwal</h3>
                <button type="button" class="admin-btn-close-modal" data-close="modal-edit-slot">×</button>
            </div>
            <p class="admin-modal-subtitle" id="edit-block-label"></p>
            <hr class="divider">
            <form method="POST" id="form-edit-block">
                @csrf
                @method('PUT')
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Cakupan</label>
                    <select name="cakupan" id="edit-block-scope" class="admin-form-input">
                        <option value="hari">Seluruh hari (studio tutup)</option>
                        <option value="jam">Rentang jam tertentu</option>
                    </select>
                </div>
                <div class="admin-meta-row-2" id="edit-block-times">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Jam mulai</label>
                        <input type="time" name="mulai" id="edit-block-start" class="admin-form-input">
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Jam selesai</label>
                        <input type="time" name="selesai" id="edit-block-end" class="admin-form-input">
                    </div>
                </div>
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Catatan internal</label>
                    <input type="text" name="catatan" id="edit-block-note" class="admin-form-input" maxlength="255">
                </div>
            </form>
            <hr class="divider">
            <div class="admin-modal-actions">
                <button type="button" class="admin-btn-outline" data-close="modal-edit-slot">Batal</button>
                <button type="submit" form="form-edit-block" class="admin-btn-primary">Simpan perubahan</button>
            </div>
        </div>
    </div>

    {{-- MODAL HAPUS BLOKIR --}}
    <div class="admin-modal-overlay" id="modal-delete-slot">
        <div class="admin-modal-dialog">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Hapus blokir jadwal?</h3>
                <button type="button" class="admin-btn-close-modal" data-close="modal-delete-slot">×</button>
            </div>
            <p class="admin-modal-subtitle" id="delete-block-label"></p>
            <hr class="divider">
            <p class="admin-meta-val">Slot pada waktu ini akan kembali dapat dipesan pelanggan.</p>
            <hr class="divider">
            <form method="POST" id="form-delete-block" class="admin-modal-actions">
                @csrf
                @method('DELETE')
                <button type="button" class="admin-btn-outline" data-close="modal-delete-slot">Batal</button>
                <button type="submit" class="admin-btn-primary" style="background:#D32F2F; border-color:#D32F2F;">Hapus blokir</button>
            </form>
        </div>
    </div>

    <div class="admin-footer-copyright">
        © {{ date('Y') }} Naro Capture · Akses terbatas admin
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const open = id => document.getElementById(id).classList.add('is-open');
            const baseUrl = @json(url('/admin/jadwal/blokir'));

            document.getElementById('btn-open-add-slot').addEventListener('click', () => open('modal-add-slot'));
            const blockSel = document.getElementById('btn-block-selected');
            if (blockSel) blockSel.addEventListener('click', () => {
                document.getElementById('add-block-date').value = blockSel.dataset.date;
                open('modal-add-slot');
            });

            const toggleTimes = (scopeEl, timesEl) => { timesEl.style.display = scopeEl.value === 'jam' ? '' : 'none'; };
            const addScope = document.getElementById('add-block-scope'), addTimes = document.getElementById('add-block-times');
            addScope.addEventListener('change', () => toggleTimes(addScope, addTimes));
            const editScope = document.getElementById('edit-block-scope'), editTimes = document.getElementById('edit-block-times');
            editScope.addEventListener('change', () => toggleTimes(editScope, editTimes));

            document.querySelectorAll('.js-edit-block').forEach(b => b.addEventListener('click', () => {
                document.getElementById('form-edit-block').action = baseUrl + '/' + b.dataset.id;
                document.getElementById('edit-block-label').textContent = b.dataset.label;
                editScope.value = b.dataset.whole === '1' ? 'hari' : 'jam';
                document.getElementById('edit-block-start').value = b.dataset.whole === '1' ? '09:00' : b.dataset.start;
                document.getElementById('edit-block-end').value = b.dataset.whole === '1' ? '12:00' : b.dataset.end;
                document.getElementById('edit-block-note').value = b.dataset.note || '';
                toggleTimes(editScope, editTimes);
                open('modal-edit-slot');
            }));
            document.querySelectorAll('.js-delete-block').forEach(b => b.addEventListener('click', () => {
                document.getElementById('form-delete-block').action = baseUrl + '/' + b.dataset.id;
                document.getElementById('delete-block-label').textContent = b.dataset.label;
                open('modal-delete-slot');
            }));

            document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => {
                const m = document.getElementById(b.getAttribute('data-close'));
                if (m) m.classList.remove('is-open');
            }));

            @if ($errors->has('tanggal') || $errors->has('cakupan') || $errors->has('mulai') || $errors->has('selesai'))
                open('modal-add-slot');
            @endif
        });
    </script>
@endsection
