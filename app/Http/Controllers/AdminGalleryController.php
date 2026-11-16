<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\Order;
use App\Support\Studio;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminGalleryController extends Controller
{
    private const STATUSES = ['Draf', 'Penyuntingan', 'Siap', 'Diterbitkan'];
    private const ACCESS = ['Privat', 'Terbuka'];

    /** Pesanan yang boleh dibuatkan galeri: sesi dikonfirmasi/selesai dan belum punya galeri. */
    private function eligibleOrders(?int $includeOrderId = null)
    {
        return Order::query()
            ->whereIn('session_status', ['Dikonfirmasi', 'Selesai'])
            ->where(function ($q) use ($includeOrderId) {
                $q->whereDoesntHave('gallery');
                if ($includeOrderId) {
                    $q->orWhere('id', $includeOrderId);
                }
            })
            ->orderByDesc('id')->get(['id', 'order_code', 'customer_name', 'package_name']);
    }

    public function index(Request $request)
    {
        $activeTab = $request->query('tab', 'semua');
        $search = trim((string) $request->query('q', ''));
        $period = $request->query('bulan');

        $counts = ['semua' => Gallery::count()];
        foreach (self::STATUSES as $st) {
            $counts[Str::lower($st)] = Gallery::where('status', $st)->count();
        }

        $query = Gallery::with('order')->latest('id');
        if ($activeTab !== 'semua' && in_array(Str::ucfirst($activeTab), self::STATUSES, true)) {
            $query->where('status', Str::ucfirst($activeTab));
        }
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereLikeCi('title', $like)->orWhereLikeCi('client_name', $like)
                    ->orWhereHas('order', fn ($o) => $o->whereLikeCi('order_code', $like));
            });
        }
        if ($period && preg_match('/^\d{4}-\d{2}$/', $period)) {
            $query->whereYear('created_at', substr($period, 0, 4))->whereMonth('created_at', substr($period, 5, 2));
        }

        $paginator = $query->paginate(10)->withQueryString();
        $galleries = $paginator->getCollection()->map(fn (Gallery $g) => [
            'id' => $g->id,
            'title' => $g->title,
            'order_code' => $g->order ? $g->order->order_code : '—',
            'owner' => $g->client_name,
            'photos' => $g->photo_count . ' / ' . $g->target_photos,
            'note' => $g->drive_url ? 'Tautan Drive terpasang' : 'Belum ada tautan Drive',
            'status' => $g->status,
            'access' => $g->access_type,
            'pin' => $g->pin_code,
            'drive_url' => $g->drive_url,
            'notes' => $g->notes,
            'photo_count' => $g->photo_count,
            'target' => $g->target_photos,
            'order_id' => $g->order_id,
            'order_label' => $g->order ? $g->order->order_code . ' · ' . $g->order->customer_name : null,
        ])->all();

        $periods = Gallery::query()->pluck('created_at')->map(fn ($d) => $d->format('Y-m'))->push(Studio::today()->format('Y-m'))->unique()->sort()->values()
            ->map(fn ($ym) => ['value' => $ym, 'label' => Studio::dateLabel($ym . '-01', 'F Y')])->all();

        return view('admin.galeri', [
            'galleries' => $galleries, 'activeTab' => $activeTab, 'counts' => $counts, 'paginator' => $paginator,
            'search' => $search, 'period' => $period, 'periods' => $periods,
            'orders' => $this->eligibleOrders(),
            'statuses' => self::STATUSES, 'accessTypes' => self::ACCESS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'order_id' => ['required', 'integer', Rule::exists('orders', 'id')->where(fn ($q) => $q->whereIn('session_status', ['Dikonfirmasi', 'Selesai']))],
            'target_photos' => 'nullable|integer|min:1|max:1000',
            'access_type' => ['required', Rule::in(self::ACCESS)],
        ], [
            'title.required' => 'Judul galeri wajib diisi.',
            'order_id.required' => 'Pilih pesanan terkait.',
            'order_id.exists' => 'Pesanan harus berstatus sesi dikonfirmasi atau selesai.',
            'target_photos.min' => 'Target jumlah foto minimal 1.',
        ]);

        $order = Order::findOrFail($data['order_id']);
        if (Gallery::where('order_id', $order->id)->exists()) {
            return back()->withInput()->withErrors(['order_id' => 'Pesanan ini sudah memiliki galeri.']);
        }

        $gallery = Gallery::create([
            'slug' => Str::slug($data['title']) . '-' . Str::lower(Str::random(5)),
            'title' => $data['title'],
            'order_id' => $order->id,
            'client_name' => $order->customer_name,
            'target_photos' => $data['target_photos'] ?? 20,
            'status' => 'Draf',
            'access_type' => $data['access_type'],
            'pin_code' => $data['access_type'] === 'Privat' ? (string) random_int(100000, 999999) : null,
            'photo_count' => 0,
        ]);

        return redirect()->route('admin.galeri', ['tab' => 'draf'])
            ->with('success', 'Galeri draf “' . $gallery->title . '” berhasil dibuat. Tambahkan tautan Google Drive lalu terbitkan saat siap.');
    }

    public function update(Request $request, $id)
    {
        $gallery = Gallery::findOrFail($id);
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'status' => ['required', Rule::in(self::STATUSES)],
            'access_type' => ['required', Rule::in(self::ACCESS)],
            'target_photos' => 'required|integer|min:1|max:1000',
            'photo_count' => 'required|integer|min:0|max:100000',
            'drive_url' => ['nullable', 'url', 'max:500', Rule::requiredIf(fn () => $request->input('status') === 'Diterbitkan')],
            'notes' => 'nullable|string|max:1000',
            'reset_pin' => 'nullable|boolean',
        ], [
            'title.required' => 'Judul galeri wajib diisi.',
            'drive_url.url' => 'Tautan Google Drive tidak valid. Awali dengan https://',
            'drive_url.required' => 'Tautan Google Drive wajib diisi untuk menerbitkan galeri.',
            'target_photos.min' => 'Target jumlah foto minimal 1.',
        ]);

        $pin = $gallery->pin_code;
        if ($data['access_type'] === 'Privat' && (!$pin || $request->boolean('reset_pin'))) {
            $pin = (string) random_int(100000, 999999);
        }
        if ($data['access_type'] === 'Terbuka') {
            $pin = null;
        }

        $gallery->update([
            'title' => $data['title'],
            'status' => $data['status'],
            'access_type' => $data['access_type'],
            'target_photos' => $data['target_photos'],
            'photo_count' => $data['photo_count'],
            'drive_url' => $data['drive_url'] ?? null,
            'notes' => $data['notes'] ?? null,
            'pin_code' => $pin,
        ]);

        return redirect()->route('admin.galeri', ['tab' => Str::lower($gallery->status)])
            ->with('success', 'Galeri “' . $gallery->title . '” berhasil diperbarui (status: ' . $gallery->status . ').');
    }

    public function destroy($id)
    {
        $gallery = Gallery::findOrFail($id);
        $title = $gallery->title;
        $gallery->delete();

        return redirect()->route('admin.galeri')->with('success', 'Galeri “' . $title . '” berhasil dihapus.');
    }
}
