<?php

namespace Antareja\View;

use Antareja\Models\Problem;
use Antareja\Support\Navigation;
use Antareja\Support\Severity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Data untuk kerangka aplikasi ANTAREJA (sidebar + topbar). */
class ShellComposer
{
    public function __construct(private readonly Navigation $navigation, private readonly Request $request)
    {
    }

    public function compose(View $view): void
    {
        $user = Auth::user();
        $problems = null;
        if ($user?->can('ant-problem.view')) {
            try {
                $open = Problem::open()->whereNull('suppressed_reason')->pluck('severity');
                $top = $open->sortByDesc(fn ($s) => Severity::problemRank($s))->first();
                $problems = ['count' => $open->count(), 'tone' => $top ? Severity::problem($top)['tone'] : 'ok'];
            } catch (\Throwable) {
                $problems = null; // tabel belum ada (migrasi berjalan)
            }
        }

        $view->with([
            'ant_nav' => $this->navigation->for($user, $this->request),
            'ant_problems' => $problems,
            'ant_role' => $user ? ($user->roles->pluck('title')->filter()->first() ?? $user->roles->pluck('name')->first()) : null,
        ]);
    }
}
