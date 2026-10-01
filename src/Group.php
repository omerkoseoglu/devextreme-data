<?php

declare(strict_types=1);

namespace DevExtreme\Data;

use DateTimeInterface;
use JsonSerializable;

final class Group implements JsonSerializable
{
    /**
     * @param list<mixed>|null $items  Subgroups or data objects; `null` when the group is collapsed.
     * @param list<mixed>|null $summary
     */
    public function __construct(
        public mixed $key = null,
        public ?array $items = null,
        public ?int $count = null,
        public ?array $summary = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $key = $this->key instanceof DateTimeInterface ? $this->key->format(DATE_ATOM) : $this->key;

        $result = [
            'key' => $key,
            'items' => $this->items === null ? null : array_map(
                static fn (mixed $item): mixed => $item instanceof self ? $item->toArray() : $item,
                $this->items,
            ),
        ];

        if ($this->count !== null) {
            $result['count'] = $this->count;
        }

        if ($this->summary !== null) {
            $result['summary'] = $this->summary;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
