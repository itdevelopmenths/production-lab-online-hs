<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pengaturan global modul Analisa (key/value), mis. periode OUT impor. */
class AnalisaSetting extends Model
{
    protected $table = 'analisa_setting';

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $value = static::query()->where('key', $key)->value('value');

        return $value === null || $value === '' ? $default : (string) $value;
    }

    public static function setValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
