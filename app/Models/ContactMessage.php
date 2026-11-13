<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['emailed_at' => 'datetime', 'confirmation_sent_at' => 'datetime', 'read_at' => 'datetime'];
    }

    /** Hash com sal do IP: permite limitar abusos sem guardar o IP. */
    public static function hashIp(?string $ip): ?string
    {
        return $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null;
    }
}
