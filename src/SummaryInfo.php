<?php

declare(strict_types=1);

namespace DevExtreme\Data;

use InvalidArgumentException;

final class SummaryInfo
{
    public const SUM = 'sum';
    public const MIN = 'min';
    public const MAX = 'max';
    public const AVG = 'avg';
    public const COUNT = 'count';

    public function __construct(
        public string $selector,
        public string $summaryType,
    ) {
    }

    public function isBuiltIn(): bool
    {
        return in_array($this->summaryType, [self::SUM, self::MIN, self::MAX, self::AVG, self::COUNT], true);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $type = $data['summaryType'] ?? null;
        if (!is_string($type) || $type === '') {
            throw new InvalidArgumentException('A summary item requires a "summaryType".');
        }

        $selector = $data['selector'] ?? '';

        return new self(is_string($selector) ? $selector : '', $type);
    }
}
