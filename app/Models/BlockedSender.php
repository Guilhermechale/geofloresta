<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Remetente bloqueado no formulário de contacto: um endereço ou um domínio inteiro (e os subdomínios). */
class BlockedSender extends Model
{
    protected $guarded = ['id'];

    public static function domainOf(string $email): string
    {
        return strtolower(trim(substr(strrchr($email, '@') ?: '', 1)));
    }

    public static function isBlocked(string $email): bool
    {
        $email = strtolower(trim($email));
        $domain = self::domainOf($email);
        if ($domain === '') {
            return false;
        }
        $parts = explode('.', $domain);
        $domains = [];
        for ($i = 0; $i < count($parts) - 1; $i++) {
            $domains[] = implode('.', array_slice($parts, $i)); // a.b.c -> a.b.c, b.c
        }

        return self::query()->where(fn ($q) => $q->where(fn ($q) => $q->where('kind', 'email')->where('value', $email))
            ->orWhere(fn ($q) => $q->where('kind', 'domain')->whereIn('value', $domains)))->exists();
    }

    /** Domínio que imita o nosso (contém «geofloresta» mas não é geofloresta.com): típico de burlas. */
    public static function isLookalike(string $email): bool
    {
        $domain = self::domainOf($email);
        $own = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)) ?: 'geofloresta.com';
        $own = preg_replace('/^www\./', '', $own);

        return str_contains($domain, 'geofloresta') && $domain !== $own && ! str_ends_with($domain, '.'.$own) && $domain !== 'geofloresta.com' && ! str_ends_with($domain, '.geofloresta.com');
    }

    public static function block(string $email, string $kind, ?string $note = null): self
    {
        $value = $kind === 'domain' ? self::domainOf($email) : strtolower(trim($email));

        return self::firstOrCreate(['value' => $value], ['kind' => $kind, 'note' => $note]);
    }
}
