<?php
/**
 * Helper to find classes.
 * This is useful in places where we use folder structure as an organizing convention,
 * like email notifications being customized by an Account in App\Notifications\
 * or the complete set of analytics events in App\Events\
 */

namespace Carsdotcom\JsonSchemaValidation\Helpers;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class FindClasses
{
    /**
     * Returns all the classes in a subfolder of Laravel's app_path(), recursively
     * @param string $subfolder
     * @return Collection
     */
    public static function inAppPath(string $subfolder): Collection
    {
        $fullPath = Str::finish(app_path($subfolder), '/');

        return collect(
            (new Finder())
                ->in($fullPath)
                ->files()
                ->name('*.php'),
        )
            ->map(static function (string $filename) {
                // Remove app path from the left, and .php from the right
                $className = substr($filename, strlen(app_path()), -4);

                // Add App (root of the namespace) and change / to \
                return 'App' . str_replace('/', '\\', $className);
            })
            ->filter(static function (string $className) {
                return class_exists($className, true) && !(new ReflectionClass($className))->isAbstract();
            })
            ->values(); // Restore 0-index no gaps
    }

    /**
     * Get one class if you know its folder (or any parent folder) and the class name.
     * @param string $subfolder
     * @param string $name
     * @return string|null
     */
    public static function byPathAndName(string $subfolder, string $name): ?string
    {
        return self::inAppPath($subfolder)->first(function ($item) use ($name) {
            return Str::endsWith($item, '\\' . $name);
        });
    }

    public static function byPathAndType(string $subfolder, string $implements): Collection
    {
        return self::inAppPath($subfolder)->filter(fn($item) => is_a($item, $implements, true));
    }
}