<?php

declare(strict_types=1);

namespace App\Tenancy;

/**
 * Symlink helpers that also handle Windows directory junctions (what Laravel's
 * Filesystem::link() creates there): PHP's is_link() and is_dir() return false for a
 * junction, unlink() cannot remove it, and readlink() returns the path itself for an
 * entry that is not a link at all.
 */
final class StorageLink
{
    public static function isLink(string $path): bool
    {
        clearstatcache(true, $path);

        if (is_link($path)) {
            return true;
        }

        if (! windows_os()) {
            return false;
        }

        $target = @readlink($path);
        $parent = realpath(dirname($path));

        if ($target === false || $parent === false) {
            return false;
        }

        // A plain file or directory "reads" as its own final path.
        return strcasecmp(self::normalize($target), self::normalize($parent.DIRECTORY_SEPARATOR.basename($path))) !== 0;
    }

    /**
     * Removes the link only, never what it points to. Does nothing unless $path is a link.
     */
    public static function remove(string $path): bool
    {
        if (! self::isLink($path)) {
            return false;
        }

        // A junction (or a directory symlink on Windows) is removed with rmdir(), which
        // only deletes the link; anything else with unlink().
        $removed = (windows_os() && @rmdir($path)) || @unlink($path);

        clearstatcache(true, $path);

        return $removed;
    }

    private static function normalize(string $path): string
    {
        $path = str_replace('/', '\\', $path);

        if (str_starts_with($path, '\\\\?\\')) {
            $path = substr($path, 4); // extended-length prefix
        }

        return rtrim($path, '\\');
    }
}
