<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spam automático com o motivo guardado; a função de bloquear remetentes foi retirada. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $t) {
            $t->string('spam_note', 255)->nullable()->after('spam_reason');
        });
        Schema::dropIfExists('blocked_senders');
    }

    public function down(): void
    {
        Schema::table('contact_messages', fn (Blueprint $t) => $t->dropColumn('spam_note'));
        Schema::create('blocked_senders', function (Blueprint $t) {
            $t->id();
            $t->string('value', 190)->unique();
            $t->string('kind', 8);
            $t->string('note', 255)->nullable();
            $t->timestamps();
        });
    }
};
