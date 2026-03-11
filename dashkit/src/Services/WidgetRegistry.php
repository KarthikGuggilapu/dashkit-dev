<?php

namespace Dashkit\Services;

use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Throwable;

class WidgetRegistry
{
    private Filesystem $files;

    /** @var array<int, array<string, mixed>> */
    private array $widgets = [];

    public function __construct()
    {
        $this->files = new Filesystem;
    }

    /**
     * @param  array<string, mixed>  $widget
     */
    public function register(array $widget): void
    {
        $this->widgets[] = $widget;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolveAll(): array
    {
        $resolved = array_map(fn (array $widget): array => $this->resolveWidget($widget), $this->widgets);

        usort($resolved, static fn (array $a, array $b): int => ((int) $a['order']) <=> ((int) $b['order']));

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $widget
     * @return array<string, mixed>
     */
    private function resolveWidget(array $widget): array
    {
        $rawValue = $this->resolveValue($widget['value'] ?? ($widget['source'] ?? 0));
        $safeValue = $this->safeValue($rawValue);

        return [
            'key' => (string) ($widget['key'] ?? ''),
            'title' => (string) ($widget['title'] ?? 'Widget'),
            'description' => (string) ($widget['description'] ?? ''),
            'icon' => (string) ($widget['icon'] ?? 'dot'),
            'order' => (int) ($widget['order'] ?? 100),
            'raw_value' => $safeValue,
            'value' => $this->formatValue($safeValue, (string) ($widget['format'] ?? 'plain'), (string) ($widget['prefix'] ?? ''), (string) ($widget['suffix'] ?? '')),
        ];
    }

    private function resolveValue(mixed $value): mixed
    {
        try {
            if (is_array($value) && isset($value['token'])) {
                return $this->resolveToken((string) $value['token']);
            }

            if (is_callable($value)) {
                $value = $value();
            }

            if (is_string($value)) {
                $value = $this->resolveToken($value);
            }
        } catch (Throwable) {
            return 0;
        }

        return $value;
    }

    private function resolveToken(string $token): mixed
    {
        return match ($token) {
            'users_count' => $this->resolveUsersCount(),
            'pages_count' => $this->resolvePagesCount(),
            'modules_count' => $this->resolveModulesCount(),
            'app_name' => (string) config('app.name', 'Laravel'),
            'db_connection' => (string) config('database.default', 'unknown'),
            default => $token,
        };
    }

    private function resolveUsersCount(): int
    {
        if (! class_exists(User::class)) {
            return 0;
        }

        try {
            return (int) User::query()->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function resolvePagesCount(): int
    {
        try {
            $paths = [
                resource_path('views/dashkit/pages'),
                resource_path('views/vendor/dashkit/pages'),
            ];

            $count = 0;
            foreach ($paths as $path) {
                if (! $this->files->isDirectory($path)) {
                    continue;
                }

                $count += count($this->files->glob($path.DIRECTORY_SEPARATOR.'*.blade.php'));
            }

            return $count;
        } catch (Throwable) {
            return 0;
        }
    }

    private function resolveModulesCount(): int
    {
        try {
            $path = app_path('Http/Controllers/Dashkit');
            if (! $this->files->isDirectory($path)) {
                return 0;
            }

            return count($this->files->glob($path.DIRECTORY_SEPARATOR.'*Controller.php'));
        } catch (Throwable) {
            return 0;
        }
    }

    private function formatValue(mixed $value, string $format, string $prefix, string $suffix): string
    {
        if ($format === 'number' && is_numeric($value)) {
            return $prefix.number_format((float) $value).$suffix;
        }

        if ($format === 'currency' && is_numeric($value)) {
            return $prefix.number_format((float) $value, 2).$suffix;
        }

        if ($format === 'percent' && is_numeric($value)) {
            return $prefix.number_format((float) $value, 1).'%'.$suffix;
        }

        return $prefix.(string) $value.$suffix;
    }

    private function safeValue(mixed $value): mixed
    {
        return $value ?? 0;
    }
}
