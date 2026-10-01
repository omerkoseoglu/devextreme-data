<?php

declare(strict_types=1);

namespace DevExtreme\Data;

/**
 * A grouping level.
 */
final class GroupingInfo extends SortingInfo
{
    public function __construct(
        string $selector,
        bool $desc = false,
        /** Groups data in ranges of a given length ("10", "0.5") or date/time periods ("year", "month", ...). */
        public ?string $groupInterval = null,
        /** Whether the group's data objects should be returned. `null` means `true`. */
        public ?bool $isExpanded = null,
    ) {
        parent::__construct($selector, $desc);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $base = SortingInfo::fromArray($data);

        $interval = $data['groupInterval'] ?? null;
        if ($interval !== null) {
            $interval = is_float($interval) ? rtrim(rtrim(sprintf('%.10F', $interval), '0'), '.') : (string) $interval;
            if ($interval === '') {
                $interval = null;
            }
        }

        $expanded = $data['isExpanded'] ?? null;
        if ($expanded !== null) {
            $expanded = self::toBool($expanded);
        }

        return new self($base->selector, $base->desc, $interval, $expanded);
    }

    public function getIsExpanded(): bool
    {
        return $this->isExpanded ?? true;
    }

    public function hasNumericInterval(): bool
    {
        return $this->groupInterval !== null && is_numeric($this->groupInterval);
    }
}
