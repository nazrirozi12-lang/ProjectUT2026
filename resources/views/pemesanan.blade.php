@extends('layouts.app')

@section('title', 'Pemesanan — Naro Capture')

@section('content')
@php
    $selPkg = collect($packages)->firstWhere('id', $selectedPackageId) ?? ($packages[0] ?? []);
    $selDur = (int) ($selPkg['duration'] ?? 60);
@endphp
<main class="booking-page">
    {{-- Breadcrumb & Header --}}
    <section class="booking-hero">
        <div class="booking-breadcrumb">
            <a href="{{ url('/') }}">Beranda</a>
            <span class="sep">/</span>
            <span>Pemesanan</span>
        </div>
        <div class="booking-header">
            <div class="booking-header__left">
                <span class="eyebrow">MARI BUAT KENANGAN YANG BERARTI</span>
                <h1 class="booking-header__title">Rencanakan sesi Anda.</h1>
            </div>
            <p class="body-text booking-header__desc">
                Pilih paket, tentukan waktu, lalu ceritakan sedikit tentang Anda. Saya akan meninjau ketersediaan sebelum mengonfirmasi sesi.
            </p>
        </div>
    </section>

    <form action="{{ route('pemesanan.store') }}" method="POST" id="bookingForm">
        @csrf

        {{-- LANGKAH 1: PILIH PAKET LAYANAN --}}
        <section class="booking-step">
            <div class="step-title">
                <div class="step-badge">
                    <span class="step-num">01</span>
                    <h2 class="step-heading">Pilih paket layanan</h2>
                </div>
                <p class="step-desc">Temukan sesi yang paling sesuai dengan cerita Anda.</p>
            </div>

            <div class="package-selection-grid">
                @foreach ($packages as $pkg)
                    <label class="package-card-select {{ $selectedPackageId === $pkg['id'] ? 'is-selected' : '' }}" data-id="{{ $pkg['id'] }}" data-name="{{ $pkg['name'] }}" data-price="{{ $pkg['price'] }}" data-duration="{{ $pkg['duration'] }}" data-location="{{ $pkg['location_note'] }}">
                        <input type="radio" name="package_id" value="{{ $pkg['id'] }}" {{ $selectedPackageId === $pkg['id'] ? 'checked' : '' }} class="sr-only package-radio">
                        <div class="pkg-card__img">
                            <img src="{{ \App\Support\Media::pkg($pkg['image']) }}" alt="{{ $pkg['name'] }}">
                        </div>
                        <div class="pkg-card__body">
                            <div class="pkg-card__top">
                                <div class="pkg-radio-circle">
                                    <div class="pkg-radio-inner"></div>
                                </div>
                                <span class="pkg-card__name">{{ $pkg['name'] }}</span>
                            </div>
                            <div class="pkg-card__price">
                                <span class="price-val">{{ $pkg['price'] }}</span>
                                <span class="price-unit">/ sesi</span>
                            </div>
                            <hr class="divider">
                            <ul class="pkg-card__features">
                                @foreach ($pkg['items'] as $item)
                                    <li>
                                        <img src="{{ asset('images/check.svg') }}" alt="" width="14" height="14">
                                        <span>{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            @if (!empty($pkg['note']))
                                <p class="pkg-card__note">{{ $pkg['note'] }}</p>
                            @endif
                            <div class="pkg-card__footer">
                                @if ($selectedPackageId === $pkg['id'])
                                    <span class="pkg-tag">PAKET TERPILIH</span>
                                @else
                                    <span class="pkg-tag-muted">KLIK UNTUK MEMILIH</span>
                                @endif
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>
        </section>

        {{-- LANGKAH 2 & 3: GRID KOLOM (JADWAL & FORMULIR) --}}
        <div class="booking-split-grid">
            {{-- KOLOM KIRI: KALENDER & RINGKASAN --}}
            <div class="booking-split-left">
                {{-- KALENDER & WAKTU --}}
                <div class="booking-box">
                    <div class="step-title">
                        <div class="step-badge">
                            <span class="step-num">02</span>
                            <h2 class="step-heading">Pilih tanggal &amp; waktu</h2>
                        </div>
                        <p class="step-desc">Pilih jadwal yang tersedia untuk sesi Anda.</p>
                    </div>

                    {{-- Header Bulan --}}
                    <div class="calendar-month-header">
                        <h3 class="month-title" id="currentMonthYear"></h3>
                        <div class="month-nav">
                            <button type="button" class="btn-icon" id="calPrev" aria-label="Bulan sebelumnya"><img src="{{ asset('images/chevron-left.svg') }}" alt=""></button>
                            <button type="button" class="btn-icon" id="calNext" aria-label="Bulan berikutnya"><img src="{{ asset('images/chevron-right.svg') }}" alt=""></button>
                        </div>
                    </div>

                    {{-- Tabel Kalender (dirender dari tanggal hari ini + ketersediaan nyata) --}}
                    <div class="calendar-grid">
                        <div class="cal-days-header">
                            <span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span><span>Min</span>
                        </div>
                        <div class="cal-dates" id="calDates"></div>
                    </div>

                    {{-- Legenda --}}
                    <div class="cal-legend">
                        <div class="cal-legend__item">
                            <span class="legend-dot dot-available"></span>
                            <span>Tersedia</span>
                        </div>
                        <div class="cal-legend__item">
                            <span class="legend-dot dot-selected"></span>
                            <span>Dipilih</span>
                        </div>
                        <div class="cal-legend__item">
                            <span class="legend-dot dot-disabled"></span>
                            <span>Tidak tersedia</span>
                        </div>
                    </div>

                    <input type="hidden" name="session_date" id="selectedDateInput" value="{{ old('session_date') }}">
                    @error('session_date')
                        <p class="form-error" style="color:#D32F2F; font-size:13px; margin-top:8px;">{{ $message }}</p>
                    @enderror
                    @error('session_time')
                        <p class="form-error" style="color:#D32F2F; font-size:13px; margin-top:8px;">{{ $message }}</p>
                    @enderror

                    <hr class="divider">

                    {{-- Pilihan Jam --}}
                    <div class="time-selection-wrap">
                        <div class="time-header">
                            <span class="time-date-label" id="timeDateDisplay">Pilih tanggal</span>
                            <span class="small-caps">WIB</span>
                        </div>
                        <div class="time-slots" id="timeSlots"></div>
                        <p class="form-hint" id="slotEmptyNote" style="display:none; color:#B7791F; font-size:13px; margin-top:10px;">Belum ada jam yang tersedia pada tanggal ini. Silakan pilih tanggal lain.</p>
                        <p class="time-duration-note" id="timeDurationNote">Durasi {{ $selPkg['name'] ?? '' }}: {{ $selDur }} menit</p>
                    </div>
                </div>

                {{-- RINGKASAN PEMESANAN --}}
                <div class="booking-box summary-box">
                    <h3 class="summary-title">Sesi yang Anda rencanakan</h3>
                    
                    <div class="summary-item">
                        <img src="{{ asset('images/icon-camera.svg') }}" alt="" class="summary-icon">
                        <div class="summary-item__text">
                            <span class="summary-label">Paket pilihan</span>
                            <strong class="summary-val" id="sumPackageName">{{ $selPkg['name'] ?? '' }}</strong>
                        </div>
                    </div>

                    <div class="summary-item">
                        <img src="{{ asset('images/icon-calendar.svg') }}" alt="" class="summary-icon">
                        <div class="summary-item__text">
                            <span class="summary-label">Tanggal sesi</span>
                            <strong class="summary-val" id="sumDate">Pilih tanggal</strong>
                        </div>
                    </div>

                    <div class="summary-item">
                        <img src="{{ asset('images/icon-clock.svg') }}" alt="" class="summary-icon">
                        <div class="summary-item__text">
                            <span class="summary-label">Waktu sesi</span>
                            <strong class="summary-val" id="sumTime">Pilih jam · {{ $selDur }} menit</strong>
                        </div>
                    </div>

                    <hr class="divider">

                    <div class="summary-price-row">
                        <span class="summary-price-label">Harga paket / sesi</span>
                        <span class="summary-price-val" id="sumPrice">{{ $selPkg['price'] ?? '' }}</span>
                    </div>

                    <div class="summary-notice">
                        <img src="{{ asset('images/icon-info.svg') }}" alt="" class="notice-icon">
                        <p>Ini adalah permintaan jadwal, bukan konfirmasi. Sesi dikonfirmasi setelah ketersediaan ditinjau. Belum ada pembayaran pada tahap ini.</p>
                    </div>
                </div>
            </div>

            {{-- KOLOM KANAN: FORMULIR DATA DIRI --}}
            <div class="booking-split-right">
                <div class="booking-box form-box">
                    <div class="step-title">
                        <div class="step-badge">
                            <span class="step-num">03</span>
                            <h2 class="step-heading">Ceritakan tentang Anda</h2>
                        </div>
                        <p class="step-desc">Lengkapi data berikut agar saya bisa membantu menyiapkan sesi yang personal.</p>
                    </div>

                    <div class="form-section">
                        <div class="form-section__header">
                            <h4 class="form-section__title">Data diri</h4>
                            <span class="form-required-note">* Wajib diisi</span>
                        </div>

                        <div class="form-group">
                            <label for="nama_lengkap" class="form-label">Nama lengkap *</label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" placeholder="Contoh: Ayu Pramesti" value="{{ old('nama_lengkap') }}" required minlength="3" maxlength="100" autocomplete="name">
                            @error('nama_lengkap')<p class="form-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="form-row-2">
                            <div class="form-group">
                                <label for="email" class="form-label">Alamat email *</label>
                                <input type="email" id="email" name="email" class="form-control" placeholder="nama@email.com" value="{{ old('email') }}" required maxlength="255" autocomplete="email">
                                @error('email')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="form-group">
                                <label for="whatsapp" class="form-label">Nomor WhatsApp *</label>
                                <input type="tel" id="whatsapp" name="whatsapp" class="form-control" placeholder="+62 812 3456 7890" value="{{ old('whatsapp') }}" inputmode="tel" autocomplete="tel" required maxlength="25" pattern="\+?[0-9][0-9\s\-]{6,22}[0-9]" title="Contoh: 0812 3456 7890 atau +62 812 3456 7890">
                                @error('whatsapp')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <p class="form-helper-text">Pastikan email dan nomor WhatsApp aktif untuk menerima kabar dari studio.</p>
                    </div>

                    <hr class="divider">

                    <div class="form-section">
                        <h4 class="form-section__title">Detail sesi</h4>

                        <div class="form-group">
                            <label for="paket_display" class="form-label">Paket pilihan *</label>
                            <div class="custom-select-mimic">
                                <span id="formSelectedPkgLabel">{{ $selPkg['name'] ?? '' }} · {{ $selPkg['price'] ?? '' }} / sesi</span>
                                <img src="{{ asset('images/chevron-down.svg') }}" alt="" class="select-chevron">
                            </div>
                            <p class="form-helper-text">Sesuai dengan paket yang Anda pilih di atas.</p>
                        </div>

                        <div class="form-group">
                            <label for="lokasi" class="form-label">Lokasi sesi *</label>
                            <input type="text" id="lokasi" name="lokasi" class="form-control" placeholder="Contoh: Rumah / Cafe / Studio di Jakarta Selatan" value="{{ old('lokasi') }}" required minlength="3" maxlength="255">
                            @error('lokasi')<p class="form-error">{{ $message }}</p>@enderror
                            <p class="form-helper-text" id="lokasiHelper">{{ $selPkg['location_note'] ?? '' }}</p>
                        </div>

                        <div class="form-group">
                            <label for="catatan" class="form-label">Catatan untuk studio (opsional)</label>
                            <textarea id="catatan" name="catatan" rows="4" class="form-control form-textarea" placeholder="Ceritakan konsep atau preferensi suasana yang Anda inginkan...">{{ old('catatan') }}</textarea>
                        </div>
                    </div>

                    <hr class="divider">

                    {{-- Persetujuan & Tombol Kirim --}}
                    <div class="form-section">
                        <label class="consent-checkbox-label">
                            <input type="checkbox" name="persetujuan" value="1" required class="custom-checkbox" {{ old('persetujuan') ? 'checked' : '' }}>
                            <span class="consent-text">Saya menyetujui Kebijakan Privasi dan Syarat Pemesanan, serta bersedia dihubungi untuk membahas rencana sesi ini.</span>
                        </label>
                        @error('persetujuan')<p class="form-error">{{ $message }}</p>@enderror

                        <button type="submit" class="btn btn--fill btn--block btn--lg">
                            Kirim Permintaan Pemesanan @include('partials.arrow')
                        </button>

                        <p class="form-disclaimer-text">
                            Setelah permintaan dikirim, kode pesanan dibuat dan ditampilkan di checkout serta dikirim ke email atau WhatsApp Anda. Simpan kode untuk mengecek status. Tim studio akan meninjau ketersediaan sebelum mengonfirmasi sesi.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</main>

{{-- SCRIPT INTERAKTIF PEMILIHAN PAKET, KALENDER, & WAKTU --}}
<script type="application/json" id="calendar-data">@json($calendar)</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const packageCards = document.querySelectorAll('.package-card-select');
    const selectedDateInput = document.getElementById('selectedDateInput');
    
    // Elemen Ringkasan & Form Mirror
    const sumPkg = document.getElementById('sumPackageName');
    const sumPrice = document.getElementById('sumPrice');
    const sumTime = document.getElementById('sumTime');
    const sumDate = document.getElementById('sumDate');
    const formSelectedPkgLabel = document.getElementById('formSelectedPkgLabel');
    const lokasiHelper = document.getElementById('lokasiHelper');
    const timeDurationNote = document.getElementById('timeDurationNote');
    const timeDateDisplay = document.getElementById('timeDateDisplay');

    // Nilai awal diambil dari paket yang sedang terpilih (dirender server)
    const initCard = document.querySelector('.package-card-select.is-selected') || packageCards[0];
    let currentPkg = {
        name: initCard ? initCard.dataset.name : '',
        price: initCard ? initCard.dataset.price : '',
        duration: initCard ? parseInt(initCard.dataset.duration || 60) : 60,
        location: initCard ? initCard.dataset.location : ''
    };
    let currentDateLabel = 'Pilih tanggal';
    let currentTimeSlot = '';

    function updateSummary() {
        if (sumPkg) sumPkg.textContent = currentPkg.name;
        if (sumPrice) sumPrice.textContent = currentPkg.price;
        if (formSelectedPkgLabel) formSelectedPkgLabel.textContent = `${currentPkg.name} · ${currentPkg.price} / sesi`;
        if (lokasiHelper) lokasiHelper.textContent = currentPkg.location;
        if (sumDate) sumDate.textContent = currentDateLabel;
        if (timeDateDisplay) timeDateDisplay.textContent = currentDateLabel;
        
        // Jam selesai = jam mulai + durasi paket
        if (currentTimeSlot) {
            const [startH, startM] = currentTimeSlot.split('.').map(n => parseInt(n, 10));
            const endTotal = startH * 60 + startM + currentPkg.duration;
            const endTime = String(Math.floor(endTotal / 60) % 24).padStart(2, '0') + '.' + String(endTotal % 60).padStart(2, '0');
            if (sumTime) sumTime.textContent = `${currentTimeSlot}–${endTime} WIB · ${currentPkg.duration} menit`;
            if (timeDurationNote) timeDurationNote.textContent = `Durasi ${currentPkg.name}: ${currentPkg.duration} menit · ${currentTimeSlot}–${endTime} WIB`;
        } else {
            if (sumTime) sumTime.textContent = `Pilih jam · ${currentPkg.duration} menit`;
            if (timeDurationNote) timeDurationNote.textContent = `Durasi ${currentPkg.name}: ${currentPkg.duration} menit`;
        }
    }

    // Pemilihan Paket
    packageCards.forEach(card => {
        card.addEventListener('click', () => {
            packageCards.forEach(c => {
                c.classList.remove('is-selected');
                const tag = c.querySelector('.pkg-card__footer span');
                if (tag) {
                    tag.className = 'pkg-tag-muted';
                    tag.textContent = 'KLIK UNTUK MEMILIH';
                }
            });
            card.classList.add('is-selected');
            const radio = card.querySelector('.package-radio');
            if (radio) radio.checked = true;

            const activeTag = card.querySelector('.pkg-card__footer span');
            if (activeTag) {
                activeTag.className = 'pkg-tag';
                activeTag.textContent = 'PAKET TERPILIH';
            }

            currentPkg.name = card.dataset.name;
            currentPkg.price = card.dataset.price;
            currentPkg.duration = parseInt(card.dataset.duration || 90);
            currentPkg.location = card.dataset.location;

            refreshCalendar();
        });
    });

    // ===== Kalender & slot waktu dinamis (data nyata dari server) =====
    const cal = JSON.parse(document.getElementById('calendar-data').textContent);
    const MONTHS = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const DAYS = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const calDatesEl = document.getElementById('calDates');
    const timeSlotsEl = document.getElementById('timeSlots');
    const slotEmptyNote = document.getElementById('slotEmptyNote');
    const monthTitle = document.getElementById('currentMonthYear');
    const prevBtn = document.getElementById('calPrev');
    const nextBtn = document.getElementById('calNext');

    const pad = n => String(n).padStart(2, '0');
    const ymd = (y, m, d) => `${y}-${pad(m + 1)}-${pad(d)}`;
    const parseYmd = str => { const [y, m, d] = str.split('-').map(Number); return { y, m: m - 1, d }; };
    const labelOf = str => { const { y, m, d } = parseYmd(str); return `${DAYS[new Date(y, m, d).getDay()]}, ${d} ${MONTHS[m]} ${y}`; };
    const avail = () => (cal.availability[String(currentPkg.duration)] || {});
    const daySlots = str => avail()[str] || null;
    const dayHasSlot = str => { const s = daySlots(str); return !!s && Object.values(s).includes('available'); };

    let selectedDate = '';
    let viewMonth = parseYmd(cal.min);   // bulan yang sedang tampil

    function renderCalendar() {
        monthTitle.textContent = `${MONTHS[viewMonth.m]} ${viewMonth.y}`;
        calDatesEl.innerHTML = '';

        const first = new Date(viewMonth.y, viewMonth.m, 1);
        const lead = (first.getDay() + 6) % 7;               // Senin = kolom pertama
        const daysInMonth = new Date(viewMonth.y, viewMonth.m + 1, 0).getDate();
        const prevDays = new Date(viewMonth.y, viewMonth.m, 0).getDate();

        for (let i = lead - 1; i >= 0; i--) {
            const c = document.createElement('div');
            c.className = 'cal-date is-disabled';
            c.textContent = prevDays - i;
            calDatesEl.appendChild(c);
        }
        for (let d = 1; d <= daysInMonth; d++) {
            const str = ymd(viewMonth.y, viewMonth.m, d);
            const c = document.createElement('div');
            c.textContent = d;
            if (dayHasSlot(str)) {
                c.className = 'cal-date is-available' + (str === selectedDate ? ' is-selected' : '');
                c.dataset.date = str;
                c.dataset.label = labelOf(str);
                c.setAttribute('role', 'button');
                c.tabIndex = 0;
                c.addEventListener('click', () => selectDate(str));
                c.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectDate(str); } });
            } else {
                c.className = 'cal-date is-disabled';
                if (str === cal.today) c.title = 'Hari ini';
            }
            calDatesEl.appendChild(c);
        }
        const total = lead + daysInMonth;
        for (let i = 1; i <= (7 - (total % 7)) % 7; i++) {
            const c = document.createElement('div');
            c.className = 'cal-date is-disabled';
            c.textContent = i;
            calDatesEl.appendChild(c);
        }

        // Navigasi bulan hanya dalam rentang yang bisa dipesan
        const min = parseYmd(cal.min), max = parseYmd(cal.max);
        const idx = o => o.y * 12 + o.m;
        prevBtn.disabled = idx(viewMonth) <= idx(min);
        nextBtn.disabled = idx(viewMonth) >= idx(max);
        prevBtn.style.opacity = prevBtn.disabled ? '.35' : '1';
        nextBtn.style.opacity = nextBtn.disabled ? '.35' : '1';
    }

    function renderSlots() {
        timeSlotsEl.innerHTML = '';
        const slots = selectedDate ? daySlots(selectedDate) : null;
        slotEmptyNote.style.display = 'none';
        if (!slots) { selectedTimeInit(''); return; }

        let chosen = currentTimeSlot && slots[currentTimeSlot] === 'available' ? currentTimeSlot : '';
        if (!chosen) chosen = Object.keys(slots).find(k => slots[k] === 'available') || '';
        currentTimeSlot = chosen;

        Object.keys(slots).forEach(time => {
            if (slots[time] === 'available') {
                const label = document.createElement('label');
                label.className = 'time-slot-btn' + (time === chosen ? ' is-selected' : '');
                label.innerHTML = `<input type="radio" name="session_time" value="${time}" class="sr-only time-radio"${time === chosen ? ' checked' : ''}>` +
                    `<span class="slot-time">${time}</span><span class="slot-status">${time === chosen ? 'Dipilih' : 'Tersedia'}</span>`;
                label.addEventListener('click', () => { currentTimeSlot = time; renderSlots(); updateSummary(); });
                timeSlotsEl.appendChild(label);
            } else {
                const div = document.createElement('div');
                div.className = 'time-slot-btn is-full';
                div.innerHTML = `<span class="slot-time">${time}</span><span class="slot-status">${slots[time] === 'blocked' ? 'Tidak tersedia' : 'Penuh'}</span>`;
                timeSlotsEl.appendChild(div);
            }
        });
        if (!chosen) slotEmptyNote.style.display = 'block';
    }
    function selectedTimeInit(v) { currentTimeSlot = v; }

    function selectDate(str) {
        selectedDate = str;
        selectedDateInput.value = str;
        currentDateLabel = labelOf(str);
        renderCalendar();
        renderSlots();
        updateSummary();
    }

    // Hitung ulang saat paket berganti (durasi berbeda = ketersediaan berbeda)
    function refreshCalendar() {
        if (!selectedDate || !dayHasSlot(selectedDate)) {
            selectedDate = '';
            const allDates = Object.keys(avail()).filter(dayHasSlot);
            const keep = cal.oldDate && dayHasSlot(cal.oldDate) ? cal.oldDate : (allDates[0] || '');
            if (keep) { selectedDate = keep; viewMonth = parseYmd(keep); }
        }
        selectedDateInput.value = selectedDate;
        currentDateLabel = selectedDate ? labelOf(selectedDate) : 'Pilih tanggal';
        renderCalendar();
        renderSlots();
        updateSummary();
    }

    prevBtn.addEventListener('click', () => { viewMonth = { y: viewMonth.m === 0 ? viewMonth.y - 1 : viewMonth.y, m: (viewMonth.m + 11) % 12, d: 1 }; renderCalendar(); });
    nextBtn.addEventListener('click', () => { viewMonth = { y: viewMonth.m === 11 ? viewMonth.y + 1 : viewMonth.y, m: (viewMonth.m + 1) % 12, d: 1 }; renderCalendar(); });

    if (cal.oldTime) currentTimeSlot = cal.oldTime;
    refreshCalendar();

    // Cegah kirim bila belum memilih tanggal & jam yang tersedia
    document.getElementById('bookingForm').addEventListener('submit', e => {
        if (!selectedDateInput.value || !currentTimeSlot) {
            e.preventDefault();
            slotEmptyNote.textContent = 'Pilih tanggal dan jam yang tersedia terlebih dahulu.';
            slotEmptyNote.style.display = 'block';
            document.getElementById('currentMonthYear').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    // Format Otomatis Nomor WhatsApp (Number only & 08 -> +62 8)
    const waInput = document.getElementById('whatsapp');
    if (waInput) {
        waInput.addEventListener('input', (e) => {
            let val = waInput.value;

            // Pertahankan hanya digit angka dan tanda plus awal jika ada
            let rawDigits = val.replace(/\D/g, '');

            // Jika user mengetik mulai dari 08... -> ganti 0 di depan jadi 62
            if (rawDigits.startsWith('0')) {
                rawDigits = '62' + rawDigits.substring(1);
            } else if (rawDigits.startsWith('8')) {
                // Jika langsung ketik 8... -> tambahkan 62 di depan
                rawDigits = '62' + rawDigits;
            }

            // Jika kosong, kosongkan input
            if (!rawDigits) {
                waInput.value = '';
                return;
            }

            // Format rapi: +62 8xx xxxx xxxx
            let formatted = '+';
            if (rawDigits.startsWith('62')) {
                formatted += '62';
                const rest = rawDigits.substring(2);
                if (rest.length > 0) {
                    formatted += ' ' + rest.substring(0, 3);
                }
                if (rest.length > 3) {
                    formatted += ' ' + rest.substring(3, 7);
                }
                if (rest.length > 7) {
                    formatted += ' ' + rest.substring(7, 12);
                }
            } else {
                formatted += rawDigits;
            }

            waInput.value = formatted;
        });

        // Cegah karakter non-angka saat mengetik
        waInput.addEventListener('keydown', (e) => {
            // Izinkan navigation keys, backspace, delete, tab, enter, ctrl+a/c/v
            const allowed = [
                'Backspace', 'Delete', 'Tab', 'Enter', 'Escape', 'ArrowLeft', 'ArrowRight', 'Home', 'End'
            ];
            if (allowed.includes(e.key) || (e.ctrlKey || e.metaKey)) {
                return;
            }
            // Cegah jika bukan angka 0-9
            if (!/^[0-9]$/.test(e.key)) {
                e.preventDefault();
            }
        });
    }
});
</script>
@endsection

