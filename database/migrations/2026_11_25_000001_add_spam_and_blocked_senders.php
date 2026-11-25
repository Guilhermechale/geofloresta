<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spam nas mensagens de contacto e lista de remetentes bloqueados (e-mail ou domínio). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $t) {
            $t->timestamp('spam_at')->nullable()->after('read_at');
            $t->string('spam_reason', 40)->nullable()->after('spam_at'); // manual | blocked | lookalike
        });
        Schema::create('blocked_senders', function (Blueprint $t) {
            $t->id();
            $t->string('value', 190)->unique();   // endereço completo ou domínio (sem @)
            $t->string('kind', 8);                // email | domain
            $t->string('note', 255)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_senders');
        Schema::table('contact_messages', fn (Blueprint $t) => $t->dropColumn(['spam_at', 'spam_reason']));
    }
};
