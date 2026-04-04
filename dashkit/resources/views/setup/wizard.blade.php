<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashkit Console Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: radial-gradient(circle at top, #e0f2fe 0%, #f8fafc 45%, #e2e8f0 100%); }
        .dk-panel { box-shadow: 0 24px 60px rgba(15, 23, 42, 0.10); }
    </style>
</head>
<body class="min-h-screen text-slate-900">
    <div class="mx-auto flex min-h-screen max-w-7xl flex-col gap-8 px-4 py-8 lg:px-8">
        <header class="rounded-3xl border border-white/60 bg-white/70 p-8 backdrop-blur dk-panel">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="mb-3 inline-flex rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-sky-700">Dashkit Console</p>
                    <h1 class="text-4xl font-black tracking-tight text-slate-950">Browser-based setup for your Dashkit install</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">This wizard mirrors the CLI installer. Fill in the same project and database details here, test the connection, then run the package installation from the browser.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[320px]">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">PHP</div>
                        <div class="mt-2 text-lg font-semibold text-slate-900">{{ $phpVersion }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Laravel</div>
                        <div class="mt-2 text-lg font-semibold text-slate-900">{{ $laravelVersion }}</div>
                    </div>
                </div>
            </div>
        </header>

        @php
            $lifecycleTone = match ($traceReport['lifecycle_status']) {
                'fresh' => 'emerald',
                'ready-to-upgrade' => 'amber',
                'resume-available' => 'sky',
                default => 'rose',
            };
        @endphp

        <section class="rounded-3xl border border-{{ $lifecycleTone }}-200 bg-{{ $lifecycleTone }}-50/80 p-6 dk-panel">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <div class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-{{ $lifecycleTone }}-700">{{ str_replace('-', ' ', $traceReport['lifecycle_status']) }}</div>
                    <h2 class="mt-3 text-xl font-bold text-slate-950">Project trace check</h2>
                    <p class="mt-2 text-sm text-slate-700">{{ $traceReport['recommended_action'] }}. Recommended command: <span class="font-semibold text-slate-950">{{ $traceReport['recommended_command'] }}</span></p>
                </div>
                <div class="rounded-2xl border border-white/70 bg-white/70 px-4 py-3 text-sm text-slate-700">
                    <div><span class="font-semibold text-slate-950">Trace status:</span> {{ $traceReport['status'] }}</div>
                    <div class="mt-1"><span class="font-semibold text-slate-950">Detected traces:</span> {{ $traceReport['present_count'] }}</div>
                    @if ($traceReport['installed_version'])
                        <div class="mt-1"><span class="font-semibold text-slate-950">Installed version:</span> {{ $traceReport['installed_version'] }}</div>
                    @endif
                </div>
            </div>

            @if ($traceHighlights !== [])
                <div class="mt-5 flex flex-wrap gap-2">
                    @foreach ($traceHighlights as $trace)
                        <span class="rounded-full border border-white/80 bg-white/80 px-3 py-1 text-xs font-medium text-slate-700">{{ $trace['label'] }}</span>
                    @endforeach
                </div>
            @endif
        </section>

        <main class="grid gap-8 lg:grid-cols-[1.15fr_0.85fr]">
            <section class="rounded-3xl border border-white/60 bg-white/80 p-6 backdrop-blur dk-panel sm:p-8">
                <form id="setup-form" class="space-y-8">
                    <input type="hidden" name="token" value="{{ $token }}">
                    <input type="hidden" name="action" id="action-input" value="{{ $availableActions[0] ?? 'install' }}">

                    <section class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-bold text-slate-950">Setup wizard</h2>
                                <p id="wizard-stage-copy" class="mt-1 text-sm text-slate-600">Complete the setup inputs stage by stage before the installer starts running.</p>
                            </div>
                            <div id="wizard-stage-badge" class="rounded-2xl bg-white px-4 py-2 text-sm font-semibold text-slate-700">Step 1 of 4</div>
                        </div>

                        <div id="wizard-stage-track" class="mt-5 grid gap-3 sm:grid-cols-4">
                            <div data-wizard-track="0" class="wizard-track-step rounded-2xl border border-sky-200 bg-white px-4 py-3">
                                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-700">Stage 1</div>
                                <div class="mt-1 text-sm font-semibold text-slate-900">Project</div>
                            </div>
                            <div data-wizard-track="1" class="wizard-track-step rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Stage 2</div>
                                <div class="mt-1 text-sm font-semibold text-slate-900">Database</div>
                            </div>
                            <div data-wizard-track="2" class="wizard-track-step rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Stage 3</div>
                                <div class="mt-1 text-sm font-semibold text-slate-900">Admin</div>
                            </div>
                            <div data-wizard-track="3" class="wizard-track-step rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Stage 4</div>
                                <div class="mt-1 text-sm font-semibold text-slate-900">Review</div>
                            </div>
                        </div>
                    </section>

                    <section id="wizard-lock-banner" class="hidden rounded-3xl border border-amber-200 bg-amber-50/80 p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-[0.24em] text-amber-700">Inputs locked</h2>
                        <p class="mt-2 text-sm text-amber-900">The guided installer has already started. Setup inputs are now locked so the remaining steps run against the same reviewed values.</p>
                    </section>

                    @if ($progressSummary['has_progress'])
                        <section class="rounded-3xl border border-sky-200 bg-sky-50/70 p-5">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <h2 class="text-lg font-bold text-slate-950">Resume summary</h2>
                                    <p class="mt-1 text-sm text-slate-600">Dashkit found saved install progress from a previous run.</p>
                                </div>
                                <div class="rounded-2xl bg-white/80 px-4 py-3 text-sm text-slate-700">
                                    <div><span class="font-semibold text-slate-950">Setup data ready:</span> {{ $progressSummary['setup_ready'] ? 'yes' : 'no' }}</div>
                                    <div class="mt-1"><span class="font-semibold text-slate-950">Next step:</span> {{ $progressSummary['next_step'] ?? 'Completed' }}</div>
                                </div>
                            </div>

                            @if ($progressSummary['completed'] !== [])
                                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                    @foreach ($progressSummary['completed'] as $step)
                                        <div class="rounded-2xl border border-white/70 bg-white/80 px-4 py-3 text-sm text-slate-700">{{ $step }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    @endif

                    <section class="wizard-stage" data-stage="0" data-lockable="true">
                        <div class="mb-4">
                            <h2 class="text-xl font-bold text-slate-950">Project</h2>
                            <p class="text-sm text-slate-500">These values will be written into your environment during installation.</p>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-700">Application name</span>
                                <input class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-sky-500 focus:ring-4 focus:ring-sky-100" type="text" name="app_name" value="{{ $appName }}" required>
                            </label>
                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-700">Preset</span>
                                <select class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-sky-500 focus:ring-4 focus:ring-sky-100" name="preset" required>
                                    @foreach (['default' => 'Default', 'ecommerce' => 'Ecommerce', 'crm' => 'CRM'] as $value => $label)
                                        <option value="{{ $value }}" @selected($preset === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </section>

                    <section class="wizard-stage hidden" data-stage="1" data-lockable="true">
                        <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-slate-950">Database</h2>
                                <p class="text-sm text-slate-500">Use Test Connection before running the installer.</p>
                            </div>
                            <button id="test-connection" type="button" class="inline-flex h-12 w-full items-center justify-center self-start rounded-2xl border border-sky-200 bg-sky-50 px-5 text-sm font-semibold text-sky-700 shadow-sm transition hover:border-sky-300 hover:bg-sky-100 sm:w-auto sm:min-w-[11rem]">Test connection</button>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-700">Connection</span>
                                <select id="db_connection" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-sky-500 focus:ring-4 focus:ring-sky-100" name="db_connection" required>
                                    @foreach (['mysql' => 'MySQL', 'pgsql' => 'PostgreSQL', 'sqlite' => 'SQLite'] as $value => $label)
                                        <option value="{{ $value }}" @selected($dbConnection === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-700">Database</span>
                                <input class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-sky-500 focus:ring-4 focus:ring-sky-100" type="text" name="db_database" value="{{ $dbDatabase }}" required>
                            </label>
                            <label class="block db-network-field">
                                <span class="mb-2 block text-sm font-medium text-slate-700">Host</span>
                                <input class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-sky-500 focus:ring-4 focus:ring-sky-100" type="text" name="db_host" value="{{ $dbHost }}">
                            </label>
                            <label class="block db-network-field">
                                <span class="mb-2 block text-sm font-medium text-slate-700">Port</span>
                                <input class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-sky-500 focus:ring-4 focus:ring-sky-100" type="number" name="db_port" value="{{ $dbPort }}">
                            </label>
                            <label class="block db-network-field">
                                <span class="mb-2 block text-sm font-medium text-slate-700">Username</span>
                                <input class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-sky-500 focus:ring-4 focus:ring-sky-100" type="text" name="db_username" value="{{ $dbUsername }}">
                            </label>
                            <label class="block db-network-field">
                                <span class="mb-2 block text-sm font-medium text-slate-700">Password</span>
                                <input class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-sky-500 focus:ring-4 focus:ring-sky-100" type="password" name="db_password" value="">
                            </label>
                        </div>
                    </section>

                    <section class="wizard-stage hidden" data-stage="2" data-lockable="true">
                        <div class="mb-4">
                            <h2 class="text-xl font-bold text-slate-950">Admin account</h2>
                            <p class="text-sm text-slate-500">These credentials will be seeded and used for the first login.</p>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-700">Admin name</span>
                                <input class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-sky-500 focus:ring-4 focus:ring-sky-100" type="text" name="admin_name" value="{{ $adminName }}" required>
                            </label>
                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-700">Admin email</span>
                                <input class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-sky-500 focus:ring-4 focus:ring-sky-100" type="email" name="admin_email" value="{{ $adminEmail }}" required>
                            </label>
                            <label class="block md:col-span-2">
                                <span class="mb-2 block text-sm font-medium text-slate-700">Admin password</span>
                                <input class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-sky-500 focus:ring-4 focus:ring-sky-100" type="password" name="admin_password" minlength="8" required>
                            </label>
                        </div>
                    </section>

                    <section class="wizard-stage hidden" data-stage="3">
                        <div class="mb-4">
                            <h2 class="text-xl font-bold text-slate-950">Review and launch</h2>
                            <p class="text-sm text-slate-500">Check the collected inputs once, then start the guided installer. Only one CLI-aligned install step runs per action.</p>
                        </div>

                        <div id="locked-snapshot" class="hidden mb-6 rounded-3xl border border-amber-200 bg-amber-50/80 p-5 transition-colors duration-300">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <h3 id="snapshot-title" class="text-sm font-semibold uppercase tracking-[0.24em] text-amber-700">Locked snapshot</h3>
                                    <p id="snapshot-step" class="mt-2 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Step context unavailable</p>
                                    <p id="snapshot-note" class="mt-2 text-sm text-amber-900">These are the exact reviewed values now frozen for the remaining guided installer steps.</p>
                                </div>
                                <div id="snapshot-summary-panel" class="rounded-2xl bg-white/80 px-4 py-3 text-sm text-slate-700">
                                    <div><span class="font-semibold text-slate-950">App:</span> <span id="snapshot-app-name"></span></div>
                                    <div class="mt-1"><span class="font-semibold text-slate-950">Preset:</span> <span id="snapshot-preset"></span></div>
                                    <div class="mt-1"><span class="font-semibold text-slate-950">DB:</span> <span id="snapshot-db-summary"></span></div>
                                    <div class="mt-1"><span class="font-semibold text-slate-950">Admin:</span> <span id="snapshot-admin-email"></span></div>
                                </div>
                            </div>
                        </div>

                        <div id="review-grid" class="grid gap-4 md:grid-cols-2">
                            <article class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                                <h3 class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">Project</h3>
                                <dl class="mt-4 space-y-3 text-sm text-slate-700">
                                    <div class="flex items-center justify-between gap-4">
                                        <dt>Application</dt>
                                        <dd id="review-app-name" class="font-semibold text-slate-950"></dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <dt>Preset</dt>
                                        <dd id="review-preset" class="font-semibold text-slate-950"></dd>
                                    </div>
                                </dl>
                            </article>

                            <article class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                                <h3 class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">Database</h3>
                                <dl class="mt-4 space-y-3 text-sm text-slate-700">
                                    <div class="flex items-center justify-between gap-4">
                                        <dt>Connection</dt>
                                        <dd id="review-db-connection" class="font-semibold text-slate-950"></dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <dt>Database</dt>
                                        <dd id="review-db-database" class="font-semibold text-slate-950"></dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <dt>Host</dt>
                                        <dd id="review-db-host" class="font-semibold text-slate-950"></dd>
                                    </div>
                                </dl>
                            </article>

                            <article class="rounded-3xl border border-slate-200 bg-slate-50 p-5 md:col-span-2">
                                <h3 class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">Admin</h3>
                                <dl class="mt-4 grid gap-3 md:grid-cols-3 text-sm text-slate-700">
                                    <div class="rounded-2xl bg-white px-4 py-3">
                                        <dt>Admin name</dt>
                                        <dd id="review-admin-name" class="mt-1 font-semibold text-slate-950"></dd>
                                    </div>
                                    <div class="rounded-2xl bg-white px-4 py-3">
                                        <dt>Admin email</dt>
                                        <dd id="review-admin-email" class="mt-1 font-semibold text-slate-950"></dd>
                                    </div>
                                    <div class="rounded-2xl bg-white px-4 py-3">
                                        <dt>Password</dt>
                                        <dd class="mt-1 font-semibold text-slate-950">Hidden for security</dd>
                                    </div>
                                </dl>
                            </article>
                        </div>

                        <div class="mt-6 rounded-3xl border border-slate-200 bg-slate-50 p-5">
                            <p class="text-sm text-slate-600">Guided mode runs one installer step at a time. The progress panel shows all 7 CLI-aligned steps and the output panel shows each step result before you continue.</p>
                        </div>
                    </section>

                    <div class="border-t border-slate-200 pt-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <p id="wizard-nav-copy" class="max-w-2xl text-sm leading-6 text-slate-500">Move through the form stages to review everything before the guided install starts.</p>
                            <div class="flex w-full flex-col gap-3 lg:w-auto lg:items-end">
                                <div class="grid w-full grid-cols-2 gap-3 sm:flex sm:flex-wrap sm:justify-end">
                                    <button id="wizard-prev" type="button" class="inline-flex h-12 items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 sm:min-w-[9rem]">Previous</button>
                                    <button id="wizard-next" type="button" class="inline-flex h-12 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 sm:min-w-[9rem]">Next</button>
                                </div>
                                <div id="wizard-actions" class="hidden w-full flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-end">
                                @if (in_array('install', $availableActions, true))
                                    <button type="submit" data-action="install" class="setup-action inline-flex h-12 w-full items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 sm:w-auto sm:min-w-[13rem]">Start Guided Install</button>
                                @endif
                                @if (in_array('resume', $availableActions, true))
                                    <button type="submit" data-action="resume" class="setup-action inline-flex h-12 w-full items-center justify-center rounded-2xl bg-sky-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 sm:w-auto sm:min-w-[13rem]">Run Next Step</button>
                                @endif
                                @if (in_array('upgrade', $availableActions, true))
                                    <button type="submit" data-action="upgrade" class="setup-action inline-flex h-12 w-full items-center justify-center rounded-2xl bg-amber-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700 sm:w-auto sm:min-w-[13rem]">Upgrade</button>
                                @endif
                                @if (in_array('reinstall', $availableActions, true))
                                    <button type="submit" data-action="reinstall" class="setup-action inline-flex h-12 w-full items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 sm:w-auto sm:min-w-[13rem]">Reinstall Step By Step</button>
                                @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </section>

            <aside class="space-y-6">
                <section class="rounded-3xl border border-white/60 bg-white/80 p-6 backdrop-blur dk-panel">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-slate-950">Installer progress</h2>
                            <p id="progress-meta" class="mt-1 text-sm text-slate-500">{{ $progressSummary['completed_count'] }} of {{ $progressSummary['total_steps'] }} steps completed</p>
                            <p id="progress-step-label" class="mt-2 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">All 7 installer steps completed</p>
                        </div>
                        <div id="progress-percent" class="rounded-2xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white">{{ $progressSummary['percent'] }}%</div>
                    </div>

                    <div class="mt-5 h-3 overflow-hidden rounded-full bg-slate-200">
                        <div id="progress-bar" class="h-full rounded-full bg-gradient-to-r from-sky-500 via-cyan-500 to-emerald-500 transition-all duration-300" style="width: {{ $progressSummary['percent'] }}%"></div>
                    </div>

                    <div id="progress-next-step" class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        @if ($progressSummary['next_step'])
                            Next step: <span class="font-semibold text-slate-950">{{ $progressSummary['next_step'] }}</span>
                        @else
                            All installer steps are complete.
                        @endif
                    </div>

                    <div id="progress-steps" class="mt-5 space-y-3">
                        @foreach ($progressSummary['steps'] as $step)
                            <div data-step-key="{{ $step['key'] }}" data-step-status="{{ $step['status'] }}" class="progress-step rounded-2xl border px-4 py-3 {{ $step['status'] === 'completed' ? 'border-emerald-200 bg-emerald-50' : ($step['status'] === 'current' ? 'border-sky-200 bg-sky-50' : 'border-slate-200 bg-white') }}">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-[0.24em] {{ $step['status'] === 'completed' ? 'text-emerald-700' : ($step['status'] === 'current' ? 'text-sky-700' : 'text-slate-400') }}">Step {{ $step['index'] }}</div>
                                        <div class="mt-1 text-sm font-semibold text-slate-900">{{ $step['label'] }}</div>
                                    </div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.2em] {{ $step['status'] === 'completed' ? 'text-emerald-700' : ($step['status'] === 'current' ? 'text-sky-700' : 'text-slate-400') }}">{{ $step['status'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-3xl border border-white/60 bg-white/80 p-6 backdrop-blur dk-panel">
                    <h2 class="text-lg font-bold text-slate-950">Environment snapshot</h2>
                    <dl class="mt-4 space-y-3 text-sm text-slate-600">
                        <div class="flex items-center justify-between gap-4 rounded-2xl bg-slate-50 px-4 py-3">
                            <dt class="font-medium text-slate-500">App URL</dt>
                            <dd class="text-right text-slate-900">{{ $appUrl }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 rounded-2xl bg-slate-50 px-4 py-3">
                            <dt class="font-medium text-slate-500">DB Driver</dt>
                            <dd class="text-right text-slate-900">{{ $dbConnection }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-3xl border border-white/60 bg-slate-950 p-6 text-slate-100 dk-panel">
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="text-lg font-bold">Installer output</h2>
                        <span id="status-badge" class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-200">Idle</span>
                    </div>
                    <p id="message" class="mt-3 text-sm text-slate-300">Use the test button first, then run the installer.</p>
                    <p id="last-completed-step" class="mt-2 text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">No installer step completed yet.</p>
                    <p id="retry-hint" class="hidden mt-2 rounded-2xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-xs font-medium leading-5 text-rose-100"></p>
                    <pre id="output" class="mt-4 max-h-[420px] overflow-auto rounded-2xl border border-white/10 bg-black/30 p-4 text-xs leading-6 text-slate-200">No output yet.</pre>
                </section>
            </aside>
        </main>
    </div>

    <div id="confirm-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/55 px-4">
        <div class="w-full max-w-lg rounded-3xl border border-white/60 bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Confirm action</p>
                    <h2 id="confirm-title" class="mt-2 text-2xl font-bold text-slate-950">Continue?</h2>
                </div>
                <button id="confirm-close" type="button" class="rounded-full border border-slate-200 px-3 py-1 text-sm font-semibold text-slate-500 transition hover:bg-slate-50">Close</button>
            </div>

            <p id="confirm-body" class="mt-4 text-sm leading-6 text-slate-600"></p>

            <div class="mt-6 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-600">
                <p id="confirm-note" class="font-medium text-slate-700">This action can change published files, route wiring, or installation state in the current project.</p>
                <ul id="confirm-effects" class="mt-3 space-y-2 text-slate-600"></ul>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button id="confirm-cancel" type="button" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-50">Cancel</button>
                <button id="confirm-accept" type="button" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Continue</button>
            </div>
        </div>
    </div>

    @php
        $modalTraceContext = [
            'lifecycleStatus' => $traceReport['lifecycle_status'],
            'traceStatus' => $traceReport['status'],
            'presentCount' => $traceReport['present_count'],
            'installedVersion' => $traceReport['installed_version'],
            'recommendedAction' => $traceReport['recommended_action'],
            'recommendedCommand' => $traceReport['recommended_command'],
            'highlights' => array_values(array_map(static function (array $trace): string {
                return (string) $trace['label'];
            }, $traceHighlights)),
            'hasProgress' => $progressSummary['has_progress'],
            'setupReady' => $progressSummary['setup_ready'],
            'nextStep' => $progressSummary['next_step'],
            'completedSteps' => array_values($progressSummary['completed']),
        ];
    @endphp

    <script>
        const traceContext = @json($modalTraceContext);
        const initialInstallerProgress = @json($progressSummary);
        const form = document.getElementById('setup-form');
        const testButton = document.getElementById('test-connection');
        const actionInput = document.getElementById('action-input');
        const actionButtons = Array.from(document.querySelectorAll('.setup-action'));
        const wizardStages = Array.from(document.querySelectorAll('.wizard-stage'));
        const wizardTrackSteps = Array.from(document.querySelectorAll('.wizard-track-step'));
        const wizardPrev = document.getElementById('wizard-prev');
        const wizardNext = document.getElementById('wizard-next');
        const wizardActions = document.getElementById('wizard-actions');
        const wizardStageBadge = document.getElementById('wizard-stage-badge');
        const wizardStageCopy = document.getElementById('wizard-stage-copy');
        const wizardNavCopy = document.getElementById('wizard-nav-copy');
        const wizardLockBanner = document.getElementById('wizard-lock-banner');
        const output = document.getElementById('output');
        const message = document.getElementById('message');
        const lastCompletedStep = document.getElementById('last-completed-step');
        const retryHint = document.getElementById('retry-hint');
        const statusBadge = document.getElementById('status-badge');
        const dbConnection = document.getElementById('db_connection');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const confirmModal = document.getElementById('confirm-modal');
        const confirmTitle = document.getElementById('confirm-title');
        const confirmBody = document.getElementById('confirm-body');
        const confirmNote = document.getElementById('confirm-note');
        const confirmEffects = document.getElementById('confirm-effects');
        const confirmAccept = document.getElementById('confirm-accept');
        const confirmCancel = document.getElementById('confirm-cancel');
        const confirmClose = document.getElementById('confirm-close');
        const progressPercent = document.getElementById('progress-percent');
        const progressMeta = document.getElementById('progress-meta');
        const progressStepLabel = document.getElementById('progress-step-label');
        const progressBar = document.getElementById('progress-bar');
        const progressNextStep = document.getElementById('progress-next-step');
        const progressSteps = document.getElementById('progress-steps');
        const lockedSnapshot = document.getElementById('locked-snapshot');
        const snapshotTitle = document.getElementById('snapshot-title');
        const snapshotStep = document.getElementById('snapshot-step');
        const snapshotNote = document.getElementById('snapshot-note');
        const snapshotSummaryPanel = document.getElementById('snapshot-summary-panel');
        const confirmedActions = new Set();
        let formLocked = Boolean(initialInstallerProgress && initialInstallerProgress.completed_count > 0 && initialInstallerProgress.setup_ready);
        let lockedPayload = null;
        const reviewFields = {
            appName: document.getElementById('review-app-name'),
            preset: document.getElementById('review-preset'),
            dbConnection: document.getElementById('review-db-connection'),
            dbDatabase: document.getElementById('review-db-database'),
            dbHost: document.getElementById('review-db-host'),
            adminName: document.getElementById('review-admin-name'),
            adminEmail: document.getElementById('review-admin-email'),
        };
        const snapshotFields = {
            appName: document.getElementById('snapshot-app-name'),
            preset: document.getElementById('snapshot-preset'),
            dbSummary: document.getElementById('snapshot-db-summary'),
            adminEmail: document.getElementById('snapshot-admin-email'),
        };
        let currentWizardStage = 0;
        const wizardStageDetails = [
            {
                title: 'Step 1 of 4',
                copy: 'Set the application name and preset that Dashkit should prepare.',
                nav: 'Project details are first so the review stage can reflect the correct app identity and preset.',
            },
            {
                title: 'Step 2 of 4',
                copy: 'Define the database target and optionally test the connection before continuing.',
                nav: 'Use Test connection here. Guided install can continue without it, but verifying now reduces step 7 surprises.',
            },
            {
                title: 'Step 3 of 4',
                copy: 'Provide the initial admin account that will be seeded during the last installer step.',
                nav: 'These credentials are written only when the database migration and seeding step runs.',
            },
            {
                title: 'Step 4 of 4',
                copy: 'Review the collected inputs, then start or continue the guided installer.',
                nav: 'When you start here, the browser runs only one CLI-aligned installer step per action.',
            },
        ];
        const actionMessages = {
            install: 'This will start the guided Dashkit installation and run the next pending CLI step.',
            resume: 'This will continue the guided Dashkit installation by running the next pending step.',
            reinstall: 'This will rerun Dashkit step by step and may overwrite published files or setup state.',
            upgrade: 'This will run the Dashkit upgrade workflow against the current project. Continue?',
        };
        const actionConsequences = {
            reinstall: {
                note: 'Reinstall is intended for leftover or inconsistent Dashkit states.',
                effects: [
                    'Published config, views, and assets may be overwritten if the installer needs to refresh them.',
                    'Saved install progress will be reused and forced through the installer flow again.',
                    'Route wiring, guest redirect setup, and generated preset pages can be reapplied.',
                ],
            },
            upgrade: {
                note: 'Upgrade is intended for an existing Dashkit installation that should move to the current package state.',
                effects: [
                    'Config, views, and public assets may be republished depending on upgrade decisions and force flags.',
                    'Default routes, preset pages, and runtime Dashkit metadata can be refreshed to match the current package.',
                    'Migrations and cache-clearing steps will run as part of the upgrade workflow.',
                ],
            },
        };

        function detailLine(label, value) {
            if (value === null || value === undefined || value === '') {
                return null;
            }

            return label + ': ' + value;
        }

        function buildContextEffects(action) {
            const contextEffects = [];

            contextEffects.push(detailLine('Detected traces', traceContext.presentCount));
            contextEffects.push(detailLine('Trace status', traceContext.traceStatus));
            contextEffects.push(detailLine('Lifecycle status', traceContext.lifecycleStatus.replace(/-/g, ' ')));

            if (action === 'upgrade') {
                contextEffects.push(detailLine('Installed version', traceContext.installedVersion || 'unknown'));
            }

            if (action === 'reinstall' || action === 'resume') {
                contextEffects.push(detailLine('Saved progress found', traceContext.hasProgress ? 'yes' : 'no'));

                if (traceContext.hasProgress) {
                    contextEffects.push(detailLine('Setup data ready', traceContext.setupReady ? 'yes' : 'no'));
                    contextEffects.push(detailLine('Next step', traceContext.nextStep || 'Completed'));
                }
            }

            if (Array.isArray(traceContext.highlights) && traceContext.highlights.length > 0) {
                contextEffects.push(detailLine('Detected items', traceContext.highlights.slice(0, 4).join(', ')));
            }

            return contextEffects.filter(Boolean);
        }

        function buildConfirmationDetails(action) {
            const consequences = actionConsequences[action] || {
                note: 'Please confirm that you want to continue.',
                effects: [],
            };

            return {
                note: consequences.note,
                effects: [...buildContextEffects(action), ...consequences.effects],
            };
        }

        function setStatus(label, tone) {
            statusBadge.textContent = label;
            statusBadge.className = 'rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em]';

            if (tone === 'success') {
                statusBadge.classList.add('bg-emerald-500/20', 'text-emerald-200');
                return;
            }

            if (tone === 'error') {
                statusBadge.classList.add('bg-rose-500/20', 'text-rose-200');
                return;
            }

            if (tone === 'progress') {
                statusBadge.classList.add('bg-sky-500/20', 'text-sky-200');
                return;
            }

            statusBadge.classList.add('bg-white/10', 'text-slate-200');
        }

        function stepClasses(status) {
            if (status === 'completed') {
                return {
                    card: 'border-emerald-200 bg-emerald-50',
                    text: 'text-emerald-700',
                };
            }

            if (status === 'current') {
                return {
                    card: 'border-sky-200 bg-sky-50',
                    text: 'text-sky-700',
                };
            }

            return {
                card: 'border-slate-200 bg-white',
                text: 'text-slate-400',
            };
        }

        function snapshotPhase(progress) {
            if (! progress || ! progress.next_step_key) {
                return {
                    wrapper: ['border-slate-200', 'bg-slate-50/80'],
                    title: 'text-slate-700',
                    note: 'text-slate-900',
                    panel: 'bg-white/80',
                    message: 'All installer steps are complete. The locked values below represent the final setup used by Dashkit.',
                    stepLabel: 'All 7 installer steps completed',
                };
            }

            const activeStep = progress.steps.find((step) => step.key === progress.next_step_key);
            const stepLabel = activeStep
                ? `Step ${activeStep.index}/${progress.total_steps}`
                : 'Step context unavailable';

            if (['publish_config', 'publish_views', 'publish_assets', 'route_registration'].includes(progress.next_step_key)) {
                return {
                    wrapper: ['border-sky-200', 'bg-sky-50/80'],
                    title: 'text-sky-700',
                    note: 'text-sky-900',
                    panel: 'bg-white/85',
                    message: 'Dashkit is still in the publishing and route wiring phase. Locked values will be used when setup and migration steps are reached.',
                    stepLabel,
                };
            }

            if (progress.next_step_key === 'env_setup') {
                return {
                    wrapper: ['border-amber-200', 'bg-amber-50/80'],
                    title: 'text-amber-700',
                    note: 'text-amber-900',
                    panel: 'bg-white/85',
                    message: 'Dashkit is about to apply environment and credential values. The locked snapshot below is the exact input set queued for that step.',
                    stepLabel,
                };
            }

            if (progress.next_step_key === 'post_setup') {
                return {
                    wrapper: ['border-cyan-200', 'bg-cyan-50/80'],
                    title: 'text-cyan-700',
                    note: 'text-cyan-900',
                    panel: 'bg-white/85',
                    message: 'Dashkit is preparing preset pages and redirect behavior. The locked values now drive the generated dashboard shape.',
                    stepLabel,
                };
            }

            return {
                wrapper: ['border-emerald-200', 'bg-emerald-50/80'],
                title: 'text-emerald-700',
                note: 'text-emerald-900',
                panel: 'bg-white/85',
                message: 'Dashkit is in the final migration and seeding phase. The locked snapshot below is the exact configuration being finalized.',
                stepLabel,
            };
        }

        function renderSnapshotTone(progress) {
            const phase = snapshotPhase(progress);

            lockedSnapshot.classList.remove('border-amber-200', 'bg-amber-50/80', 'border-sky-200', 'bg-sky-50/80', 'border-cyan-200', 'bg-cyan-50/80', 'border-emerald-200', 'bg-emerald-50/80', 'border-slate-200', 'bg-slate-50/80');
            lockedSnapshot.classList.add(...phase.wrapper);
            snapshotTitle.className = `text-sm font-semibold uppercase tracking-[0.24em] ${phase.title}`;
            snapshotStep.textContent = phase.stepLabel;
            snapshotNote.className = `mt-2 text-sm ${phase.note}`;
            snapshotNote.textContent = phase.message;
            snapshotSummaryPanel.className = `rounded-2xl px-4 py-3 text-sm text-slate-700 ${phase.panel}`;
        }

        function renderProgress(progress) {
            if (! progress) {
                return;
            }

            const phase = snapshotPhase(progress);

            progressPercent.textContent = `${progress.percent}%`;
            progressMeta.textContent = `${progress.completed_count} of ${progress.total_steps} steps completed`;
            progressStepLabel.textContent = phase.stepLabel;
            progressBar.style.width = `${progress.percent}%`;
            progressNextStep.innerHTML = progress.next_step
                ? `Next step: <span class="font-semibold text-slate-950">${progress.next_step}</span>`
                : 'All installer steps are complete.';
            renderSnapshotTone(progress);

            progressSteps.innerHTML = '';

            progress.steps.forEach((step) => {
                const classes = stepClasses(step.status);
                const card = document.createElement('div');
                card.dataset.stepKey = step.key;
                card.dataset.stepStatus = step.status;
                card.className = `progress-step rounded-2xl border px-4 py-3 ${classes.card}`;
                card.innerHTML = `
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.24em] ${classes.text}">Step ${step.index}</div>
                            <div class="mt-1 text-sm font-semibold text-slate-900">${step.label}</div>
                        </div>
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] ${classes.text}">${step.status}</div>
                    </div>
                `;
                progressSteps.appendChild(card);
            });
        }

        function updateLastCompletedStep(explicitStep, progress) {
            if (explicitStep && explicitStep.label && explicitStep.index && explicitStep.total) {
                lastCompletedStep.textContent = `Last completed: Step ${explicitStep.index}/${explicitStep.total} ${explicitStep.label}`;
                return;
            }

            if (progress && Array.isArray(progress.completed) && progress.completed.length > 0) {
                const stepNumber = progress.completed.length;
                const totalSteps = progress.total_steps || 7;
                const label = progress.completed[progress.completed.length - 1];
                lastCompletedStep.textContent = `Last completed: Step ${stepNumber}/${totalSteps} ${label}`;
                return;
            }

            lastCompletedStep.textContent = 'No installer step completed yet.';
        }

        function setRetryHint(hint) {
            if (! hint) {
                retryHint.textContent = '';
                retryHint.classList.add('hidden');
                return;
            }

            retryHint.textContent = hint;
            retryHint.classList.remove('hidden');
        }

        function toggleDbFields() {
            const sqlite = dbConnection.value === 'sqlite';
            document.querySelectorAll('.db-network-field').forEach((field) => {
                field.style.display = sqlite ? 'none' : 'block';
            });
        }

        function setFormLocked(locked) {
            formLocked = locked;

            if (locked && ! lockedPayload) {
                lockedPayload = Object.fromEntries(new FormData(form).entries());
            }

            wizardLockBanner.classList.toggle('hidden', ! locked);
            lockedSnapshot.classList.toggle('hidden', ! locked);

            document.querySelectorAll('[data-lockable="true"]').forEach((section) => {
                section.classList.toggle('pointer-events-none', locked);
                section.classList.toggle('select-none', locked);
                section.classList.toggle('opacity-70', locked);
            });

            testButton.disabled = locked;
            testButton.classList.toggle('opacity-60', locked);
            testButton.classList.toggle('cursor-not-allowed', locked);
        }

        function updateReview() {
            const payload = formPayload();
            reviewFields.appName.textContent = payload.app_name || '-';
            reviewFields.preset.textContent = payload.preset || '-';
            reviewFields.dbConnection.textContent = payload.db_connection || '-';
            reviewFields.dbDatabase.textContent = payload.db_database || '-';
            reviewFields.dbHost.textContent = payload.db_connection === 'sqlite' ? 'Local SQLite file' : (payload.db_host || '-');
            reviewFields.adminName.textContent = payload.admin_name || '-';
            reviewFields.adminEmail.textContent = payload.admin_email || '-';
            snapshotFields.appName.textContent = payload.app_name || '-';
            snapshotFields.preset.textContent = payload.preset || '-';
            snapshotFields.dbSummary.textContent = payload.db_connection === 'sqlite'
                ? `sqlite:${payload.db_database || '-'}`
                : `${payload.db_connection || '-'} @ ${payload.db_host || '-'} / ${payload.db_database || '-'}`;
            snapshotFields.adminEmail.textContent = payload.admin_email || '-';
        }

        function stageInputElements(stageIndex) {
            const stage = wizardStages.find((item) => Number(item.dataset.stage) === stageIndex);

            if (! stage) {
                return [];
            }

            return Array.from(stage.querySelectorAll('input, select, textarea'));
        }

        function validateStage(stageIndex) {
            const fields = stageInputElements(stageIndex).filter((element) => {
                if (element.disabled) {
                    return false;
                }

                if (element.closest('.db-network-field') && dbConnection.value === 'sqlite') {
                    return false;
                }

                return true;
            });

            for (const field of fields) {
                if (! field.reportValidity()) {
                    field.focus();
                    return false;
                }
            }

            return true;
        }

        function renderWizardStage() {
            wizardStages.forEach((stage) => {
                stage.classList.toggle('hidden', Number(stage.dataset.stage) !== currentWizardStage);
            });

            wizardTrackSteps.forEach((step, index) => {
                const active = index === currentWizardStage;
                const completed = index < currentWizardStage;
                step.className = `wizard-track-step rounded-2xl border px-4 py-3 ${completed ? 'border-emerald-200 bg-emerald-50' : (active ? 'border-sky-200 bg-white' : 'border-slate-200 bg-white')}`;
                const eyebrow = step.querySelector('div:first-child');

                if (eyebrow) {
                    eyebrow.className = `text-xs font-semibold uppercase tracking-[0.24em] ${completed ? 'text-emerald-700' : (active ? 'text-sky-700' : 'text-slate-400')}`;
                }
            });

            wizardStageBadge.textContent = wizardStageDetails[currentWizardStage].title;
            wizardStageCopy.textContent = wizardStageDetails[currentWizardStage].copy;
            wizardNavCopy.textContent = wizardStageDetails[currentWizardStage].nav;
            wizardPrev.classList.toggle('hidden', currentWizardStage === 0);
            wizardNext.classList.toggle('hidden', currentWizardStage === wizardStages.length - 1);
            wizardActions.classList.toggle('hidden', currentWizardStage !== wizardStages.length - 1);
            wizardActions.classList.toggle('flex', currentWizardStage === wizardStages.length - 1);

            if (currentWizardStage === wizardStages.length - 1) {
                updateReview();
            }
        }

        async function postJson(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (!response.ok && data.errors) {
                const lines = Object.values(data.errors).flat().join('\n');
                throw new Error(lines);
            }

            return data;
        }

        function formPayload() {
            const payload = Object.fromEntries(new FormData(form).entries());

            if (! formLocked || ! lockedPayload) {
                return payload;
            }

            return {
                ...payload,
                ...lockedPayload,
                token: payload.token,
                action: payload.action,
            };
        }

        function openConfirmationModal(action) {
            confirmTitle.textContent = action === 'upgrade' ? 'Confirm upgrade' : 'Confirm reinstall';
            confirmBody.textContent = actionMessages[action] || 'Continue?';
            const details = buildConfirmationDetails(action);
            confirmNote.textContent = details.note;
            confirmEffects.innerHTML = '';

            details.effects.forEach((effect) => {
                const item = document.createElement('li');
                item.className = 'flex items-start gap-2';
                item.innerHTML = '<span class="mt-1 inline-block h-2 w-2 rounded-full bg-slate-400"></span><span>' + effect + '</span>';
                confirmEffects.appendChild(item);
            });

            confirmModal.classList.remove('hidden');
            confirmModal.classList.add('flex');
            confirmAccept.focus();

            return new Promise((resolve) => {
                function cleanup(result) {
                    confirmModal.classList.add('hidden');
                    confirmModal.classList.remove('flex');
                    confirmAccept.removeEventListener('click', onAccept);
                    confirmCancel.removeEventListener('click', onCancel);
                    confirmClose.removeEventListener('click', onCancel);
                    confirmModal.removeEventListener('click', onBackdrop);
                    document.removeEventListener('keydown', onEscape);
                    resolve(result);
                }

                function onAccept() {
                    cleanup(true);
                }

                function onCancel() {
                    cleanup(false);
                }

                function onBackdrop(event) {
                    if (event.target === confirmModal) {
                        cleanup(false);
                    }
                }

                function onEscape(event) {
                    if (event.key === 'Escape') {
                        cleanup(false);
                    }
                }

                confirmAccept.addEventListener('click', onAccept);
                confirmCancel.addEventListener('click', onCancel);
                confirmClose.addEventListener('click', onCancel);
                confirmModal.addEventListener('click', onBackdrop);
                document.addEventListener('keydown', onEscape);
            });
        }

        function setButtonsDisabled(disabled) {
            actionButtons.forEach((button) => {
                button.disabled = disabled;
                button.classList.toggle('opacity-60', disabled);
                button.classList.toggle('cursor-not-allowed', disabled);
            });
        }

        actionButtons.forEach((button) => {
            button.addEventListener('click', () => {
                actionInput.value = button.dataset.action || 'install';
            });
        });

        wizardPrev.addEventListener('click', () => {
            currentWizardStage = Math.max(0, currentWizardStage - 1);
            renderWizardStage();
        });

        wizardNext.addEventListener('click', () => {
            if (! validateStage(currentWizardStage)) {
                return;
            }

            currentWizardStage = Math.min(wizardStages.length - 1, currentWizardStage + 1);
            renderWizardStage();
        });

        testButton.addEventListener('click', async () => {
            setStatus('Testing', 'progress');
            message.textContent = 'Checking the database connection...';
            updateLastCompletedStep(null, initialInstallerProgress);
            setRetryHint('');
            output.textContent = 'Running connection test...';

            try {
                const data = await postJson('{{ route('dashkit.setup.test-connection') }}', formPayload());
                setStatus(data.success ? 'Connected' : 'Failed', data.success ? 'success' : 'error');
                message.textContent = data.message;
                output.textContent = data.message;
            } catch (error) {
                setStatus('Failed', 'error');
                message.textContent = error.message;
                output.textContent = error.message;
            }
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (! validateStage(currentWizardStage)) {
                return;
            }

            const action = actionInput.value || 'install';

            if ((action === 'reinstall' || action === 'upgrade') && ! confirmedActions.has(action)) {
                const confirmed = await openConfirmationModal(action);

                if (! confirmed) {
                    return;
                }

                confirmedActions.add(action);
            }

            setButtonsDisabled(true);
            setStatus('Installing', 'progress');
            message.textContent = actionMessages[action] || `Running Dashkit ${action}. This can take a moment.`;
            output.textContent = `Starting ${action}...`;

            try {
                const data = await postJson('{{ route('dashkit.setup.install') }}', formPayload());
                renderProgress(data.progress || initialInstallerProgress);
                if (data.progress && data.progress.completed_count > 0) {
                    setFormLocked(true);
                }
                updateLastCompletedStep(data.executed_step || null, data.progress || initialInstallerProgress);
                setStatus(data.installation_complete ? 'Completed' : 'Step done', data.success ? 'success' : 'error');
                setRetryHint(data.success ? '' : (data.retry_hint || 'Fix the reported issue and run Next Step again.'));
                message.textContent = data.message;
                output.textContent = data.output || data.message;

                if (data.installation_complete) {
                    setButtonsDisabled(true);
                    message.textContent = `${data.message} Open the dashboard when you are ready.`;
                }
            } catch (error) {
                setStatus('Failed', 'error');
                message.textContent = error.message;
                setRetryHint('Fix the reported issue and run Next Step again.');
                output.textContent = error.message;
            } finally {
                if (statusBadge.textContent !== 'Completed') {
                    setButtonsDisabled(false);
                }
            }
        });

        renderProgress(initialInstallerProgress);
        updateLastCompletedStep(null, initialInstallerProgress);
        renderWizardStage();
    setFormLocked(formLocked);
        toggleDbFields();
        dbConnection.addEventListener('change', toggleDbFields);
        form.addEventListener('input', () => {
            if (currentWizardStage === wizardStages.length - 1) {
                updateReview();
            }
        });
    </script>
</body>
</html>