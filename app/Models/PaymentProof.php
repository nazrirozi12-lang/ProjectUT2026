<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentProof extends Model
{
    protected $fillable = ['payment_id', 'mime', 'data'];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
