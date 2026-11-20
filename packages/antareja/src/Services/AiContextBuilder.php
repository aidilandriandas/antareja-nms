<?php

namespace Antareja\Services;

use Antareja\Models\Anomaly;
use Antareja\Models\Change;
use Antareja\Models\ConfigVersion;
use Antareja\Models\DeviceDetail;
use Antareja\Models\Incident;
use Antareja\Models\MaintenanceWindow;
use Antareja\Models\Problem;
use Antareja\Models\QualityTarget;
use Antareja\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

/**
 * Menyusun data yang dikirim ke asisten AI sebagai teks ringkas.
 * TIDAK PERNAH menyertakan: isi konfigurasi perangkat, kredensial, kunci, email/telepon pengguna.
 * Alamat IP & MAC disamarkan (AiAssistant::redact) kecuali dinonaktifkan.
 */
class AiContextBuilder
{
    public const SYSTEM = <<<'TXT'
Anda adalah asisten engineer NOC jaringan & ICT di sebuah tambang. Anda menulis DRAF dalam Bahasa Indonesia yang akan diperiksa engineer sebelum dipakai.
Aturan:
- Gunakan hanya fakta di dalam <data>. Bila sesuatu tidak ada di data, katakan "tidak ada data" — jangan menebak nama perangkat, angka, atau waktu.
- Bedakan fakta dari dugaan. Setiap dugaan penyebab harus menyebut bukti dari data (mis. "problem X mulai 08:12, 2 menit setelah konfigurasi berubah").
- Anda tidak bisa dan tidak boleh menjalankan perintah atau mengubah konfigurasi perangkat. Saran berupa langkah pengecekan yang dilakukan engineer.
- Tulis ringkas dan langsung; gunakan judul bagian dan butir. Jangan mengulang seluruh data.
TXT;

    public function incident(Incident $incident): string
    {
        $incident->loadMissing(['device', 'site', 'problems.sources', 'notes']);
        $L = [];
        $L[] = "INCIDENT {$incident->number}: {$incident->title}";
        $L[] = "Severity {$incident->severity}, status {$incident->status}, mulai {$this->t($incident->started_at)}"
            . ($incident->acknowledged_at ? ", ack {$this->t($incident->acknowledged_at)}" : '')
            . ($incident->resolved_at ? ", resolved {$this->t($incident->resolved_at)}" : '');
        $L[] = 'Site: ' . ($incident->site ? "{$incident->site->code} {$incident->site->name}" : '-');
        if ($incident->description) {
            $L[] = 'Deskripsi: ' . $this->clip($incident->description, 1500);
        }

        if ($d = $incident->device) {
            $det = DeviceDetail::find($d->device_id);
            $L[] = '';
            $L[] = "PERANGKAT: {$d->displayName()} (os {$d->os}, tipe {$d->type}, kategori " . ($det?->category ?? '-') . ', status kini ' . ($d->status ? 'up' : 'down') . ')';
            $outages = DB::table('device_outages')->where('device_id', $d->device_id)->where('going_down', '>=', time() - 7 * 86400)->orderBy('going_down')->get();
            $L[] = 'Outage 7 hari: ' . ($outages->isEmpty() ? 'tidak ada' : $outages->map(fn ($o) => $this->t($o->going_down) . ' – ' . ($o->up_again ? $this->t($o->up_again) : 'belum pulih'))->implode('; '));
            $parents = DB::table('device_relationships')->where('child_device_id', $d->device_id)->pluck('parent_device_id');
            if ($parents->isNotEmpty()) {
                $L[] = 'Perangkat induk (dependensi): ' . \App\Models\Device::whereIn('device_id', $parents)->get()->map(fn ($p) => $p->displayName() . ' ' . ($p->status ? 'up' : 'down'))->implode(', ');
            }
            $cfg = ConfigVersion::with('change')->where('device_id', $d->device_id)->where('collected_at', '>=', $incident->started_at->copy()->subDays(2))->orderBy('collected_at')->get();
            $L[] = 'Perubahan konfigurasi 2 hari sebelum incident: ' . ($cfg->isEmpty() ? 'tidak ada tercatat'
                : $cfg->map(fn ($v) => "{$this->t($v->collected_at)} +{$v->lines_added}/-{$v->lines_removed} baris" . ($v->change ? " ({$v->change->number})" : ($v->unauthorized ? ' (tanpa change request)' : '')))->implode('; '));
            $mw = MaintenanceWindow::whereHas('devices', fn ($q) => $q->where('devices.device_id', $d->device_id))
                ->where('starts_at', '<=', $incident->started_at->copy()->addHours(6))->where('ends_at', '>=', $incident->started_at->copy()->subDays(1))->get();
            if ($mw->isNotEmpty()) {
                $L[] = 'Maintenance terkait: ' . $mw->map(fn ($w) => "{$w->title} ({$w->type}, {$this->t($w->starts_at)}–{$this->t($w->ends_at)}, {$w->status})")->implode('; ');
            }
            foreach (QualityTarget::where('device_id', $d->device_id)->get() as $q) {
                $L[] = "Quality Probe {$q->name}: status {$q->last_status}";
            }
        }

        $L[] = '';
        $L[] = 'PROBLEM TERKAIT:';
        foreach ($incident->problems as $p) {
            $L[] = "- [{$p->severity}] {$p->title} — mulai {$this->t($p->started_at)}" . ($p->resolved_at ? ", selesai {$this->t($p->resolved_at)}" : ', masih aktif')
                . ($p->suppressed_reason ? ", ditekan: {$p->suppressed_reason}" : '') . '; sumber: ' . $p->sources->map(fn ($s) => "{$s->source} ({$s->label})")->implode(', ');
        }
        if ($incident->problems->isEmpty()) {
            $L[] = '- tidak ada';
        }

        $since = $incident->started_at->copy()->subHours(2);
        $other = Problem::where('started_at', '>=', $since)->where('started_at', '<=', $incident->started_at->copy()->addHours(2))
            ->whereNotIn('id', $incident->problems->pluck('id'))->where(fn ($q) => $q->where('site_id', $incident->site_id)->orWhereNull('site_id'))->limit(15)->get();
        if ($other->isNotEmpty()) {
            $L[] = '';
            $L[] = 'PROBLEM LAIN DI SEKITAR WAKTU YANG SAMA (±2 jam, site sama):';
            foreach ($other as $p) {
                $L[] = "- [{$p->severity}] {$p->title} — mulai {$this->t($p->started_at)}";
            }
        }
        $anom = Anomaly::where('detected_at', '>=', $since)->where('detected_at', '<=', $incident->started_at->copy()->addHours(2))->limit(10)->get();
        if ($anom->isNotEmpty()) {
            $L[] = '';
            $L[] = 'ANOMALI STATISTIK DI SEKITAR WAKTU INCIDENT:';
            foreach ($anom as $a) {
                $L[] = "- {$this->t($a->detected_at)} {$a->label}";
            }
        }

        $L[] = '';
        $L[] = 'CATATAN & RIWAYAT INCIDENT:';
        foreach ($incident->notes->sortBy('id')->take(-40) as $n) {
            if ($n->type === 'ai') {
                continue;
            }
            $L[] = "- {$this->t($n->created_at)} [{$n->type}] " . trim(($n->old_value ? "{$n->old_value} → {$n->new_value} " : '') . $this->clip((string) $n->body, 400));
        }
        if ($incident->root_cause) {
            $L[] = 'Root cause yang sudah ditulis engineer: ' . $this->clip($incident->root_cause, 800);
        }

        return AiAssistant::redact(implode("\n", $L));
    }

    public function shift(int $hours): string
    {
        $from = now()->subHours($hours);
        $L = ["PERIODE: {$this->t($from)} – {$this->t(now())} ({$hours} jam)"];

        $opened = Problem::where('started_at', '>=', $from)->orderBy('started_at')->get();
        $L[] = '';
        $L[] = "PROBLEM BARU ({$opened->count()}):";
        foreach ($opened->take(60) as $p) {
            $L[] = "- [{$p->severity}] {$p->title} — {$this->t($p->started_at)}" . ($p->resolved_at ? " s/d {$this->t($p->resolved_at)}" : ' (masih aktif)')
                . ($p->suppressed_reason ? " (ditekan: {$p->suppressed_reason})" : '') . ($p->acknowledged_at ? ' (di-ack)' : '');
        }
        $stillOpen = Problem::open()->whereNull('suppressed_reason')->where('started_at', '<', $from)->get();
        if ($stillOpen->isNotEmpty()) {
            $L[] = "PROBLEM LAMA YANG MASIH AKTIF ({$stillOpen->count()}): " . $stillOpen->take(20)->map(fn ($p) => "[{$p->severity}] {$p->title}")->implode('; ');
        }

        $inc = Incident::where(fn ($q) => $q->where('started_at', '>=', $from)->orWhere('updated_at', '>=', $from)->orWhereIn('status', Incident::ACTIVE))->orderBy('started_at')->get();
        $L[] = '';
        $L[] = "INCIDENT ({$inc->count()}):";
        foreach ($inc as $i) {
            $L[] = "- {$i->number} [{$i->severity}] {$i->title} — status {$i->status}, mulai {$this->t($i->started_at)}" . ($i->root_cause ? '; root cause: ' . $this->clip($i->root_cause, 200) : '');
        }

        $wo = WorkOrder::where(fn ($q) => $q->where('updated_at', '>=', $from)->orWhereIn('status', WorkOrder::ACTIVE))->get();
        $L[] = '';
        $L[] = "WORK ORDER ({$wo->count()}):";
        foreach ($wo as $w) {
            $p = $w->checklistProgress();
            $L[] = "- {$w->number} [{$w->priority}] {$w->title} — {$w->status}, checklist {$p['done']}/{$p['total']}" . ($w->isOverdue() ? ', LEWAT TENGGAT' : '') . ($w->result_notes ? '; hasil: ' . $this->clip($w->result_notes, 200) : '');
        }

        $chg = Change::where(fn ($q) => $q->where('updated_at', '>=', $from)->orWhereIn('status', ['submitted', 'approved', 'in_progress']))->get();
        if ($chg->isNotEmpty()) {
            $L[] = '';
            $L[] = "CHANGE REQUEST ({$chg->count()}):";
            foreach ($chg as $c) {
                $L[] = "- {$c->number} {$c->title} — {$c->status}" . ($c->planned_start ? ", jadwal {$this->t($c->planned_start)}" : '');
            }
        }

        $mw = MaintenanceWindow::where('starts_at', '<=', now()->addHours(12))->where('ends_at', '>=', $from)->where('status', '!=', 'cancelled')->get();
        if ($mw->isNotEmpty()) {
            $L[] = '';
            $L[] = 'MAINTENANCE (berlangsung / 12 jam ke depan):';
            foreach ($mw as $w) {
                $L[] = "- {$w->title} ({$w->type}) {$this->t($w->starts_at)}–{$this->t($w->ends_at)}, {$w->effectiveStatus()}";
            }
        }

        $anom = Anomaly::where('last_seen_at', '>=', $from)->get();
        if ($anom->isNotEmpty()) {
            $L[] = '';
            $L[] = "ANOMALI STATISTIK ({$anom->count()}):";
            foreach ($anom as $a) {
                $L[] = "- {$a->label} ({$a->status})";
            }
        }
        $unauth = ConfigVersion::with('device')->where('unauthorized', true)->whereNull('acknowledged_at')->get();
        if ($unauth->isNotEmpty()) {
            $L[] = 'KONFIGURASI BERUBAH TANPA CHANGE REQUEST (belum diverifikasi): ' . $unauth->map(fn ($v) => $v->device?->displayName())->unique()->implode(', ');
        }

        return AiAssistant::redact(implode("\n", $L));
    }

    private function t($v): string
    {
        if ($v === null) {
            return '-';
        }
        $c = is_numeric($v) ? \Carbon\Carbon::createFromTimestamp((int) $v, config('app.timezone')) : \Carbon\Carbon::parse($v)->setTimezone(config('app.timezone'));

        return $c->format('d M H:i');
    }

    private function clip(string $s, int $n): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));

        return mb_strlen($s) > $n ? mb_substr($s, 0, $n) . '…' : $s;
    }
}
