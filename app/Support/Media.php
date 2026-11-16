<?php

namespace App\Support;

class Media
{
    /** Nama berkas di public/images ATAU URL penuh -> URL siap pakai di <img>. */
    public static function pkg(?string $value): string
    {
        $value = $value ?: 'paket-1.jpg';
        if (preg_match('#^(https?:)?//#', $value) || str_starts_with($value, '/')) {
            return $value;
        }
        return asset('images/' . $value);
    }
}
