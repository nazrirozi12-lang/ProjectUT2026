{{--
    Notifikasi aksi (toast): berhasil, gagal, peringatan, info, dan galat validasi.
    Dipakai di layout admin & pelanggan.
    Parameter opsional: $skipSuccess (true = jangan tampilkan pesan sukses).
--}}
@php
    $toasts = [];
    if (empty($skipSuccess) && session('success')) {
        $toasts[] = ['success', 'Berhasil', session('success')];
    }
    if (session('error')) {
        $toasts[] = ['error', 'Gagal', session('error')];
    }
    if (session('warning')) {
        $toasts[] = ['warning', 'Perhatian', session('warning')];
    }
    if (session('info')) {
        $toasts[] = ['info', 'Info', session('info')];
    }
    if (isset($errors) && $errors->any() && empty($skipValidation)) {
        $toasts[] = ['error', 'Data belum dapat disimpan', $errors->first() . ($errors->count() > 1 ? ' (+' . ($errors->count() - 1) . ' kesalahan lain)' : '')];
    }
@endphp
@if (count($toasts))
    <div class="toast-stack" role="region" aria-label="Notifikasi">
        @foreach ($toasts as [$type, $title, $message])
            <div class="toast toast--{{ $type }}" role="{{ $type === 'error' ? 'alert' : 'status' }}">
                <span class="toast__icon" aria-hidden="true">{{ ['success' => '✓', 'error' => '!', 'warning' => '!', 'info' => 'i'][$type] }}</span>
                <div class="toast__body">
                    <strong class="toast__title">{{ $title }}</strong>
                    <p class="toast__msg">{{ $message }}</p>
                </div>
                <button type="button" class="toast__close" aria-label="Tutup notifikasi">×</button>
            </div>
        @endforeach
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.toast').forEach(function (t) {
                var close = function () { t.classList.add('is-leaving'); setTimeout(function () { t.remove(); }, 250); };
                t.querySelector('.toast__close').addEventListener('click', close);
                setTimeout(close, t.classList.contains('toast--error') ? 9000 : 6000);
            });
        });
    </script>
@endif
