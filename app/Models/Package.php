<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'code',
        'name',
        'tagline',
        'description',
        'price',
        'duration',
        'duration_minutes',
        'photo_count',
        'photo_count_label',
        'features',
        'badge',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'price' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function image()
    {
        return $this->hasOne(PackageImage::class);
    }

    /** Foto bawaan (berkas di public/images) menurut slug paket. */
    public const DEFAULT_IMAGES = [
        'birthday-family-photoshoot' => 'paket-1.jpg',
        'prewedding' => 'paket-2.jpg',
        'graduation' => 'paket-3.jpg',
    ];

    protected static ?array $imageStamps = null;

    public static function forgetImageCache(): void
    {
        static::$imageStamps = null;
    }

    /**
     * Sumber foto kartu: URL foto unggahan admin bila ada,
     * jika tidak, berkas bawaan di public/images.
     */
    public function imageSource(): string
    {
        static::$imageStamps ??= PackageImage::query()->pluck('updated_at', 'package_id')->all();

        if (isset(static::$imageStamps[$this->id])) {
            $stamp = static::$imageStamps[$this->id];
            return route('package.image', ['slug' => $this->slug]) . '?v=' . (is_object($stamp) ? $stamp->timestamp : strtotime((string) $stamp));
        }

        return self::DEFAULT_IMAGES[$this->slug] ?? 'paket-1.jpg';
    }
}
