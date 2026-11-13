<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Prova anónima de consentimento: identificador aleatório, versão do texto, categorias e data. */
class ConsentLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['categories' => 'array', 'created_at' => 'datetime'];
    }
}
