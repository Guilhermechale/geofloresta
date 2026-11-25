<?php

namespace App\Http\Controllers;

use App\Mail\ContactConfirmation;
use App\Mail\ContactReceived;
use App\Models\BlockedSender;
use App\Models\ContactMessage;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Formulário de contacto: grava SEMPRE na base de dados e depois tenta enviar e-mail.
 * Antispam sem serviços externos: campo isco, tempo mínimo de preenchimento e limite por IP (hash).
 */
class ContactController extends Controller
{
    public const SUBJECTS = ['proposal', 'data', 'services', 'partnership', 'other'];

    public const MIN_SECONDS = 4;

    public function show(Request $request): View
    {
        return view('pages.contact', [
            'formToken' => Crypt::encryptString((string) now()->timestamp),
            'email' => $this->email(),
            'subject' => in_array($request->query('assunto'), self::SUBJECTS, true) ? $request->query('assunto') : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $locale = app()->getLocale();

        // 1) Campo isco: bots preenchem-no. Fingimos sucesso, sem gravar.
        if (filled($request->input('website'))) {
            return redirect()->to(lroute('thanks'));
        }

        // 2) Tempo mínimo entre abrir a página e enviar
        try {
            $started = (int) Crypt::decryptString((string) $request->input('_t'));
        } catch (\Throwable) {
            $started = 0;
        }
        if (! $started || now()->timestamp - $started < self::MIN_SECONDS) {
            throw ValidationException::withMessages(['message' => __('pages.contact.too_fast')]);
        }

        // 3) Limite: 5 mensagens por hora por ligação (IP em hash)
        $ipHash = ContactMessage::hashIp($request->ip());
        $key = 'contact:'.$ipHash;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['message' => __('pages.contact.too_many', ['email' => $this->email()])]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'organisation' => ['nullable', 'string', 'max:190'],
            'subject' => ['required', 'in:'.implode(',', self::SUBJECTS)],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        RateLimiter::hit($key, 3600);

        $msg = ContactMessage::create([
            ...$data,
            'subject' => __('pages.contact.subjects.'.$data['subject'], [], 'pt'),
            'locale' => $locale,
            'ip_hash' => $ipHash,
        ]);

        // Remetente bloqueado ou domínio que imita o nosso: vai para o spam, sem e-mails (nem para nós, nem de resposta).
        $reason = BlockedSender::isBlocked($msg->email) ? 'blocked' : (BlockedSender::isLookalike($msg->email) ? 'lookalike' : null);
        if ($reason) {
            $msg->markSpam($reason);

            return redirect()->to(lroute('thanks'))->with('contact_sent', true);
        }

        // E-mail para a GEOFLORESTA e confirmação no idioma usado. Falhas não perdem a mensagem.
        try {
            Mail::to($this->email())->send(new ContactReceived($msg));
            $msg->forceFill(['emailed_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::warning('Contacto: e-mail para a GEOFLORESTA falhou', ['id' => $msg->id, 'erro' => $e->getMessage()]);
        }
        try {
            Mail::to($msg->email)->locale($locale)->send(new ContactConfirmation($msg));
            $msg->forceFill(['confirmation_sent_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::warning('Contacto: confirmação falhou', ['id' => $msg->id, 'erro' => $e->getMessage()]);
        }

        return redirect()->to(lroute('thanks'))->with('contact_sent', true);
    }

    public function thanks(Request $request): View
    {
        return view('pages.thanks', ['sent' => (bool) $request->session()->get('contact_sent')]);
    }

    protected function email(): string
    {
        return (string) (Setting::get('contact_email') ?: config('geofloresta.contact_email'));
    }
}
