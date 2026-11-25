<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['emailed_at' => 'datetime', 'confirmation_sent_at' => 'datetime', 'read_at' => 'datetime', 'spam_at' => 'datetime'];
    }

    public function scopeInbox($q)
    {
        return $q->whereNull('spam_at');
    }

    public function scopeSpam($q)
    {
        return $q->whereNotNull('spam_at');
    }

    public function markSpam(string $reason = 'manual', ?string $note = null): void
    {
        $this->forceFill(['spam_at' => now(), 'spam_reason' => $reason, 'spam_note' => $note ? mb_substr($note, 0, 255) : null, 'read_at' => $this->read_at ?? now()])->save();
    }

    public function markNotSpam(): void
    {
        $this->forceFill(['spam_at' => null, 'spam_reason' => null, 'spam_note' => null])->save();
    }

    /** Hash com sal do IP: permite limitar abusos sem guardar o IP. */
    public static function hashIp(?string $ip): ?string
    {
        return $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null;
    }
}
