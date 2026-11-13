<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Cria (ou repõe) o único administrador do painel.
 * A palavra-passe temporária é gravada num ficheiro fora da raiz web (modo 600), nunca no terminal.
 */
class CreateAdmin extends Command
{
    protected $signature = 'geofloresta:admin {email} {--name=Administrador} {--out=}';

    protected $description = 'Cria o administrador do painel com palavra-passe temporária forte';

    public function handle(): int
    {
        $password = Str::password(24);

        $user = User::updateOrCreate(
            ['email' => $this->argument('email')],
            ['name' => $this->option('name'), 'password' => $password, 'is_admin' => true],
        );

        $out = $this->option('out') ?: (getenv('HOME') . '/geofloresta-secrets/admin.txt');
        file_put_contents($out, "email: {$user->email}\npalavra-passe temporária: {$password}\n");
        chmod($out, 0600);

        $this->info("Administrador {$user->email} pronto. Credenciais em {$out}");

        return self::SUCCESS;
    }
}
