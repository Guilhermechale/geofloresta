<?php

namespace App\Geo\Writers;

/** Campo de atributos: nome curto (≤10 caracteres ASCII, por causa do Shapefile), tipo e largura. */
final class Field
{
    public function __construct(
        public readonly string $name,
        public readonly string $type = 'string', // string | int | float | date
        public readonly int $length = 80,
        public readonly int $decimals = 0,
        public readonly ?string $description = null,
    ) {
        if (! preg_match('/^[a-z][a-z0-9_]{0,9}$/', $name)) {
            throw new \InvalidArgumentException("Nome de campo inválido para Shapefile: {$name}");
        }
    }

    public static function string(string $name, int $length = 80, ?string $d = null): self { return new self($name, 'string', $length, 0, $d); }
    public static function int(string $name, int $length = 10, ?string $d = null): self { return new self($name, 'int', $length, 0, $d); }
    public static function float(string $name, int $decimals = 4, int $length = 18, ?string $d = null): self { return new self($name, 'float', $length, $decimals, $d); }
    public static function date(string $name, ?string $d = null): self { return new self($name, 'date', 10, 0, $d); }
}
