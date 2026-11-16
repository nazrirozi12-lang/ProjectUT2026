<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_code',
        'name',
        'email',
        'whatsapp',
        'notes',
        'status',
    ];

    public static function findByCodeOrFail(string|int $key): self
    {
        return static::query()
            ->where('customer_code', (string) $key)
            ->when(ctype_digit((string) $key), fn ($q) => $q->orWhere('id', (int) $key))
            ->firstOrFail();
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}

