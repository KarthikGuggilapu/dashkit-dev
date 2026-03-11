<?php

namespace Dashkit\Http\Controllers;

use Dashkit\Services\WidgetRegistry;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly WidgetRegistry $widgets)
    {
    }

    public function index(): View
    {
        return view('dashkit::dashboard', [
            'widgets' => $this->widgets->resolveAll(),
            'title' => config('dashkit.name', 'Dashkit'),
        ]);
    }

    public function page(string $page): View
    {
        $configuredNamespace = (string) config('dashkit.generated_pages_namespace', 'dashkit.pages');
        $view = $configuredNamespace . '.' . $page;

        if (! view()->exists($view)) {
            $view = 'dashkit::pages.' . $page;
        }

        abort_unless(view()->exists($view), 404);

        return view($view, [
            'widgets' => $this->widgets->resolveAll(),
            'title' => ucfirst(str_replace(['-', '_'], ' ', $page)),
            'slug' => $page,
        ]);
    }
}
