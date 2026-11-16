<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\PackageImage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminPackageController extends Controller
{
    private function durationLabel(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' menit';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $m === 0 ? $h . ' jam' : $h . ' jam ' . $m . ' menit';
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'price' => 'required|string',
            'duration_minutes' => 'required|integer|min:15|max:1440',
            'photo_count_label' => 'nullable|string|max:100',
            'tagline' => 'nullable|string|max:150',
            'description' => 'nullable|string|max:600',
            'features' => 'required|string|max:2000',
            'badge' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Nama paket wajib diisi.',
            'price.required' => 'Harga paket wajib diisi.',
            'duration_minutes.required' => 'Durasi sesi (menit) wajib diisi.',
            'duration_minutes.min' => 'Durasi sesi minimal 15 menit.',
            'features.required' => 'Isi minimal satu fasilitas (satu baris per fasilitas).',
            'image.image' => 'Berkas foto harus berupa gambar.',
            'image.mimes' => 'Foto paket harus berformat JPG, PNG, atau WEBP.',
            'image.max' => 'Ukuran foto paket maksimal 2 MB.',
        ];
    }

    private function parsed(Request $request, array $data): array
    {
        $price = (int) preg_replace('/\D+/', '', $data['price']);
        $features = collect(preg_split('/\R/u', $data['features']))->map(fn ($l) => trim($l))->filter()->map(fn ($l) => Str::limit($l, 140, ''))->take(15)->values()->all();

        return [
            'price' => $price,
            'features' => $features,
            'duration_minutes' => (int) $data['duration_minutes'],
            'duration' => $this->durationLabel((int) $data['duration_minutes']),
            'photo_count_label' => $data['photo_count_label'] ?? null,
            'tagline' => $data['tagline'] ?? null,
            'description' => $data['description'] ?? null,
            'badge' => $data['badge'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'is_featured' => $request->boolean('is_featured'),
        ];
    }

    private function saveImage(Package $package, Request $request): void
    {
        if ($request->boolean('remove_image')) {
            PackageImage::where('package_id', $package->id)->delete();
        }
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            PackageImage::updateOrCreate(['package_id' => $package->id], [
                'mime' => $file->getMimeType() ?: 'image/jpeg',
                'data' => base64_encode(file_get_contents($file->getRealPath())),
            ]);
        }
        Package::forgetImageCache();
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'semua');
        $search = trim((string) $request->query('q', ''));

        $query = Package::query()->orderBy('id');
        if ($status === 'aktif') {
            $query->where('is_active', true);
        } elseif ($status === 'nonaktif') {
            $query->where('is_active', false);
        }
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where(fn ($q) => $q->whereLikeCi('name', $like)->orWhereLikeCi('code', $like)->orWhereLikeCi('description', $like));
        }

        $all = Package::query();
        $counts = ['aktif' => Package::where('is_active', true)->count(), 'nonaktif' => Package::where('is_active', false)->count()];

        $packages = $query->withCount('orders')->get()->map(fn (Package $p) => [
            'id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'tagline' => $p->tagline, 'description' => $p->description,
            'price' => $p->price, 'price_label' => 'Rp' . number_format($p->price, 0, ',', '.'),
            'duration' => $p->duration, 'duration_minutes' => $p->duration_minutes, 'photo_label' => $p->photo_count_label,
            'features' => is_array($p->features) ? $p->features : [], 'badge' => $p->badge,
            'active' => (bool) $p->is_active, 'featured' => (bool) $p->is_featured,
            'image' => $p->imageSource(), 'orders_count' => $p->orders_count,
        ])->all();

        return view('admin.paket', compact('packages', 'status', 'search', 'counts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(), $this->messages());
        $parsed = $this->parsed($request, $data);

        if ($parsed['price'] < 0) {
            return back()->withInput()->withErrors(['price' => 'Harga tidak valid.']);
        }

        $base = Str::slug($data['name']) ?: 'paket';
        $slug = $base;
        while (Package::where('slug', $slug)->exists()) {
            $slug = $base . '-' . Str::lower(Str::random(4));
        }
        do {
            $code = 'PKG-' . strtoupper(Str::random(5));
        } while (Package::where('code', $code)->exists());

        $package = Package::create($parsed + [
            'slug' => $slug, 'code' => $code, 'name' => $data['name'],
            'photo_count' => 0,
        ]);
        $this->saveImage($package, $request);

        return redirect()->route('admin.paket')->with('success', 'Paket “' . $package->name . '” berhasil ditambahkan' . ($package->is_active ? ' dan tampil di halaman pelanggan.' : ' (nonaktif).'));
    }

    public function update(Request $request, $id)
    {
        $package = Package::findOrFail($id);
        $data = $request->validate($this->rules(), $this->messages());
        $package->update($this->parsed($request, $data) + ['name' => $data['name']]);
        $this->saveImage($package, $request);

        return redirect()->route('admin.paket')->with('success', 'Paket “' . $package->name . '” berhasil diperbarui.');
    }

    public function toggle($id)
    {
        $package = Package::findOrFail($id);
        $package->update(['is_active' => !$package->is_active]);

        return redirect()->route('admin.paket')->with('success', 'Paket “' . $package->name . '” ' . ($package->is_active ? 'diaktifkan dan tampil kembali ke pelanggan.' : 'dinonaktifkan dan disembunyikan dari pelanggan.'));
    }

    public function destroy($id)
    {
        $package = Package::withCount('orders')->findOrFail($id);
        if ($package->orders_count > 0) {
            return redirect()->route('admin.paket')->with('error', 'Paket “' . $package->name . '” sudah dipakai ' . $package->orders_count . ' pesanan sehingga tidak dapat dihapus. Nonaktifkan saja agar tidak tampil ke pelanggan.');
        }
        $name = $package->name;
        $package->delete();
        Package::forgetImageCache();

        return redirect()->route('admin.paket')->with('success', 'Paket “' . $name . '” berhasil dihapus.');
    }

    /** Foto kartu paket unggahan admin (publik, dipakai di halaman pelanggan). */
    public function image($slug)
    {
        $package = Package::where('slug', $slug)->firstOrFail();
        $img = PackageImage::where('package_id', $package->id)->firstOrFail();

        return response(base64_decode($img->data), 200, [
            'Content-Type' => $img->mime,
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
