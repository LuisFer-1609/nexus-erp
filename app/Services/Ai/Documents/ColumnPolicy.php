<?php

namespace App\Services\Ai\Documents;

use App\Services\Ai\Schema\ColumnDefinition;
use Illuminate\Support\Str;

/**
 * Decide qué columnas pueden llegar al asistente. Las contraseñas, tokens,
 * datos personales sensibles y columnas de auditoría se excluyen siempre, sin
 * importar la tabla: se aplica por nombre de columna, así que también cubre
 * tablas y columnas que se agreguen en el futuro.
 */
final class ColumnPolicy
{
    /** @var list<string> */
    private readonly array $sensitive;

    /** @var list<string> */
    private readonly array $skip;

    public function __construct()
    {
        /** @var list<string> $sensitive */
        $sensitive = (array) config('nexus_ai.sync.sensitive_columns');
        /** @var list<string> $skip */
        $skip = (array) config('nexus_ai.sync.skip_columns');

        $this->sensitive = array_map(Str::lower(...), $sensitive);
        $this->skip = array_map(Str::lower(...), $skip);
    }

    public function isSensitive(ColumnDefinition $column): bool
    {
        return Str::is($this->sensitive, Str::lower($column->name));
    }

    /** Columnas que el asistente puede consultar (todas salvo sensibles, binarias y de auditoría). */
    public function isQueryable(ColumnDefinition $column): bool
    {
        return ! $column->isBinary()
            && ! $this->isSensitive($column)
            && ! in_array(Str::lower($column->name), $this->skip, true);
    }

    /** Columnas que no deben mostrarse como dato del registro. */
    public function isHidden(ColumnDefinition $column): bool
    {
        return $column->isPrimary
            || $column->isBinary()
            || $this->isSensitive($column)
            || in_array(Str::lower($column->name), $this->skip, true);
    }
}
