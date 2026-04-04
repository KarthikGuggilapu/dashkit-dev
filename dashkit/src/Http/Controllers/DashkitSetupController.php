<?php

namespace Dashkit\Http\Controllers;

use Dashkit\Support\ProjectTraceInspector;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PDO;
use Throwable;

class DashkitSetupController extends Controller
{
    private string $tokenPath;

    private string $progressPath;

    private string $setupDatabasePath;

    public function __construct()
    {
        $this->tokenPath = storage_path('app/dashkit/setup-token.json');
        $this->progressPath = storage_path('app/dashkit/install-progress.json');
        $this->setupDatabasePath = storage_path('app/dashkit/setup.sqlite');
    }

    // -------------------------------------------------------------------------
    // Wizard entry point
    // -------------------------------------------------------------------------

    public function index(Request $request): View|RedirectResponse
    {
        $token = (string) $request->query('token', '');

        if (! $this->isValidToken($token)) {
            abort(404);
        }

        $inspector = new ProjectTraceInspector(new Filesystem);
        $report = $inspector->inspect();
        $availableActions = $this->availableActionsForLifecycle((string) $report['lifecycle_status']);
        $progressSummary = $this->loadProgressSummary();

        return view('dashkit::setup.wizard', [
            'token' => $token,
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'appName' => (string) config('app.name', 'Laravel'),
            'appUrl' => (string) config('app.url', 'http://localhost'),
            'dbConnection' => (string) env('DB_CONNECTION', 'mysql'),
            'dbHost' => (string) env('DB_HOST', '127.0.0.1'),
            'dbPort' => (string) env('DB_PORT', '3306'),
            'dbDatabase' => (string) env('DB_DATABASE', 'laravel'),
            'dbUsername' => (string) env('DB_USERNAME', 'root'),
            'adminName' => (string) ($_ENV['DASHKIT_DEFAULT_ADMIN_NAME'] ?? 'Dashkit Admin'),
            'adminEmail' => (string) ($_ENV['DASHKIT_DEFAULT_ADMIN_EMAIL'] ?? 'admin@example.com'),
            'preset' => (string) ($_ENV['DASHKIT_INSTALL_PRESET'] ?? 'default'),
            'traceReport' => $report,
            'traceHighlights' => array_slice(array_values(array_filter($report['traces'], static fn (array $trace): bool => $trace['present'])), 0, 5),
            'availableActions' => $availableActions,
            'progressSummary' => $progressSummary,
        ]);
    }

    // -------------------------------------------------------------------------
    // AJAX: Test database connection
    // -------------------------------------------------------------------------

    public function testConnection(Request $request): JsonResponse
    {
        $token = (string) $request->input('token', '');

        if (! $this->isValidToken($token)) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired setup token.'], 403);
        }

        $connection = (string) $request->input('db_connection', 'mysql');
        $host = (string) $request->input('db_host', '127.0.0.1');
        $port = (int) $request->input('db_port', 3306);
        $database = (string) $request->input('db_database', '');
        $username = (string) $request->input('db_username', '');
        $password = (string) $request->input('db_password', '');

        try {
            if ($connection === 'sqlite') {
                $dsn = 'sqlite:'.$this->resolveSqlitePath($database);
                new PDO($dsn);
            } elseif ($connection === 'pgsql') {
                $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
                new PDO($dsn, $username, $password, [PDO::ATTR_TIMEOUT => 5]);
            } else {
                $dsn = "mysql:host={$host};port={$port};dbname={$database}";
                new PDO($dsn, $username, $password, [PDO::ATTR_TIMEOUT => 5]);
            }

            return response()->json(['success' => true, 'message' => 'Connection successful!']);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Connection failed: '.$e->getMessage()]);
        }
    }

    // -------------------------------------------------------------------------
    // AJAX: Run the full installation
    // -------------------------------------------------------------------------

    public function install(Request $request): JsonResponse
    {
        $token = (string) $request->input('token', '');

        if (! $this->isValidToken($token)) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired setup token.'], 403);
        }

        $validated = $request->validate([
            'action' => ['required', 'in:install,resume,reinstall,upgrade'],
            'app_name' => ['required', 'string', 'max:255'],
            'db_connection' => ['required', 'in:sqlite,mysql,pgsql'],
            'db_host' => ['nullable', 'string', 'max:255'],
            'db_port' => ['nullable', 'integer'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['nullable', 'string', 'max:255'],
            'db_password' => ['nullable', 'string'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email'],
            'admin_password' => ['required', 'string', 'min:8'],
            'preset' => ['required', 'in:default,ecommerce,crm'],
        ]);

        $inspector = new ProjectTraceInspector(new Filesystem);
        $traceReport = $inspector->inspect();
        $action = (string) $validated['action'];

        if (! in_array($action, $this->availableActionsForLifecycle((string) $traceReport['lifecycle_status']), true)) {
            return response()->json([
                'success' => false,
                'message' => 'The requested GUI action is not valid for the current Dashkit lifecycle state.',
                'output' => 'Lifecycle state: '.$traceReport['lifecycle_status'].' | Recommended command: '.$traceReport['recommended_command'],
            ], 422);
        }

        $connection = $validated['db_connection'];
        $defaultPort = $connection === 'pgsql' ? '5432' : '3306';
        $response = null;

        // Build the same setup array the CLI would produce in collectSetupInputs()
        $setup = [
            'APP_NAME' => $validated['app_name'],
            'DB_CONNECTION' => $connection,
            'DB_HOST' => $connection === 'sqlite' ? '' : (string) ($validated['db_host'] ?? '127.0.0.1'),
            'DB_PORT' => $connection === 'sqlite' ? '' : (string) ($validated['db_port'] ?? $defaultPort),
            'DB_DATABASE' => $validated['db_database'],
            'DB_USERNAME' => $connection === 'sqlite' ? '' : (string) ($validated['db_username'] ?? ''),
            'DB_PASSWORD' => $connection === 'sqlite' ? '' : (string) ($validated['db_password'] ?? ''),
            'DASHKIT_DEFAULT_ADMIN_NAME' => $validated['admin_name'],
            'DASHKIT_DEFAULT_ADMIN_EMAIL' => $validated['admin_email'],
            'DASHKIT_DEFAULT_ADMIN_PASSWORD' => $validated['admin_password'],
            'MAIL_MAILER' => (string) env('MAIL_MAILER', 'log'),
            'MAIL_HOST' => (string) env('MAIL_HOST', '127.0.0.1'),
            'MAIL_PORT' => (string) env('MAIL_PORT', '2525'),
            'MAIL_USERNAME' => (string) env('MAIL_USERNAME', ''),
            'MAIL_PASSWORD' => (string) env('MAIL_PASSWORD', ''),
            'MAIL_ENCRYPTION' => (string) data_get(config('mail.mailers.smtp', []), 'encryption', 'tls'),
            'MAIL_FROM_ADDRESS' => (string) env('MAIL_FROM_ADDRESS', 'hello@example.com'),
            'MAIL_FROM_NAME' => (string) env('MAIL_FROM_NAME', $validated['app_name']),
            'DASHKIT_MAIL_FROM_ADDRESS' => (string) env('MAIL_FROM_ADDRESS', 'hello@example.com'),
            'DASHKIT_MAIL_FROM_NAME' => (string) env('MAIL_FROM_NAME', $validated['app_name']),
            'DASHKIT_INSTALL_PRESET' => $validated['preset'],
        ];

        try {
            $command = 'dashkit:install';
            $parameters = ['--resume' => true];
            $executedStepKey = null;

            if ($action !== 'upgrade') {
                $this->writeProgressWithSetup($setup);

                $progressBefore = $this->loadProgressSummary();
                $executedStepKey = $progressBefore['next_step_key'];

                if ($executedStepKey === null) {
                    return response()->json([
                        'success' => true,
                        'message' => 'All installer steps are already complete.',
                        'output' => 'Dashkit guided setup has no remaining steps to run.',
                        'progress' => $this->buildCompletedProgressSummary(),
                        'installation_complete' => true,
                        'redirect' => '/',
                    ]);
                }

                $parameters['--stop-after'] = $executedStepKey;
            }

            if ($action === 'reinstall') {
                $parameters['--force'] = true;
            }

            if ($action === 'upgrade') {
                $command = 'dashkit:upgrade';
                $parameters = ['--type' => $validated['preset']];
            }

            $exitCode = Artisan::call($command, $parameters);
            $output = Artisan::output();

            if ($exitCode !== 0) {
                $response = response()->json([
                    'success' => false,
                    'message' => ucfirst($action).' encountered errors. Please check the output.',
                    'output' => $output,
                    'attempted_step' => $action === 'upgrade' ? null : $this->stepInfo($executedStepKey),
                    'retry_hint' => $action === 'upgrade' ? 'Review the upgrade output, fix the reported issue, and run upgrade again.' : $this->retryHint($executedStepKey),
                    'progress' => $action === 'upgrade' ? null : $this->loadProgressSummary(),
                ]);

                return $response;
            }

            if ($action === 'upgrade') {
                $this->deleteToken();

                return response()->json([
                    'success' => true,
                    'message' => 'Dashkit upgraded successfully!',
                    'output' => $output,
                    'installation_complete' => true,
                    'redirect' => '/',
                ]);
            }

            $latestTraceReport = $inspector->inspect();
            $installationComplete = ! file_exists($this->progressPath) && $latestTraceReport['status'] === 'installed';
            $progress = $installationComplete
                ? $this->buildCompletedProgressSummary()
                : $this->loadProgressSummary();

            if ($installationComplete) {
                $this->deleteToken();
            }

            return response()->json([
                'success' => true,
                'message' => $installationComplete
                    ? 'Dashkit install completed successfully!'
                    : 'Completed '.$this->stepDisplay($executedStepKey).'. Continue to the next step.',
                'output' => $output,
                'progress' => $progress,
                'executed_step' => $this->stepInfo($executedStepKey),
                'installation_complete' => $installationComplete,
                'redirect' => $installationComplete ? '/' : null,
            ]);
        } catch (Throwable $e) {
            $response = response()->json([
                'success' => false,
                'message' => 'Unexpected error: '.$e->getMessage(),
                'output' => '',
                'progress' => $action === 'upgrade' ? null : $this->loadProgressSummary(),
            ]);

            return $response;
        } finally {
            $this->reapplySetupRuntime();
        }
    }

    /**
     * @return array<int, string>
     */
    private function availableActionsForLifecycle(string $lifecycleStatus): array
    {
        return match ($lifecycleStatus) {
            'fresh' => ['install'],
            'resume-available' => ['resume', 'reinstall'],
            'ready-to-upgrade' => ['upgrade', 'reinstall'],
            default => ['reinstall'],
        };
    }

    /**
     * @return array{has_progress: bool, completed: array<int, string>, completed_keys: array<int, string>, next_step: string|null, next_step_key: string|null, setup_ready: bool, steps: array<int, array{key: string, label: string, status: string, index: int}>, completed_count: int, total_steps: int, percent: int}
     */
    private function loadProgressSummary(): array
    {
        $steps = $this->installSteps();

        if (! file_exists($this->progressPath)) {
            return [
                'has_progress' => false,
                'completed' => [],
                'completed_keys' => [],
                'next_step' => null,
                'next_step_key' => null,
                'setup_ready' => false,
                'steps' => $this->buildStepItems([], null),
                'completed_count' => 0,
                'total_steps' => count($steps),
                'percent' => 0,
            ];
        }

        $decoded = json_decode((string) file_get_contents($this->progressPath), true);

        if (! is_array($decoded)) {
            return [
                'has_progress' => false,
                'completed' => [],
                'completed_keys' => [],
                'next_step' => null,
                'next_step_key' => null,
                'setup_ready' => false,
                'steps' => $this->buildStepItems([], null),
                'completed_count' => 0,
                'total_steps' => count($steps),
                'percent' => 0,
            ];
        }

        $completed = isset($decoded['completed']) && is_array($decoded['completed'])
            ? array_values(array_unique(array_map('strval', $decoded['completed'])))
            : [];

        $completedLabels = [];
        foreach ($completed as $step) {
            $completedLabels[] = $steps[$step] ?? $step;
        }

        $nextStepKey = null;
        $nextStep = null;
        foreach (array_keys($steps) as $step) {
            if (! in_array($step, $completed, true)) {
                $nextStepKey = $step;
                $nextStep = $steps[$step];
                break;
            }
        }

        $completedCount = count(array_intersect(array_keys($steps), $completed));
        $totalSteps = count($steps);

        return [
            'has_progress' => $completed !== [] || isset($decoded['setup']),
            'completed' => $completedLabels,
            'completed_keys' => $completed,
            'next_step' => $nextStep,
            'next_step_key' => $nextStepKey,
            'setup_ready' => isset($decoded['setup']) && is_array($decoded['setup']) && $decoded['setup'] !== [],
            'steps' => $this->buildStepItems($completed, $nextStepKey),
            'completed_count' => $completedCount,
            'total_steps' => $totalSteps,
            'percent' => $totalSteps > 0 ? (int) floor(($completedCount / $totalSteps) * 100) : 0,
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function isValidToken(string $token): bool
    {
        if ($token === '' || ! file_exists($this->tokenPath)) {
            return false;
        }

        $raw = @file_get_contents($this->tokenPath);
        if ($raw === false) {
            return false;
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return false;
        }

        $storedToken = (string) ($data['token'] ?? '');
        $expiresAt = (string) ($data['expires_at'] ?? '');

        if (! hash_equals($storedToken, $token)) {
            return false;
        }

        if ($expiresAt !== '' && now()->greaterThan($expiresAt)) {
            return false;
        }

        return true;
    }

    private function resolveSqlitePath(string $database): string
    {
        $database = trim($database);

        if ($database === '') {
            return database_path('database.sqlite');
        }

        if (str_contains($database, ':') || str_starts_with($database, '/') || str_starts_with($database, '\\')) {
            return $database;
        }

        if (str_starts_with(str_replace('\\', '/', $database), 'database/')) {
            return base_path($database);
        }

        return database_path($database);
    }

    private function reapplySetupRuntime(): void
    {
        $filesystem = new Filesystem;

        $filesystem->ensureDirectoryExists(dirname($this->setupDatabasePath));

        if (! $filesystem->exists($this->setupDatabasePath)) {
            $filesystem->put($this->setupDatabasePath, '');
        }

        config([
            'database.connections.dashkit_setup' => [
                'driver' => 'sqlite',
                'database' => $this->setupDatabasePath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'database.default' => 'sqlite',
            'session.driver' => 'database',
            'session.connection' => 'dashkit_setup',
            'session.table' => 'sessions',
            'cache.default' => 'file',
        ]);

        DB::purge('dashkit_setup');
        DB::purge('sqlite');

        try {
            DB::connection('dashkit_setup')->getPdo()->exec('CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(255) PRIMARY KEY,
                user_id INTEGER NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                payload TEXT NOT NULL,
                last_activity INTEGER NOT NULL
            )');
        } catch (Throwable) {
            // Keep the setup response flowing even if the temporary SQLite bootstrap fails.
        }
    }

    private function deleteToken(): void
    {
        if (file_exists($this->tokenPath)) {
            @unlink($this->tokenPath);
        }

        if (file_exists($this->setupDatabasePath)) {
            @unlink($this->setupDatabasePath);
        }
    }

    /**
     * Write setup data to install-progress.json so the resumed CLI install
     * can consume it when step 5 is reached.
     *
     * @param  array<string, string>  $setup
     */
    private function writeProgressWithSetup(array $setup): void
    {
        $existing = [];

        if (file_exists($this->progressPath)) {
            $decoded = json_decode((string) file_get_contents($this->progressPath), true);
            $existing = is_array($decoded) ? $decoded : [];
        }

        $progress = [
            'completed' => array_values((array) ($existing['completed'] ?? [])),
            'setup' => $setup,
        ];

        $fs = new Filesystem;
        $fs->ensureDirectoryExists(dirname($this->progressPath));
        $fs->put($this->progressPath, json_encode($progress, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, string>
     */
    private function installSteps(): array
    {
        return [
            'publish_config' => 'Publish configuration',
            'publish_views' => 'Publish views',
            'publish_assets' => 'Publish assets',
            'route_registration' => 'Register routes',
            'env_setup' => 'Collect setup inputs and update environment',
            'post_setup' => 'Prepare preset pages and dashboard redirect',
            'migrate_seed' => 'Run migrations and seed admin user',
        ];
    }

    /**
     * @param  array<int, string>  $completed
     * @return array<int, array{key: string, label: string, status: string, index: int}>
     */
    private function buildStepItems(array $completed, ?string $nextStepKey): array
    {
        $items = [];

        foreach (array_keys($this->installSteps()) as $index => $key) {
            $items[] = [
                'key' => $key,
                'label' => $this->installSteps()[$key],
                'status' => in_array($key, $completed, true)
                    ? 'completed'
                    : ($key === $nextStepKey ? 'current' : 'pending'),
                'index' => $index + 1,
            ];
        }

        return $items;
    }

    /**
     * @return array{key: string, label: string, index: int, total: int}|null
     */
    private function stepInfo(?string $stepKey): ?array
    {
        if ($stepKey === null) {
            return null;
        }

        $keys = array_keys($this->installSteps());
        $index = array_search($stepKey, $keys, true);

        if ($index === false) {
            return null;
        }

        return [
            'key' => $stepKey,
            'label' => $this->installSteps()[$stepKey],
            'index' => $index + 1,
            'total' => count($keys),
        ];
    }

    private function stepDisplay(?string $stepKey): string
    {
        $info = $this->stepInfo($stepKey);

        if ($info === null) {
            return 'installer step';
        }

        return 'step '.$info['index'].'/'.$info['total'].': '.$info['label'];
    }

    private function retryHint(?string $stepKey): string
    {
        return match ($stepKey) {
            'publish_config', 'publish_views', 'publish_assets' => 'Check file permissions or conflicting published files, then run Next Step again.',
            'route_registration' => 'Review routes/web.php for conflicts or manual edits, then run Next Step again.',
            'env_setup' => 'Fix the provided app, database, or admin input values, then run Next Step again.',
            'post_setup' => 'Check generated pages, config writes, or redirect wiring, then run Next Step again.',
            'migrate_seed' => 'Fix the database connection or migration issue, then run Next Step again.',
            default => 'Fix the reported issue and run Next Step again.',
        };
    }

    /**
     * @return array{has_progress: bool, completed: array<int, string>, completed_keys: array<int, string>, next_step: string|null, next_step_key: string|null, setup_ready: bool, steps: array<int, array{key: string, label: string, status: string, index: int}>, completed_count: int, total_steps: int, percent: int}
     */
    private function buildCompletedProgressSummary(): array
    {
        $steps = $this->installSteps();
        $completedKeys = array_keys($steps);

        return [
            'has_progress' => true,
            'completed' => array_values($steps),
            'completed_keys' => $completedKeys,
            'next_step' => null,
            'next_step_key' => null,
            'setup_ready' => true,
            'steps' => $this->buildStepItems($completedKeys, null),
            'completed_count' => count($completedKeys),
            'total_steps' => count($steps),
            'percent' => 100,
        ];
    }
}
