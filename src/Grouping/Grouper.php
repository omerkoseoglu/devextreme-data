<?php

declare(strict_types=1);

namespace DevExtreme\Data\Grouping;

use DevExtreme\Data\Group;
use DevExtreme\Data\GroupingInfo;
use DevExtreme\Data\Support\Accessor;

/**
 * Groups rows into a (nested) list of {@see Group}s, preserving first-seen order.
 */
final class Grouper
{
    /**
     * @param iterable<mixed>     $rows
     * @param non-empty-list<GroupingInfo> $levels
     *
     * @return list<Group>
     */
    public static function group(iterable $rows, array $levels): array
    {
        $groups = self::groupLevel($rows, $levels[0]);

        if (count($levels) > 1) {
            $rest = array_slice($levels, 1);
            foreach ($groups as $group) {
                $group->items = self::group($group->items ?? [], $rest);
            }
        }

        return $groups;
    }

    /**
     * @param iterable<mixed> $rows
     *
     * @return list<Group>
     */
    private static function groupLevel(iterable $rows, GroupingInfo $info): array
    {
        /** @var array<string, Group> $index */
        $index = [];
        /** @var list<Group> $groups */
        $groups = [];

        foreach ($rows as $row) {
            $key = GroupKey::compute(Accessor::read($row, $info->selector), $info);
            $id = GroupKey::identity($key);

            if (!isset($index[$id])) {
                $index[$id] = new Group($key, []);
                $groups[] = $index[$id];
            }

            $index[$id]->items[] = $row;
        }

        return $groups;
    }
}
