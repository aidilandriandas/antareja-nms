<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\Anomaly;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AnomalyController extends Controller
{
    public function index(): View
    {
        return view('antareja::assistant.anomalies', [
            'open' => Anomaly::with('device')->where('status', 'open')->orderByDesc('score')->get(),
            'closed' => Anomaly::with('device')->where('status', 'closed')->where('closed_at', '>=', now()->subDays(7))->latest('closed_at')->limit(50)->get(),
        ]);
    }
}
