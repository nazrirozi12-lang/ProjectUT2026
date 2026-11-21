<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalleryPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'gallery_id',
        'photo_path',
        'caption',
        'sort_order',
        'mime',
        'file_name',
        'data',
        'thumb',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function gallery()
    {
        return $this->belongsTo(Gallery::class);
    }
}

