<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogAktivitas extends Model
{
    use HasFactory;

    protected $table = 'log_aktivitas';

    protected $fillable = [
        'user_id',
        'modul',
        'aktivitas',
        'ip_address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function catat(string $modul, string $aktivitas): self
    {
        return self::create([
            'user_id' => auth()->id(),
            'modul' => $modul,
            'aktivitas' => $aktivitas,
            'ip_address' => request()->ip(),
        ]);
    }
}
