<?php

declare(strict_types=1);

namespace DevExtreme\Data;

use JsonSerializable;

/**
 * A load result, in the exact shape the DevExtreme client expects.
 */
final class LoadResult implements JsonSerializable
{
    /**
     * @param list<mixed>|null $data      Resulting dataset (rows or top-level {@see Group}s).
     * @param int              $totalCount Total number of data objects, -1 when not requested.
     * @param int              $groupCount Number of top-level groups, -1 when not requested.
     * @param list<mixed>|null $summary   Total summary values.
     */
    public function __construct(
        public ?array $data = null,
        public int $totalCount = -1,
        public int $groupCount = -1,
        public ?array $summary = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'data' => $this->data === null ? null : array_map(
                static fn (mixed $item): mixed => $item instanceof Group ? $item->toArray() : $item,
                $this->data,
            ),
            'totalCount' => $this->totalCount,
            'groupCount' => $this->groupCount,
        ];

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

    /**
     * @throws \JsonException
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this, $flags | JSON_THROW_ON_ERROR);
    }
}
