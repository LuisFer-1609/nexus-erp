<?php

namespace App\Services\Ai\Schema;

final class ColumnDefinition
{
    private const TEXT_TYPES = ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'enum', 'set'];

    private const NUMBER_TYPES = ['decimal', 'float', 'double', 'int', 'integer', 'smallint', 'mediumint', 'bigint', 'tinyint'];

    private const DATE_TYPES = ['date', 'datetime', 'timestamp'];

    private const BINARY_TYPES = ['blob', 'tinyblob', 'mediumblob', 'longblob', 'binary', 'varbinary'];

    public function __construct(
        public readonly string $name,
        public readonly string $dataType,
        public readonly string $columnType,
        public readonly bool $isPrimary,
    ) {}

    public function isBoolean(): bool
    {
        return $this->columnType === 'tinyint(1)';
    }

    public function isText(): bool
    {
        return in_array($this->dataType, self::TEXT_TYPES, true);
    }

    public function isNumber(): bool
    {
        return ! $this->isBoolean() && in_array($this->dataType, self::NUMBER_TYPES, true);
    }

    public function isDecimal(): bool
    {
        return in_array($this->dataType, ['decimal', 'float', 'double'], true);
    }

    public function isDate(): bool
    {
        return in_array($this->dataType, self::DATE_TYPES, true);
    }

    public function isBinary(): bool
    {
        return in_array($this->dataType, self::BINARY_TYPES, true);
    }

    /** Longitud declarada de un varchar/char, o null si no aplica. */
    public function length(): ?int
    {
        return preg_match('/\((\d+)\)/', $this->columnType, $m) === 1 ? (int) $m[1] : null;
    }
}
