<?php

declare(strict_types=1);

namespace DevExtreme\Data\Sql;

use InvalidArgumentException;

/**
 * Maps client field names to SQL expressions. This is the SQL-injection boundary:
 * field names coming from the client are never interpolated unless they are
 * a whitelisted map key or a plain identifier.
 */
final class ColumnResolver
{
    /**
     * @param array<string, string>|null $columns field => trusted SQL expression. When given, it is a strict whitelist.
     */
    public function __construct(
        private readonly Dialect $dialect,
        private readonly ?array $columns = null,
    ) {
    }

    /**
     * @throws InvalidArgumentException for unknown or malformed field names
     */
    public function resolve(string $field): string
    {
        if ($this->columns !== null) {
            if (array_key_exists($field, $this->columns)) {
                return $this->columns[$field];
            }

            foreach ($this->columns as $name => $expression) {
                if (strcasecmp($name, $field) === 0) {
                    return $expression;
                }
            }

            throw new InvalidArgumentException(sprintf('Unknown field "%s".', $field));
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid field name "%s".', $field));
        }

        return $this->dialect->quoteIdentifier($field);
    }

    /**
     * The whitelisted field names, or null when any plain identifier is allowed.
     *
     * @return list<string>|null
     */
    public function knownFields(): ?array
    {
        return $this->columns === null ? null : array_keys($this->columns);
    }
}
