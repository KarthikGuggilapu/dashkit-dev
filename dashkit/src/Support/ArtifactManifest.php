<?php

namespace Dashkit\Support;

use Illuminate\Filesystem\Filesystem;

class ArtifactManifest
{
    private string $path;

    public function __construct(private readonly Filesystem $files)
    {
        $this->path = storage_path('app/dashkit/artifacts.json');
    }

    /**
     * @return array{files: array<int, string>, routes: array<int, string>}
     */
    public function all(): array
    {
        if (! $this->files->exists($this->path)) {
            return ['files' => [], 'routes' => []];
        }

        $decoded = json_decode((string) $this->files->get($this->path), true);

        if (! is_array($decoded)) {
            return ['files' => [], 'routes' => []];
        }

        $files = isset($decoded['files']) && is_array($decoded['files']) ? $decoded['files'] : [];
        $routes = isset($decoded['routes']) && is_array($decoded['routes']) ? $decoded['routes'] : [];

        return [
            'files' => array_values(array_unique(array_filter(array_map('strval', $files)))),
            'routes' => array_values(array_unique(array_filter(array_map('strval', $routes)))),
        ];
    }

    public function addFile(string $path): void
    {
        $data = $this->all();
        $data['files'][] = $path;
        $this->save($data);
    }

    public function addRoute(string $routeName): void
    {
        $data = $this->all();
        $data['routes'][] = $routeName;
        $this->save($data);
    }

    public function clear(): void
    {
        if ($this->files->exists($this->path)) {
            $this->files->delete($this->path);
        }
    }

    /**
     * @param array{files: array<int, string>, routes: array<int, string>} $data
     */
    private function save(array $data): void
    {
        $data['files'] = array_values(array_unique(array_filter(array_map('strval', $data['files']))));
        $data['routes'] = array_values(array_unique(array_filter(array_map('strval', $data['routes']))));

        $this->files->ensureDirectoryExists(dirname($this->path));
        $this->files->put($this->path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
