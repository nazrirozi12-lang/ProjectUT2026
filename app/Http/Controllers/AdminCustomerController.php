<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\Studio;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminCustomerController extends Controller
{
    private function rupiah($n): string
    {
        return 'Rp' . number_format((int) $n, 0, ',', '.');
    }

    public function index(Request $request)
    {
        $activeTab = $request->query('tab', 'aktif');
        $search = trim((string) $request->query('q', ''));

        $counts = [
            'aktif' => Customer::where('status', '!=', 'Diarsipkan')->count(),
            'diarsipkan' => Customer::where('status', 'Diarsipkan')->count(),
        ];
        $counts['semua'] = $counts['aktif'] + $counts['diarsipkan'];

        $query = Customer::query()
            ->withCount(['orders', 'orders as active_orders_count' => fn ($q) => $q->whereIn('session_status', ['Permintaan', 'Dikonfirmasi'])])
            ->latest('id');

        match ($activeTab) {
            'diarsipkan' => $query->where('status', 'Diarsipkan'),
            'semua' => null,
            default => $query->where('status', '!=', 'Diarsipkan'),
        };

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where(fn ($q) => $q->whereLikeCi('name', $like)->orWhereLikeCi('email', $like)
                ->orWhereLikeCi('customer_code', $like)->orWhereLikeCi('whatsapp', $like));
        }

        $paginator = $query->paginate(10)->withQueryString();
        $customers = $paginator->getCollection()->map(fn (Customer $c) => [
            'name' => $c->name,
            'id' => $c->customer_code,
            'email' => $c->email,
            'whatsapp' => $c->whatsapp,
            'history' => $c->orders_count . ' pesanan' . ($c->active_orders_count ? ' · ' . $c->active_orders_count . ' aktif' : ''),
            'status' => $c->status === 'Diarsipkan' ? 'Diarsipkan' : 'Aktif',
        ])->all();

        return view('admin.pelanggan', compact('customers', 'activeTab', 'counts', 'paginator', 'search'));
    }

    public function show($id)
    {
        $c = Customer::findByCodeOrFail($id);
        $c->load(['orders' => fn ($q) => $q->latest('id')]);

        $orders = $c->orders;
        $verified = $orders->where('payment_status', 'Terverifikasi')->where('session_status', '!=', 'Dibatalkan');
        $upcoming = $orders->whereIn('session_status', ['Permintaan', 'Dikonfirmasi'])
            ->filter(fn ($o) => $o->session_date && $o->session_date->gte(Studio::today()))->sortBy('session_date')->first();

        $digits = preg_replace('/\D+/', '', (string) $c->whatsapp);
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }

        $customer = [
            'id' => $c->id, 'code' => $c->customer_code, 'name' => $c->name, 'email' => $c->email,
            'whatsapp' => $c->whatsapp, 'wa_link' => $digits ? 'https://wa.me/' . $digits : null,
            'notes' => $c->notes, 'status' => $c->status === 'Diarsipkan' ? 'Diarsipkan' : 'Aktif',
            'since' => Studio::dateLabel($c->created_at, 'j F Y'),
            'order_count' => $orders->count(),
            'active_count' => $orders->whereIn('session_status', ['Permintaan', 'Dikonfirmasi'])->count(),
            'spent' => $this->rupiah($verified->sum('total_price')),
            'last_order' => $orders->first() ? Studio::dateLabel($orders->first()->created_at, 'j M Y') : '-',
            'upcoming' => $upcoming ? Studio::dateLabel($upcoming->session_date, 'l, j F Y') . ' · ' . $upcoming->package_name : null,
            'can_archive' => $c->status !== 'Diarsipkan',
            'orders' => $orders->map(fn ($o) => [
                'code' => $o->order_code, 'package' => $o->package_name, 'value' => $this->rupiah($o->total_price),
                'date' => $o->session_date ? Studio::dateLabel($o->session_date, 'd M Y') : '-',
                'session_status' => $o->session_status, 'payment_status' => $o->payment_status,
            ])->all(),
        ];

        return view('admin.pelanggan-detail', compact('customer'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:customers,email',
            'whatsapp' => 'required|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ], [
            'name.required' => 'Nama pelanggan wajib diisi.',
            'email.required' => 'Email pelanggan wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar sebagai pelanggan.',
            'whatsapp.required' => 'Nomor WhatsApp wajib diisi.',
        ]);

        do {
            $code = 'PLG-' . date('Y') . '-' . strtoupper(Str::random(4));
        } while (Customer::where('customer_code', $code)->exists());

        $c = Customer::create($data + ['customer_code' => $code, 'status' => 'Aktif']);

        return redirect()->route('admin.pelanggan.detail', ['id' => $c->customer_code])
            ->with('success', 'Pelanggan “' . $c->name . '” berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $c = Customer::findByCodeOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($c->id)],
            'whatsapp' => 'required|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ], [
            'name.required' => 'Nama pelanggan wajib diisi.',
            'email.unique' => 'Email ini sudah dipakai pelanggan lain.',
            'whatsapp.required' => 'Nomor WhatsApp wajib diisi.',
        ]);
        $c->update($data);

        return redirect()->route('admin.pelanggan.detail', ['id' => $c->customer_code])
            ->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    public function arsipkan($id)
    {
        $c = Customer::findByCodeOrFail($id);
        if ($c->orders()->whereIn('session_status', ['Permintaan', 'Dikonfirmasi'])->exists()) {
            return back()->with('error', 'Pelanggan masih memiliki pesanan aktif sehingga belum dapat diarsipkan.');
        }
        $c->update(['status' => 'Diarsipkan']);

        return redirect()->route('admin.pelanggan', ['tab' => 'diarsipkan'])->with('success', 'Pelanggan “' . $c->name . '” dipindahkan ke arsip.');
    }

    public function aktifkan($id)
    {
        $c = Customer::findByCodeOrFail($id);
        $c->update(['status' => 'Aktif']);

        return redirect()->route('admin.pelanggan.detail', ['id' => $c->customer_code])->with('success', 'Pelanggan “' . $c->name . '” diaktifkan kembali.');
    }
}
