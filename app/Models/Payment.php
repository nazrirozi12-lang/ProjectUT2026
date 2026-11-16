<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_code',
        'order_id',
        'sender_name',
        'transfer_date',
        'bank_source',
        'nominal',
        'sender_notes',
        'proof_file',
        'file_original_name',
        'file_extension',
        'status',
        'internal_review_notes',
        'customer_message',
        'verified_at',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'verified_at' => 'datetime',
        'nominal' => 'integer',
    ];

    public static function findByCodeOrFail(string|int $key): self
    {
        return static::query()
            ->where('payment_code', (string) $key)
            ->when(ctype_digit((string) $key), fn ($q) => $q->orWhere('id', (int) $key))
            ->firstOrFail();
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function proof()
    {
        return $this->hasOne(PaymentProof::class);
    }
}

