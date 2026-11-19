<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\MaintenanceWindow;
use Antareja\Models\Site;
use Antareja\Services\AttachmentService;
use Antareja\Services\MaintenanceService;
use App\Models\Device;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceService $maintenance)
    {
    }

    public function index(Request $request): View
    {
        $month = rescue(fn () => Carbon::createFromFormat('Y-m', (string) $request->input('month')), null, false)?->startOfMonth() ?? now()->startOfMonth();
        $gridStart = $month->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $windows = MaintenanceWindow::with(['site', 'picUser'])->withCount('devices')
            ->where('starts_at', '<=', $gridEnd)->where('ends_at', '>=', $gridStart)
            ->when($request->filled('site'), fn ($q) => $q->where('site_id', (int) $request->input('site')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->orderBy('starts_at')->get();

        $days = [];
        for ($d = $gridStart->copy(); $d->lte($gridEnd); $d->addDay()) {
            $days[] = [
                'date' => $d->copy(),
                'items' => $windows->filter(fn ($w) => $w->starts_at->copy()->startOfDay()->lte($d) && $w->ends_at->gte($d->copy()->startOfDay())),
            ];
        }

        return view('antareja::maintenance.index', [
            'month' => $month,
            'days' => $days,
            'upcoming' => MaintenanceWindow::with('site')->withCount('devices')->whereIn('status', ['planned', 'in_progress'])
                ->where('ends_at', '>=', now())->orderBy('starts_at')->limit(20)->get(),
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        $start = now()->addDay()->setTime(8, 0);

        return view('antareja::maintenance.form', $this->formData(new MaintenanceWindow([
            'type' => 'preventive', 'starts_at' => $start, 'ends_at' => $start->copy()->addHours(2), 'exclude_from_sla' => true,
        ]), $request->filled('device') ? [(int) $request->input('device')] : []));
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $devices] = $this->validated($request);
        $window = $this->maintenance->save(new MaintenanceWindow(), $data, $devices, $request->user()->user_id);

        return redirect()->route('antareja.maintenance.show', $window)->with('antareja_success', "Maintenance \"{$window->title}\" dijadwalkan untuk {$window->devices()->count()} perangkat.");
    }

    public function show(MaintenanceWindow $window, AttachmentService $attachments): View
    {
        return view('antareja::maintenance.show', [
            'window' => $window->load(['devices', 'site', 'picUser']),
            'attachments' => $attachments->for('maintenance', $window->id),
        ]);
    }

    public function edit(MaintenanceWindow $window): View
    {
        abort_if(in_array($window->status, ['completed', 'cancelled'], true), 403, 'Maintenance yang selesai / dibatalkan tidak bisa diubah.');

        return view('antareja::maintenance.form', $this->formData($window, $window->devices()->pluck('devices.device_id')->all()));
    }

    public function update(Request $request, MaintenanceWindow $window): RedirectResponse
    {
        abort_if(in_array($window->status, ['completed', 'cancelled'], true), 403);
        [$data, $devices] = $this->validated($request);
        $this->maintenance->save($window, $data, $devices, $request->user()->user_id);

        return redirect()->route('antareja.maintenance.show', $window)->with('antareja_success', 'Maintenance disimpan.');
    }

    public function complete(Request $request, MaintenanceWindow $window): RedirectResponse
    {
        $data = $request->validate([
            'work_done' => ['required', 'string', 'max:5000'],
            'result' => ['required', Rule::in(array_keys(MaintenanceWindow::RESULTS))],
        ], [], ['work_done' => 'pekerjaan yang dilakukan', 'result' => 'hasil']);
        $this->maintenance->complete($window, $data['work_done'], $data['result']);

        return back()->with('antareja_success', 'Maintenance ditandai selesai. Penekanan alert diakhiri.');
    }

    public function cancel(MaintenanceWindow $window): RedirectResponse
    {
        $this->maintenance->cancel($window);

        return back()->with('antareja_success', 'Maintenance dibatalkan dan jadwal penekanan alert dihapus.');
    }

    public function attach(Request $request, MaintenanceWindow $window, AttachmentService $attachments): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:' . AttachmentService::ALLOWED_MIMES, 'max:' . AttachmentService::MAX_KB],
            'description' => ['nullable', 'string', 'max:255'],
        ], ['file.mimes' => 'Evidence harus berupa JPG, PNG, WEBP, atau PDF.', 'file.max' => 'Ukuran evidence maksimal 10 MB.']);
        $attachments->store('maintenance', $window->id, $request->file('file'), $request->input('description'));

        return back()->with('antareja_success', 'Evidence diunggah.');
    }

    /**
     * @return array{0:array, 1:int[]}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(MaintenanceWindow::TYPES))],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'description' => ['nullable', 'string', 'max:5000'],
            'site_id' => ['nullable', 'integer', 'exists:ant_sites,id'],
            'pic_user_id' => ['nullable', 'integer', 'exists:users,user_id'],
            'pic_name' => ['nullable', 'string', 'max:128'],
            'devices' => ['nullable', 'array', 'max:1000'],
            'devices.*' => ['integer', 'exists:devices,device_id'],
        ], ['ends_at.after' => 'Waktu selesai harus setelah waktu mulai.'], ['title' => 'judul', 'starts_at' => 'waktu mulai', 'ends_at' => 'waktu selesai']);

        $devices = $data['devices'] ?? [];
        unset($data['devices']);
        $data['exclude_from_sla'] = $request->boolean('exclude_from_sla');
        $data['include_site_devices'] = $request->boolean('include_site_devices');
        if (! $devices && ! ($data['include_site_devices'] && $data['site_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['devices' => 'Pilih perangkat yang terdampak, atau pilih site dan centang "semua perangkat di site".']);
        }

        return [$data, $devices];
    }

    private function formData(MaintenanceWindow $window, array $selected): array
    {
        return [
            'window' => $window,
            'selected' => $selected,
            'devices' => Device::orderBy('hostname')->get(),
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
            'users' => User::orderBy('username')->get(['user_id', 'username', 'realname']),
        ];
    }
}
