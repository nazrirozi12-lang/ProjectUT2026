<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    /** Ambil satu nilai pengaturan (aman bila tabel belum ada). */
    public static function get(string $key, $default = null)
    {
        try {
            $row = static::query()->find($key);
            return $row && $row->value !== null && $row->value !== '' ? $row->value : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public static function put(string $key, $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
