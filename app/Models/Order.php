<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_code',
        'invoice_number',
        'customer_id',
        'package_id',
        'customer_name',
        'customer_email',
        'customer_whatsapp',
        'package_name',
        'total_price',
        'session_date',
        'session_time_slot',
        'location',
        'customer_notes',
        'internal_notes',
        'session_status',
        'payment_status',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'archived_at',
        'cancel_reason',
    ];

    protected $casts = [
        'session_date' => 'date',
        'total_price' => 'integer',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    /** Cari pesanan lewat kode (CH-...) atau id numerik; gagal -> ModelNotFoundException. */
    public static function findByCodeOrFail(string|int $key): self
    {
        return static::query()
            ->where('order_code', (string) $key)
            ->when(ctype_digit((string) $key), fn ($q) => $q->orWhere('id', (int) $key))
            ->firstOrFail();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment()
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function studioSlots()
    {
        return $this->hasMany(StudioSlot::class);
    }

    public function gallery()
    {
        return $this->hasOne(Gallery::class);
    }
}

