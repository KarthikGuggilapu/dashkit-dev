<?php

namespace Dashkit\Commands;

use Dashkit\Support\CompatibilityGuard;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class DashkitReleaseCheckCommand extends Command
{
    protected $signature = 'dashkit:release-check {--base=main : Git ref to compare against}';

    protected $description = 'Recommend the next Dashkit version bump based on package changes.';

    public function handle(Filesystem $files): int
    {
        if (! CompatibilityGuard::ensure($this)) {
            return self::FAILURE;
        }

        $repoRoot = base_path();
        $packageRoot = dirname(__DIR__, 2);
        $relativePackagePath = trim(str_replace('\\', '/', str_replace($repoRoot, '', $packageRoot)), '/');
        $baseRef = trim((string) $this->option('base')) ?: 'main';

        $baseChanges = $this->gitNameStatus($repoRoot, ['diff', '--name-status', $baseRef.'...HEAD', '--', $relativePackagePath]);
        $workingTreeChanges = $this->gitNameStatus($repoRoot, ['diff', '--name-status', '--', $relativePackagePath]);
        $stagedChanges = $this->gitNameStatus($repoRoot, ['diff', '--cached', '--name-status', '--', $relativePackagePath]);
        $untrackedChanges = $this->gitUntracked($repoRoot, $relativePackagePath);

        $changes = $this->mergeChanges($baseChanges, $workingTreeChanges, $stagedChanges, $untrackedChanges);

        if ($changes === []) {
            $this->components->info('No Dashkit package changes detected.');

            return self::SUCCESS;
        }

        $currentVersion = $this->currentPackageVersion($files);
        [$level, $reason] = $this->recommendLevel($changes);
        $nextVersion = $this->bumpVersion($currentVersion, $level);

        $this->components->info('Dashkit release check');
        $this->line('Current version: '.$currentVersion);
        $this->line('Recommended bump: '.$level);
        $this->line('Suggested next version: '.$nextVersion);
        $this->line('Reason: '.$reason);
        $this->newLine();
        $this->line('Changed files:');

        foreach ($changes as $change) {
            $this->line('- ['.$change['status'].'] '.$change['path']);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{status: string, path: string}>
     */
    private function gitNameStatus(string $workingDirectory, array $arguments): array
    {
        $process = new Process(array_merge(['git'], $arguments), $workingDirectory);
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        $changes = [];
        $lines = preg_split('/\R/', trim($process->getOutput())) ?: [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            [$status, $path] = array_pad(preg_split('/\s+/', $line, 2) ?: [], 2, '');

            if ($path === '') {
                continue;
            }

            $changes[] = [
                'status' => $status,
                'path' => str_replace('\\', '/', $path),
            ];
        }

        return $changes;
    }

    /**
     * @return array<int, array{status: string, path: string}>
     */
    private function gitUntracked(string $workingDirectory, string $relativePackagePath): array
    {
        $process = new Process(['git', 'ls-files', '--others', '--exclude-standard', '--', $relativePackagePath], $workingDirectory);
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        $changes = [];
        $lines = preg_split('/\R/', trim($process->getOutput())) ?: [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $changes[] = [
                'status' => 'A',
                'path' => str_replace('\\', '/', $line),
            ];
        }

        return $changes;
    }

    /**
     * @param  array<int, array{status: string, path: string}>  ...$groups
     * @return array<int, array{status: string, path: string}>
     */
    private function mergeChanges(array ...$groups): array
    {
        $merged = [];

        foreach ($groups as $group) {
            foreach ($group as $change) {
                $merged[$change['path']] = $change;
            }
        }

        ksort($merged);

        return array_values($merged);
    }

    /**
     * @param  array<int, array{status: string, path: string}>  $changes
     * @return array{0: string, 1: string}
     */
    private function recommendLevel(array $changes): array
    {
        $paths = array_column($changes, 'path');
        $hasDeletion = collect($changes)->contains(fn (array $change): bool => str_starts_with($change['status'], 'D'));

        foreach ($paths as $path) {
            if ($path === 'packages/dashkit/composer.json' && $hasDeletion) {
                return ['major', 'Package metadata changed together with file deletions. Review as a breaking release.'];
            }
        }

        if ($hasDeletion) {
            return ['major', 'Package files were deleted. Treat this as a breaking release.'];
        }

        foreach ($paths as $path) {
            if (
                str_contains($path, '/src/')
                || str_contains($path, '/config/')
                || str_contains($path, '/routes/')
            ) {
                return ['minor', 'Package PHP/config/route behavior changed. Ship this as a feature release.'];
            }
        }

        return ['patch', 'Changes are limited to views, assets, docs, or metadata.'];
    }

    private function bumpVersion(string $version, string $level): string
    {
        $parts = array_map('intval', explode('.', $version));
        $parts = array_pad($parts, 3, 0);

        if ($level === 'major') {
            return ($parts[0] + 1).'.0.0';
        }

        if ($level === 'minor') {
            return $parts[0].'.'.($parts[1] + 1).'.0';
        }

        return $parts[0].'.'.$parts[1].'.'.($parts[2] + 1);
    }

    private function currentPackageVersion(Filesystem $files): string
    {
        $composerPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'composer.json';

        if (! $files->exists($composerPath)) {
            return '0.0.0';
        }

        $decoded = json_decode((string) $files->get($composerPath), true);

        if (! is_array($decoded)) {
            return '0.0.0';
        }

        $version = $decoded['version'] ?? null;

        return is_string($version) && $version !== '' ? $version : '0.0.0';
    }
}