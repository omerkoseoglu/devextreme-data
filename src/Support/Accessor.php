<?php

declare(strict_types=1);

namespace DevExtreme\Data\Support;

use ArrayAccess;

/**
 * Reads (possibly nested, dotted) fields from arrays and objects.
 *
 * Lookup order for a segment: exact key/property, case-insensitive key/property,
 * then getX()/isX()/x() methods and magic __get.
 */
final class Accessor
{
    public static function read(mixed $item, string $path): mixed
    {
        if ($path === '' || $path === 'this') {
            return $item;
        }

        if ($item === null) {
            return null;
        }

        // a key may legitimately contain dots (flat rows from SQL aliases: "customer.name")
        $found = false;
        $value = self::readSegment($item, $path, $found);
        if ($found) {
            return $value;
        }

        if (!str_contains($path, '.')) {
            return null;
        }

        $value = $item;
        foreach (explode('.', $path) as $segment) {
            if ($value === null) {
                return null;
            }

            $value = self::readSegment($value, $segment, $found);
            if (!$found) {
                return null;
            }
        }

        return $value;
    }

    private static function readSegment(mixed $container, string $key, bool &$found): mixed
    {
        $found = false;

        if (is_array($container)) {
            if (array_key_exists($key, $container)) {
                $found = true;

                return $container[$key];
            }

            foreach ($container as $k => $v) {
                if (is_string($k) && strcasecmp($k, $key) === 0) {
                    $found = true;

                    return $v;
                }
            }

            return null;
        }

        if (!is_object($container)) {
            return null;
        }

        if ($container instanceof ArrayAccess && $container->offsetExists($key)) {
            $found = true;

            return $container->offsetGet($key);
        }

        $vars = get_object_vars($container); // public properties only (we are outside the object scope)
        if (array_key_exists($key, $vars)) {
            $found = true;

            return $vars[$key];
        }

        foreach ($vars as $k => $v) {
            if (strcasecmp((string) $k, $key) === 0) {
                $found = true;

                return $v;
            }
        }

        foreach (['get' . $key, 'is' . $key, $key] as $method) {
            if (method_exists($container, $method) && is_callable([$container, $method])) {
                $found = true;

                return $container->$method();
            }
        }

        if (method_exists($container, '__get') && isset($container->$key)) {
            $found = true;

            return $container->$key;
        }

        return null;
    }
}
