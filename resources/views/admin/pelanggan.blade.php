@extends('layouts.admin')
@section('title', 'Pelanggan — Naro Capture')
@section('breadcrumb', 'Pelanggan / Pelanggan')
@section('content')
    <div class="admin-page-header">
        <div class="admin-header-text">
            <h1 class="admin-page-title">Pelanggan</h1>
            <p class="admin-page-subtitle">Kontak, riwayat sesi, dan catatan pribadi untuk layanan yang lebih personal.</p>
        </div>
        <button type="button" class="admin-btn-primary" id="btn-open-add-customer">Tambah pelanggan</button>
    </div>

    <div class="admin-panel admin-orders-panel">
        <div class="admin-tabs">
            @foreach (['aktif' => 'Aktif', 'diarsipkan' => 'Diarsipkan', 'semua' => 'Semua'] as $key => $label)
                <a href="{{ route('admin.pelanggan', array_filter(['tab' => $key, 'q' => $search])) }}" class="admin-tab-item {{ $activeTab === $key ? 'is-active' : '' }}">
                    {{ $label }} ({{ $counts[$key] }})
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.pelanggan') }}" class="admin-filter-bar">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <div class="admin-search-input-wrap">
                <input type="text" name="q" placeholder="Cari nama, email, kode, atau WhatsApp (Enter)" class="admin-search-input" value="{{ $search }}">
            </div>
            @if ($search)
                <a href="{{ route('admin.pelanggan', ['tab' => $activeTab]) }}" class="admin-btn-outline btn-sm">Reset pencarian</a>
            @endif
        </form>

        <div class="admin-table-container">
            <table class="admin-orders-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Pelanggan / ID</th>
                        <th style="width: 25%;">Email</th>
                        <th style="width: 20%;">WhatsApp</th>
                        <th style="width: 15%;">Riwayat</th>
                        <th style="width: 8%;">Status</th>
                        <th style="width: 7%; text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $c)
                        <tr>
                            <td>
                                <div class="table-cell-order">
                                    <strong class="order-customer-name">{{ $c['name'] }}</strong>
                                    <span class="order-code-mono">{{ $c['id'] }}</span>
                                </div>
                            </td>
                            <td><span class="schedule-date-text">{{ $c['email'] }}</span></td>
                            <td><span class="schedule-date-text">{{ $c['whatsapp'] }}</span></td>
                            <td><span class="schedule-time-text">{{ $c['history'] }}</span></td>
                            <td><span class="badge-status-session status-{{ Str::slug($c['status']) }}">{{ $c['status'] }}</span></td>
                            <td style="text-align: right;">
                                <a href="{{ route('admin.pelanggan.detail', ['id' => $c['id']]) }}" class="admin-table-action-link">Detail →</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center; padding:40px 0; color:#6E7A8B;">Tidak ada pelanggan yang cocok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('admin.partials.pagination', ['paginator' => $paginator, 'label' => 'pelanggan'])
    </div>

    {{-- MODAL TAMBAH PELANGGAN --}}
    <div class="admin-modal-overlay" id="modal-add-customer">
        <div class="admin-modal-dialog">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Tambah pelanggan</h3>
                <button type="button" class="admin-btn-close-modal" data-close="modal-add-customer">×</button>
            </div>
            <p class="admin-modal-subtitle">Tambahkan kontak pelanggan secara manual</p>
            <hr class="divider">
            <form action="{{ route('admin.pelanggan.simpan') }}" method="POST" id="form-add-customer">
                @csrf
                <div class="admin-meta-row-2">
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">Nama lengkap *</label>
                        <input type="text" name="name" class="admin-form-input" required maxlength="255" value="{{ old('name') }}">
                    </div>
                    <div class="admin-meta-field">
                        <label class="admin-meta-label">WhatsApp *</label>
                        <input type="text" name="whatsapp" class="admin-form-input" required maxlength="50" placeholder="08xx xxxx xxxx" value="{{ old('whatsapp') }}">
                    </div>
                </div>
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Email *</label>
                    <input type="email" name="email" class="admin-form-input" required maxlength="255" value="{{ old('email') }}">
                </div>
                <div class="admin-meta-field">
                    <label class="admin-meta-label">Catatan pribadi (opsional)</label>
                    <textarea name="notes" class="admin-form-input" rows="3" maxlength="1000">{{ old('notes') }}</textarea>
                </div>
            </form>
            <hr class="divider">
            <div class="admin-modal-actions">
                <button type="button" class="admin-btn-outline" data-close="modal-add-customer">Batal</button>
                <button type="submit" form="form-add-customer" class="admin-btn-primary">Simpan pelanggan</button>
            </div>
        </div>
    </div>

    <div class="admin-footer-copyright">© {{ date('Y') }} Naro Capture · Akses terbatas admin</div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('modal-add-customer');
            document.getElementById('btn-open-add-customer').addEventListener('click', () => modal.classList.add('is-open'));
            document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => document.getElementById(b.getAttribute('data-close')).classList.remove('is-open')));
            @if ($errors->has('name') || $errors->has('email') || $errors->has('whatsapp'))
                modal.classList.add('is-open');
            @endif
        });
    </script>
@endsection
