<?php

namespace Dashkit\Commands;

use Dashkit\Support\CompatibilityGuard;
use Dashkit\Support\ProjectTraceInspector;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class DashkitInspectCommand extends Command
{
    protected $signature = 'dashkit:inspect {--json : Output the trace report as JSON}';

    protected $description = 'Inspect Dashkit traces in the current project before install, upgrade, or uninstall operations.';

    public function handle(Filesystem $files): int
    {
        if (! CompatibilityGuard::ensure($this)) {
            return self::FAILURE;
        }

        $inspector = new ProjectTraceInspector($files);
        $report = $inspector->inspect();

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->components->info('Dashkit project trace summary');
        $this->line('Lifecycle: '.$report['lifecycle_status']);
        $this->line('Status: '.$report['status']);
        $this->line('Detected traces: '.$report['present_count']);

        if ($report['installed_version'] !== null) {
            $this->line('Installed version: '.$report['installed_version']);
        }

        $this->line('Recommended action: '.$report['recommended_action']);
        $this->line('Recommended command: '.$report['recommended_command']);
        $this->newLine();

        $this->table(
            ['Trace', 'State', 'Detail', 'Path'],
            $inspector->tableRows($report)
        );

        return self::SUCCESS;
    }
}