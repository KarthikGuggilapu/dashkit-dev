<?php

namespace Dashkit\Support;

use Illuminate\Console\Command;

class CompatibilityGuard
{
    private const MIN_PHP = '8.1.0';

    private const MIN_LARAVEL_MAJOR = 10;

    private const MAX_LARAVEL_MAJOR = 12;

    public static function ensure(Command $command): bool
    {
        $phpVersion = PHP_VERSION;
        $laravelVersion = (string) app()->version();
        $laravelMajor = self::extractMajorVersion($laravelVersion);

        if (version_compare($phpVersion, self::MIN_PHP, '<')) {
            $command->components->error('Dashkit requires PHP '.self::MIN_PHP.' or higher. Current: '.$phpVersion);

            return false;
        }

        if ($laravelMajor < self::MIN_LARAVEL_MAJOR || $laravelMajor > self::MAX_LARAVEL_MAJOR) {
            $command->components->error(
                'Dashkit supports Laravel '.self::MIN_LARAVEL_MAJOR.'-'.self::MAX_LARAVEL_MAJOR
                .'. Current: '.$laravelVersion
            );

            return false;
        }

        return true;
    }

    private static function extractMajorVersion(string $version): int
    {
        if (preg_match('/^(\d+)/', trim($version), $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }
}