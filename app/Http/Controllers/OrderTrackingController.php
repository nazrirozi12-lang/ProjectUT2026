<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\BookingSummary;
use App\Support\Studio;
use Illuminate\Http\Request;

class OrderTrackingController extends Controller
{
    /**
     * Halaman Cek Status Pesanan Publik
     */
    public function show(Request $request)
    {
        $searchCode = trim((string) $request->query('kode', ''));
        $searchContact = trim((string) $request->query('kontak', ''));

        $order = null;
        $notFound = false;

        if ($searchCode !== '') {
            $candidate = Order::with(['latestPayment', 'package', 'gallery'])->where(function ($q) use ($searchCode) {
                $q->where('order_code', $searchCode)->orWhere('invoice_number', $searchCode);
            })->first();

            // Kode saja tidak cukup: kontak harus cocok dengan email / WhatsApp saat pemesanan
            if ($candidate && $this->contactMatches($candidate, $searchContact)) {
                $order = $candidate;
            } else {
                $notFound = true;
            }
        } elseif (session()->has('last_booking')) {
            $sess = session('last_booking');
            if (isset($sess['order_code'])) {
                $order = Order::with(['latestPayment', 'package', 'gallery'])->where('order_code', $sess['order_code'])->first();
            }
        }

        $data = null;
        $track = null;
        $maskedEmail = $maskedPhone = '';

        if ($order) {
            $data = BookingSummary::fromOrder($order);
            // Pelanggan yang sudah terverifikasi boleh membuka invoice/checkout tanpa sesi lama
            session(['last_booking' => $data]);
            $maskedEmail = $this->maskEmail($order->customer_email);
            $maskedPhone = $this->maskPhone($order->customer_whatsapp);
            $track = $this->buildTrack($order);
        }

        $hasSearched = $order !== null;
        $statusKey = $order ? $order->session_status : null;
        $payStatus = $order ? $order->payment_status : null;

        return view('cek-pesanan', compact('data', 'track', 'searchCode', 'searchContact', 'statusKey', 'payStatus', 'maskedEmail', 'maskedPhone', 'hasSearched', 'notFound'));
    }

    /** Status, tindakan, dan linimasa pesanan dari data nyata. */
    private function buildTrack(Order $o): array
    {
        $payment = $o->latestPayment;
        $d = fn ($ts, $fmt = 'j F Y') => $ts ? Studio::dateLabel($ts, $fmt) : '';
        $contact = ['kode' => $o->order_code, 'kontak' => $o->customer_email];
        $payUrl = route('konfirmasi', $contact);
        $gallery = $o->gallery && $o->gallery->status === 'Diterbitkan' ? $o->gallery : null;

        $ps = $o->payment_status;
        $ss = $o->session_status;
        $cta = [];
        $icon = 'clock';
        $tone = 'info';

        if ($ss === 'Dibatalkan') {
            $icon = 'alert';
            $eyebrow = 'PESANAN DIBATALKAN';
            $heading = 'Pesanan ini dibatalkan';
            $text = 'Pesanan dibatalkan oleh studio' . ($o->cancel_reason ? ': ' . $o->cancel_reason : '.') . ' Silakan hubungi studio atau buat pemesanan baru bila ingin menjadwalkan sesi.';
            $notice = ['Pesanan tidak aktif', 'Jadwal sesi sudah dilepas. Hubungi studio bila Anda memerlukan penjelasan atau pengembalian dana.'];
            $cta[] = ['label' => 'Buat pemesanan baru', 'url' => route('pemesanan'), 'primary' => true];
        } elseif (in_array($ss, ['Selesai', 'Diarsipkan'], true)) {
            $icon = 'check';
            $eyebrow = 'SESI SELESAI';
            $heading = 'Sesi Anda sudah selesai';
            $text = $gallery
                ? 'Terima kasih sudah memercayakan momen Anda kepada kami. Galeri foto Anda sudah tersedia.'
                : 'Terima kasih sudah memercayakan momen Anda kepada kami. Galeri foto akan dikabarkan oleh studio setelah selesai disunting.';
            $notice = $gallery
                ? ['Galeri siap dibuka', 'Buka galeri dengan PIN yang dikirimkan studio kepada Anda.']
                : ['Galeri belum tersedia', 'Studio sedang menyiapkan hasil foto Anda. Anda akan dihubungi melalui email atau WhatsApp.'];
        } elseif ($ss === 'Dikonfirmasi') {
            $icon = 'check';
            $eyebrow = 'PESANAN DIKONFIRMASI';
            $heading = 'Sesi Anda sudah dikonfirmasi';
            $text = 'Pembayaran telah terverifikasi dan jadwal sesi disetujui oleh studio. Kami menantikan sesi Anda.';
            $notice = ['Sampai jumpa pada ' . $d($o->session_date, 'l, j F Y'), 'Sesi berlangsung pukul ' . $o->session_time_slot . '. Sesi foto belum berlangsung; tahap selesai masih akan datang.'];
        } elseif ($ps === 'Perlu perbaikan') {
            $icon = 'alert';
            $tone = 'warn';
            $eyebrow = 'BUKTI PERLU DIPERBAIKI';
            $heading = 'Mohon unggah ulang bukti pembayaran';
            $msg = $payment && $payment->customer_message ? $payment->customer_message : 'Bukti transfer sebelumnya belum dapat kami verifikasi.';
            $text = 'Studio meminta perbaikan bukti pembayaran Anda. Pembayaran belum terverifikasi dan pesanan belum dikonfirmasi.';
            $notice = ['Pesan dari studio', $msg];
            $cta[] = ['label' => 'Unggah ulang bukti pembayaran', 'url' => $payUrl, 'primary' => true];
        } elseif ($ps === 'Terverifikasi') {
            $icon = 'check';
            $eyebrow = 'PEMBAYARAN TERVERIFIKASI';
            $heading = 'Pembayaran Anda sudah terverifikasi';
            $text = 'Terima kasih, pembayaran Anda sudah kami terima. Studio akan segera mengonfirmasi jadwal sesi Anda.';
            $notice = ['Tidak perlu mengunggah bukti lagi', 'Konfirmasi jadwal diberikan oleh studio melalui email atau WhatsApp.'];
        } elseif ($ps === 'Bukti terkirim') {
            $eyebrow = 'MENUNGGU TINJAUAN STUDIO';
            $heading = 'Bukti pembayaran sedang diperiksa';
            $text = 'Bukti Anda sudah diterima. Pembayaran belum terverifikasi dan pemesanan belum dikonfirmasi. Tim studio akan mengabari Anda melalui email atau WhatsApp setelah peninjauan.';
            $notice = ['Tidak perlu mengunggah bukti lagi.', 'Mohon tunggu hasil pemeriksaan agar tidak ada unggahan ganda. Kirim perbaikan hanya jika studio memintanya.'];
        } else {
            $eyebrow = 'MENUNGGU PEMBAYARAN';
            $heading = 'Menunggu transfer pembayaran';
            $text = 'Silakan lakukan pembayaran sesuai instruksi invoice dan unggah bukti transfer agar pesanan Anda dapat diproses.';
            $notice = ['Unggah bukti setelah transfer', 'Pesanan baru diproses setelah studio menerima dan memverifikasi bukti pembayaran Anda.'];
            $cta[] = ['label' => 'Unggah bukti pembayaran', 'url' => $payUrl, 'primary' => true];
        }

        if ($gallery) {
            array_unshift($cta, ['label' => 'Buka galeri foto', 'url' => route('galeri-klien', ['slug' => $gallery->slug]), 'primary' => true]);
        }

        // Linimasa dari data nyata
        $steps = [];
        $steps[] = ['title' => 'Pesanan dibuat', 'desc' => $d($o->created_at) . ' · Paket, rencana jadwal, dan data pemesan telah diterima.', 'state' => 'done'];

        $firstPayment = $o->payments()->orderBy('id')->first();
        $steps[] = $firstPayment
            ? ['title' => 'Bukti pembayaran diterima', 'desc' => $d($firstPayment->created_at) . ' · Bukti diterima, bukan tanda pembayaran terverifikasi.', 'state' => 'done']
            : ['title' => 'Menunggu pembayaran', 'desc' => 'Lakukan transfer lalu unggah bukti pembayaran.', 'state' => 'upcoming'];

        if ($ps === 'Terverifikasi') {
            $steps[] = ['title' => 'Verifikasi pembayaran', 'desc' => ($payment && $payment->verified_at ? $d($payment->verified_at) . ' · ' : '') . 'Pembayaran telah diperiksa dan dinyatakan terverifikasi.', 'state' => 'done'];
        } elseif ($ps === 'Perlu perbaikan') {
            $steps[] = ['title' => 'Verifikasi pembayaran', 'desc' => 'Bukti perlu diperbaiki. Unggah bukti yang baru agar pemeriksaan dapat dilanjutkan.', 'state' => 'warn'];
        } elseif ($ps === 'Bukti terkirim') {
            $steps[] = ['title' => 'Bukti diterima · sedang diperiksa', 'desc' => 'Studio meninjau bukti pembayaran dan ketersediaan jadwal.', 'state' => 'upcoming'];
        } else {
            $steps[] = ['title' => 'Verifikasi pembayaran', 'desc' => 'Dimulai setelah bukti pembayaran diterima.', 'state' => 'upcoming'];
        }

        if ($ss === 'Dibatalkan') {
            $steps[] = ['title' => 'Pesanan dibatalkan', 'desc' => $d($o->cancelled_at ?? $o->updated_at) . ($o->cancel_reason ? ' · ' . $o->cancel_reason : ''), 'state' => 'cancelled'];
        } else {
            $confirmed = in_array($ss, ['Dikonfirmasi', 'Selesai', 'Diarsipkan'], true);
            $steps[] = $confirmed
                ? ['title' => 'Pesanan dikonfirmasi', 'desc' => $d($o->confirmed_at ?? $o->updated_at) . ' · Jadwal ' . $d($o->session_date) . ' telah disetujui studio.', 'state' => 'done']
                : ['title' => 'Pesanan dikonfirmasi', 'desc' => 'Belum dikonfirmasi. Tunggu kabar resmi dari studio.', 'state' => 'upcoming'];
            $done = in_array($ss, ['Selesai', 'Diarsipkan'], true);
            $steps[] = $done
                ? ['title' => 'Sesi selesai', 'desc' => $d($o->completed_at ?? $o->updated_at) . ' · Sesi foto telah berlangsung.', 'state' => 'done']
                : ['title' => 'Sesi selesai', 'desc' => 'Setelah pesanan dikonfirmasi dan sesi foto berlangsung.', 'state' => 'upcoming'];
        }

        // Langkah aktif = langkah pertama yang belum selesai (atau yang bermasalah)
        foreach ($steps as $i => $st) {
            if ($st['state'] === 'warn') {
                break;
            }
            if ($st['state'] === 'upcoming') {
                $steps[$i]['state'] = 'current';
                break;
            }
        }

        return compact('icon', 'tone', 'eyebrow', 'heading', 'text', 'notice', 'cta') + ['timeline' => $steps, 'gallery' => $gallery, 'show_prep' => $ss === 'Dikonfirmasi'];
    }

    private function contactMatches(Order $order, string $contact): bool
    {
        $contact = trim($contact);
        if ($contact === '') {
            return false;
        }

        if (str_contains($contact, '@')) {
            return strcasecmp($contact, (string) $order->customer_email) === 0;
        }

        $normalize = function (string $phone): string {
            $digits = preg_replace('/[^0-9]/', '', $phone);
            if (str_starts_with($digits, '62')) {
                $digits = '0' . substr($digits, 2);
            }
            return $digits;
        };

        $given = $normalize($contact);

        return strlen($given) >= 8 && $given === $normalize((string) $order->customer_whatsapp);
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return $email;
        $name = $parts[0];
        $domain = $parts[1];
        $firstChar = substr($name, 0, 1);
        return $firstChar . '•••@' . $domain;
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) < 7) return $phone;
        $start = substr($phone, 0, 6);
        $end = substr($phone, -4);
        return $start . ' •••• ' . $end;
    }
}
