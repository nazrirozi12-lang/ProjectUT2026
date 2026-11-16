<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderTrackingController extends Controller
{
    /**
     * Halaman Cek Status Pesanan Publik
     */
    public function show(Request $request)
    {
        $searchCode = trim($request->query('kode', ''));
        $searchContact = trim($request->query('kontak', ''));
        $manualStatus = $request->query('status');

        $order = null;
        $notFound = false;

        if ($searchCode !== '') {
            $candidate = Order::with(['latestPayment', 'package'])->where(function ($q) use ($searchCode) {
                $q->where('order_code', $searchCode)
                  ->orWhere('invoice_number', $searchCode);
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
                $order = Order::with(['latestPayment', 'package'])->where('order_code', $sess['order_code'])->first();
            }
        }

        // Tentukan mapping status UI Figma:
        // 'sedang-diperiksa', 'menunggu-pembayaran', 'dikonfirmasi', 'perbaikan-bukti', 'sesi-selesai', 'dibatalkan', 'kedaluwarsa'
        $statusKey = 'sedang-diperiksa';

        if ($manualStatus && !$order) {
            $statusKey = $manualStatus;
        } elseif ($order) {
            if ($order->session_status === 'Dibatalkan') {
                $statusKey = 'dibatalkan';
            } elseif ($order->session_status === 'Selesai') {
                $statusKey = 'sesi-selesai';
            } elseif ($order->payment_status === 'Perlu perbaikan') {
                $statusKey = 'perbaikan-bukti';
            } elseif ($order->session_status === 'Dikonfirmasi') {
                $statusKey = 'dikonfirmasi';
            } elseif ($order->payment_status === 'Bukti terkirim') {
                $statusKey = 'sedang-diperiksa';
            } else {
                $statusKey = 'menunggu-pembayaran';
            }
        }

        // Samarkan email & telepon untuk privasi sesuai UI Figma
        $rawEmail = $order ? $order->customer_email : 'ayu.pramesti@gmail.com';
        $rawPhone = $order ? $order->customer_whatsapp : '+62 812 3456 7890';

        $maskedEmail = $this->maskEmail($rawEmail);
        $maskedPhone = $this->maskPhone($rawPhone);

        $package = $order ? $order->package : null;
        $range = $order ? \App\Support\Studio::parseRange($order->session_time_slot) : null;

        $data = [
            'order_code' => $order ? $order->order_code : 'CH-CONTOH-001',
            'invoice_number' => $order ? $order->invoice_number : 'INV-CH-2026-001',
            'package_name' => $order ? $order->package_name : 'Prewedding',
            'package_price' => $order ? 'Rp' . number_format($order->total_price, 0, ',', '.') : 'Rp2.000.000',
            'package_image' => $package ? $package->imageSource() : 'paket-2.jpg',
            'package_duration' => $range ? ($range[1] - $range[0]) : 120,
            'package_desc' => $package ? (string) $package->description : '',
            'lokasi_hint' => 'Lokasi sesi dibahas saat perencanaan. Biaya transport di luar harga paket.',
            'package_items' => $package && is_array($package->features) ? $package->features : [
                'Durasi Foto: 2 Jam',
                '30 Edited Photo',
                'All Photo via Google Drive',
                'Pengerjaan: 7-10 hari kerja',
            ],
            'session_date' => $order && $order->session_date ? \App\Support\Studio::dateLabel($order->session_date) : 'Sabtu, 21 November 2026',
            'session_time' => $order ? $order->session_time_slot : '09.00–11.00 WIB · 120 menit',
            'nama_lengkap' => $order ? $order->customer_name : 'Ayu Pramesti',
            'email' => $rawEmail,
            'whatsapp' => $rawPhone,
            'lokasi' => $order ? $order->location : 'Studio Naro Capture',
            'catatan' => $order ? $order->customer_notes : 'Contoh catatan pemesan.',
        ];

        $hasSearched = $order !== null || (!empty($manualStatus) && !$notFound);

        return view('cek-pesanan', compact(
            'data',
            'searchCode',
            'searchContact',
            'statusKey',
            'maskedEmail',
            'maskedPhone',
            'hasSearched',
            'notFound'
        ));
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
