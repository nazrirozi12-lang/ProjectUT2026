<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\GalleryPhoto;
use App\Support\Studio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Galeri untuk pelanggan: galeri "Terbuka" dapat dilihat siapa saja yang punya tautan,
 * galeri "Privat" wajib memasukkan PIN yang dibuat studio.
 */
class ClientGalleryController extends Controller
{
    private function published(string $slug): ?Gallery
    {
        return Gallery::with('order')->where('slug', $slug)->where('status', 'Diterbitkan')->first();
    }

    private function isUnlocked(Gallery $g): bool
    {
        return $g->access_type === 'Terbuka' || in_array($g->id, (array) session('unlocked_galleries', []), true);
    }

    private function throttleKey(Request $request, Gallery $g): string
    {
        return 'gallery-pin:' . $request->ip() . ':' . $g->id;
    }

    public function show(Request $request)
    {
        $slug = (string) $request->query('slug', '');
        if ($slug === '') {
            return redirect()->route('cek-pesanan')->with('info', 'Buka galeri dari halaman status pesanan, atau gunakan tautan galeri yang dikirim studio.');
        }

        $gallery = $this->published($slug);
        if (!$gallery) {
            return response()->view('galeri-tidak-ditemukan', [], 404);
        }

        if (!$this->isUnlocked($gallery)) {
            return view('galeri-pin', ['gallery' => $gallery, 'locked' => RateLimiter::tooManyAttempts($this->throttleKey($request, $gallery), 5)]);
        }

        $photos = $gallery->photos()->orderBy('sort_order')->orderBy('id')->get(['id', 'caption', 'file_name', 'mime']);
        $order = $gallery->order;

        return view('galeri-klien', [
            'gallery' => $gallery,
            'photos' => $photos,
            'order' => $order,
            'sessionDate' => $order && $order->session_date ? Studio::dateLabel($order->session_date) : null,
        ]);
    }

    public function unlock(Request $request)
    {
        $data = $request->validate([
            'slug' => 'required|string|max:200',
            'pin' => 'required|string|max:20',
        ], ['pin.required' => 'Masukkan PIN galeri.']);

        $gallery = $this->published($data['slug']);
        if (!$gallery) {
            return response()->view('galeri-tidak-ditemukan', [], 404);
        }

        $key = $this->throttleKey($request, $gallery);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            return back()->withErrors(['pin' => "Terlalu banyak percobaan. Coba lagi dalam {$minutes} menit."]);
        }

        if ($gallery->access_type === 'Privat' && !hash_equals((string) $gallery->pin_code, trim($data['pin']))) {
            RateLimiter::hit($key, 600);
            return back()->withErrors(['pin' => 'PIN salah. Periksa kembali PIN yang diberikan studio.'])->withInput(['slug' => $gallery->slug]);
        }

        RateLimiter::clear($key);
        $request->session()->put('unlocked_galleries', array_values(array_unique(array_merge((array) session('unlocked_galleries', []), [$gallery->id]))));

        return redirect()->route('galeri-klien', ['slug' => $gallery->slug]);
    }

    /** Foto: hanya untuk galeri terbit yang sudah dibuka (PIN benar atau galeri terbuka). */
    public function photo(Request $request, string $slug, int $photo, string $variant = 'thumb')
    {
        $gallery = $this->published($slug);
        abort_unless($gallery && $this->isUnlocked($gallery), 404);

        $row = GalleryPhoto::where('gallery_id', $gallery->id)->findOrFail($photo);
        $data = $variant === 'full' ? $row->data : ($row->thumb ?: $row->data);
        abort_unless($data, 404);

        $download = $request->boolean('unduh');
        return response(base64_decode($data), 200, [
            'Content-Type' => $row->mime ?: 'image/jpeg',
            'Content-Disposition' => ($download ? 'attachment' : 'inline') . '; filename="' . addslashes($row->file_name ?: ('foto-' . $row->id . '.jpg')) . '"',
            'Cache-Control' => ($gallery->access_type === 'Terbuka' ? 'public' : 'private') . ', max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
