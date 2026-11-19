<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public const TYPES = [
        'device' => 'Perangkat / inventaris',
        'device_link' => 'Device mapping',
        'site' => 'Site',
        'area' => 'Area',
        'user' => 'User / login',
    ];

    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->input('event')))
            ->when($request->filled('type'), fn ($q) => $q->where('auditable_type', $request->input('type')))
            ->when($request->filled('user'), fn ($q) => $q->where('username', 'like', '%' . $request->input('user') . '%'))
            ->when($request->filled('q'), fn ($q) => $q->where('description', 'like', '%' . $request->input('q') . '%'))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->latest('created_at')->latest('id')
            ->paginate(100)->withQueryString();

        return view('antareja::audit.index', ['logs' => $logs, 'types' => self::TYPES, 'events' => AuditLog::EVENTS]);
    }
}
