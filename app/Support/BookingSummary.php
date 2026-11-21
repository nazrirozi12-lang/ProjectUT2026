<?php

namespace App\Support;

use App\Models\Order;

/**
 * Ringkasan pesanan untuk halaman pelanggan (checkout, konfirmasi pembayaran, cek pesanan).
 * Dibentuk dari database sehingga selalu sama dengan data yang dilihat admin.
 */
class BookingSummary
{
    public static function fromOrder(Order $order): array
    {
        $order->loadMissing('package');
        $package = $order->package;
        $range = Studio::parseRange($order->session_time_slot);

        return [
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'invoice_number' => $order->invoice_number,
            'package_id' => $package ? $package->slug : null,
            'package_name' => $order->package_name,
            'package_price' => 'Rp' . number_format($order->total_price, 0, ',', '.'),
            'package_raw_price' => (int) $order->total_price,
            'package_image' => $package ? $package->imageSource() : 'paket-1.jpg',
            'package_desc' => $package ? (string) $package->description : '',
            'package_items' => $package && is_array($package->features) ? $package->features : [],
            'package_duration' => $range ? ($range[1] - $range[0]) : ($package ? (int) $package->duration_minutes : 60),
            'lokasi_hint' => 'Lokasi sesi dibahas saat perencanaan. Biaya transport di luar harga paket.',
            'session_date' => $order->session_date ? Studio::dateLabel($order->session_date) : '-',
            'session_time' => $order->session_time_slot,
            'nama_lengkap' => $order->customer_name,
            'email' => $order->customer_email,
            'whatsapp' => $order->customer_whatsapp,
            'lokasi' => $order->location,
            'catatan' => $order->customer_notes,
        ];
    }
}
