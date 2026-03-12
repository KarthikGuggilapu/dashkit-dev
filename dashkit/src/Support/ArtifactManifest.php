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
     * @return array{files: array<int, string>, routes: array<int, string>, hashes: array<string, string>}
     */
    public function all(): array
    {
        if (! $this->files->exists($this->path)) {
            return ['files' => [], 'routes' => [], 'hashes' => []];
        }

        $decoded = json_decode((string) $this->files->get($this->path), true);

        if (! is_array($decoded)) {
            return ['files' => [], 'routes' => [], 'hashes' => []];
        }

        $files = isset($decoded['files']) && is_array($decoded['files']) ? $decoded['files'] : [];
        $routes = isset($decoded['routes']) && is_array($decoded['routes']) ? $decoded['routes'] : [];
        $hashes = isset($decoded['hashes']) && is_array($decoded['hashes']) ? $decoded['hashes'] : [];

        return [
            'files' => array_values(array_unique(array_filter(array_map('strval', $files)))),
            'routes' => array_values(array_unique(array_filter(array_map('strval', $routes)))),
            'hashes' => array_filter($hashes, 'is_string'),
        ];
    }

    public function addFile(string $path): void
    {
        $data = $this->all();
        $data['files'][] = $path;
        $this->save($data);
    }

    public function removeFile(string $path): void
    {
        $data = $this->all();
        $data['files'] = array_values(array_filter(
            $data['files'],
            static fn (string $tracked): bool => $tracked !== $path
        ));
        unset($data['hashes'][$path]);
        $this->save($data);
    }

    public function renameFile(string $oldPath, string $newPath): void
    {
        $data = $this->all();

        foreach ($data['files'] as $idx => $tracked) {
            if ($tracked === $oldPath) {
                $data['files'][$idx] = $newPath;
            }
        }

        if (isset($data['hashes'][$oldPath])) {
            $data['hashes'][$newPath] = (string) $data['hashes'][$oldPath];
            unset($data['hashes'][$oldPath]);
        }

        $this->save($data);
    }

    public function addRoute(string $routeName, ?string $signature = null): void
    {
        $data = $this->all();
        $data['routes'][] = $routeName;

        if ($signature !== null) {
            $data['hashes'][$this->routeHashKey($routeName)] = $signature;
        }

        $this->save($data);
    }

    public function removeRoute(string $routeName): void
    {
        $data = $this->all();
        $data['routes'] = array_values(array_filter(
            $data['routes'],
            static fn (string $tracked): bool => $tracked !== $routeName
        ));
        unset($data['hashes'][$this->routeHashKey($routeName)]);
        $this->save($data);
    }

    public function renameRoute(string $oldRouteName, string $newRouteName): void
    {
        $data = $this->all();

        foreach ($data['routes'] as $idx => $tracked) {
            if ($tracked === $oldRouteName) {
                $data['routes'][$idx] = $newRouteName;
            }
        }

        $oldKey = $this->routeHashKey($oldRouteName);
        $newKey = $this->routeHashKey($newRouteName);

        if (isset($data['hashes'][$oldKey])) {
            $data['hashes'][$newKey] = (string) $data['hashes'][$oldKey];
            unset($data['hashes'][$oldKey]);
        }

        $this->save($data);
    }

    /**
     * Record the md5 hash of a file at the moment Dashkit wrote it.
     * Call this immediately after writing the file to disk.
     */
    public function recordHash(string $path): void
    {
        if (! file_exists($path)) {
            return;
        }

        $data = $this->all();
        $data['hashes'][$path] = md5_file($path) ?: '';
        $this->save($data);
    }

    /**
     * Retrieve the stored baseline hash for a file, or null if not tracked.
     */
    public function getHash(string $path): ?string
    {
        $data = $this->all();

        return isset($data['hashes'][$path]) ? (string) $data['hashes'][$path] : null;
    }

    public function getRouteSignature(string $routeName): ?string
    {
        $data = $this->all();
        $key = $this->routeHashKey($routeName);

        return isset($data['hashes'][$key]) ? (string) $data['hashes'][$key] : null;
    }

    /**
     * Returns true when the file on disk differs from the hash recorded at creation time.
     * Returns false when not tracked, file missing, or unchanged.
     */
    public function fileModifiedSinceTracked(string $path): bool
    {
        $stored = $this->getHash($path);

        if ($stored === null || ! file_exists($path)) {
            return false;
        }

        return (md5_file($path) ?: '') !== $stored;
    }

    public function clear(): void
    {
        if ($this->files->exists($this->path)) {
            $this->files->delete($this->path);
        }
    }

    /**
     * @param  array{files: array<int, string>, routes: array<int, string>, hashes: array<string, string>}  $data
     */
    private function save(array $data): void
    {
        $data['files'] = array_values(array_unique(array_filter(array_map('strval', $data['files']))));
        $data['routes'] = array_values(array_unique(array_filter(array_map('strval', $data['routes']))));

        if (! isset($data['hashes']) || ! is_array($data['hashes'])) {
            $data['hashes'] = [];
        }

        $this->files->ensureDirectoryExists(dirname($this->path));
        $this->files->put($this->path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function routeHashKey(string $routeName): string
    {
        return 'route:'.$routeName;
    }
}
