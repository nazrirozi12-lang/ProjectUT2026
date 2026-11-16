<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageImage extends Model
{
    protected $fillable = ['package_id', 'mime', 'data'];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
