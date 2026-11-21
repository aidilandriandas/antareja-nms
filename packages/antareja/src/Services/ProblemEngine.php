<?php

namespace Antareja\Services;

use Antareja\Models\DeviceDetail;
use Antareja\Models\IpAddress;
use Antareja\Models\Problem;
use Antareja\Models\ProblemSource;
use Antareja\Models\QualityTarget;
use Antareja\Support\Severity;
use App\Models\Device;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LibreNMS\Enum\AlertState;

/**
 * Problem engine ala Zabbix, berjalan setiap menit (antareja:problems).
 *
 * 1. Kumpulkan observasi dari semua sumber (status polling, port uplink terverifikasi, Quality Probe, IPAM, alert rule).
 * 2. Gabungkan observasi dengan correlation_key yang sama menjadi SATU problem (dedup lintas sumber);
 *    setiap observasi disimpan sebagai ProblemSource dengan ID aslinya.
 * 3. Problem pulih bila seluruh sumbernya pulih.
 * 4. Korelasi parent/child: problem perangkat yang induknya (mapping terverifikasi / dependensi poller) sedang down
 *    ditautkan ke problem induk dan ditekan; begitu juga port down pada perangkat yang sedang down.
 * 5. Problem pada perangkat dalam jendela maintenance ditekan.
 * Topologi tidak pernah ditebak: hanya relasi yang tercatat yang dipakai.
 */
class ProblemEngine
{
    /** Sumber alert rule yang dianggap "perangkat tidak terjangkau" sehingga digabung dengan device down. */
    private const DOWN_RULE_PATTERN = '/(device|devices)\s*(up\/)?down|unreachable|no icmp response/i';

    /**
     * @return array{opened:int, resolved:int, observations:int}
     */
    public function sync(): array
    {
        $now = now();
        $observations = $this->observe();
        $stats = ['opened' => 0, 'resolved' => 0, 'observations' => count($observations)];

        $activeSources = ProblemSource::with('problem')->where('active', true)->get()
            ->keyBy(fn (ProblemSource $s) => $s->source . '|' . $s->source_ref);
        $seen = [];

        foreach ($observations as $o) {
            $sourceKey = $o['source'] . '|' . $o['ref'];
            $seen[$sourceKey] = true;
            $existing = $activeSources[$sourceKey] ?? null;

            if ($existing && $existing->problem?->isOpen()) {
                $existing->forceFill(['last_seen_at' => $now, 'label' => mb_substr($o['label'], 0, 255)])->save();
                $this->raiseSeverity($existing->problem, $o['severity'], $o['title']);
                continue;
            }

            $problem = Problem::open()->where('correlation_key', $o['key'])->first();
            if ($problem) {
                $problem->log('source_added', 'Juga dilaporkan oleh ' . ProblemSource::LABELS[$o['source']] . ': ' . $o['label']);
                $this->raiseSeverity($problem, $o['severity'], $o['title']);
            } else {
                $problem = Problem::create([
                    'correlation_key' => $o['key'], 'class' => $o['class'], 'severity' => $o['severity'],
                    'title' => mb_substr($o['title'], 0, 255), 'detail' => $o['detail'],
                    'device_id' => $o['device_id'], 'port_id' => $o['port_id'], 'site_id' => $o['site_id'],
                    'status' => Problem::OPEN, 'started_at' => $o['since'] ?? $now,
                ]);
                $problem->log('opened', $o['title'] . ' (' . ProblemSource::LABELS[$o['source']] . ')');
                $stats['opened']++;
            }

            $problem->sources()->create([
                'source' => $o['source'], 'source_ref' => (string) $o['ref'], 'label' => mb_substr($o['label'], 0, 255),
                'active' => true, 'first_seen_at' => $now, 'last_seen_at' => $now,
            ]);
        }

        // Sumber yang tidak lagi teramati → pulih; problem tanpa sumber aktif → resolved
        foreach ($activeSources as $key => $source) {
            if (isset($seen[$key])) {
                continue;
            }
            $source->forceFill(['active' => false, 'cleared_at' => $now])->save();
            $problem = $source->problem;
            if (! $problem || ! $problem->isOpen()) {
                continue;
            }
            if ($problem->sources()->where('active', true)->exists()) {
                $problem->log('source_cleared', ProblemSource::LABELS[$source->source] . ' pulih: ' . $source->label);
                continue;
            }
            $problem->forceFill(['status' => Problem::RESOLVED, 'resolved_at' => $now])->save();
            $problem->log('resolved', 'Semua sumber pulih setelah ' . $problem->duration());
            $problem->incident?->note('link', "Problem \"{$problem->title}\" selesai ({$problem->duration()}).");
            $stats['resolved']++;
        }

        $this->correlate();
        $this->acknowledgeFromPoller();

        return $stats;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function observe(): array
    {
        $obs = [];
        $details = DeviceDetail::pluck('verification_status', 'device_id');
        $sites = DeviceDetail::pluck('site_id', 'device_id');
        $skip = fn ($deviceId) => in_array($details[$deviceId] ?? null, [DeviceDetail::REJECTED, DeviceDetail::RETIRED], true);
        $parents = DB::table('device_relationships')->pluck('parent_device_id')->unique()->flip();

        // 1. Perangkat down (status polling). Device baru yang belum pernah dipoll diabaikan.
        //    Waktu mulai diambil dari outage poller yang masih terbuka, agar durasi problem akurat.
        $outageStart = DB::table('device_outages')->whereNull('up_again')->selectRaw('device_id, MAX(going_down) AS t')
            ->groupBy('device_id')->pluck('t', 'device_id');
        Device::where('status', 0)->where('disabled', 0)->where('ignore', 0)->where('status_reason', '!=', '')
            ->get()->each(function (Device $d) use (&$obs, $skip, $sites, $parents, $outageStart): void {
                if ($skip($d->device_id)) {
                    return;
                }
                $isParent = isset($parents[$d->device_id]);
                $obs[] = $this->o('device', $d->device_id, "device:{$d->device_id}:down", 'device_down', $isParent ? 'disaster' : 'high',
                    "{$d->displayName()} tidak merespons", $d->device_id, null, $sites[$d->device_id] ?? null,
                    "status {$d->status_reason}" . ($isParent ? '; perangkat ini induk dari perangkat lain' : ''),
                    "Perangkat {$d->displayName()} down ({$d->status_reason})",
                    isset($outageStart[$d->device_id]) ? \Carbon\Carbon::createFromTimestamp((int) $outageStart[$d->device_id], config('app.timezone')) : null);
            });

        // 2. Port down — hanya port yang tercatat di mapping terverifikasi (uplink, AP, CCTV, PTP …), bukan semua port.
        $mappedPorts = DB::table('ant_device_links')->where('status', 'verified')
            ->selectRaw('a_port_id AS port_id, b_device_id AS other')->whereNotNull('a_port_id')
            ->unionAll(DB::table('ant_device_links')->where('status', 'verified')->selectRaw('b_port_id, a_device_id')->whereNotNull('b_port_id'))
            ->get()->groupBy('port_id');
        if ($mappedPorts->isNotEmpty()) {
            \App\Models\Port::with('device')->whereIn('port_id', $mappedPorts->keys())
                ->where('deleted', 0)->where('ifAdminStatus', 'up')->where('ifOperStatus', '!=', 'up')
                ->get()->each(function ($port) use (&$obs, $skip, $sites, $mappedPorts): void {
                    $dev = $port->device;
                    if (! $dev || $dev->disabled || $skip($dev->device_id)) {
                        return;
                    }
                    $other = Device::find($mappedPorts[$port->port_id]->first()->other);
                    $label = $port->getShortLabel();
                    $obs[] = $this->o('port', $port->port_id, "port:{$port->port_id}:down", 'port_down', 'average',
                        "Port {$label} di {$dev->displayName()} down" . ($other ? " (ke {$other->displayName()})" : ''),
                        $dev->device_id, $port->port_id, $sites[$dev->device_id] ?? null,
                        "ifOperStatus={$port->ifOperStatus}", "{$dev->displayName()} {$label}: {$port->ifOperStatus}");
                });
        }

        // 3. Quality Probe. Link putus pada target perangkat = perangkat tak terjangkau → digabung dengan device down.
        QualityTarget::with('device')->where('enabled', true)->whereIn('last_status', ['warning', 'critical', 'down'])
            ->where('last_checked_at', '>=', now()->subMinutes(QualityTarget::STALE_MINUTES))->get()
            ->each(function (QualityTarget $t) use (&$obs, $sites): void {
                $sample = $t->latestSample;
                $metric = $sample && $sample->received > 0
                    ? sprintf('median %.1f ms, loss %.0f%%, jitter %.1f ms', $sample->median_ms, $sample->loss_pct, $sample->jitter_ms)
                    : 'loss 100%';
                $key = ($t->last_status === 'down' && $t->device_id) ? "device:{$t->device_id}:down" : "quality:{$t->id}";
                $severity = ['warning' => 'warning', 'critical' => 'average', 'down' => 'high'][$t->last_status];
                $title = $t->last_status === 'down' ? "Link putus: {$t->name}" : 'Kualitas link ' . strtolower(QualityTarget::STATUS[$t->last_status]['label']) . ": {$t->name}";
                $obs[] = $this->o('quality', $t->id, $key, $t->last_status === 'down' && $t->device_id ? 'device_down' : 'quality', $severity,
                    $title, $t->device_id, null, $t->site_id ?? ($t->device_id ? ($sites[$t->device_id] ?? null) : null), $metric, "{$t->name} ({$t->host}): {$metric}");
            });

        // 4. Konflik IP (IPAM)
        IpAddress::where('conflict', true)->get()->each(function (IpAddress $ip) use (&$obs, $sites): void {
            $obs[] = $this->o('ipam', $ip->address_text, "ip:{$ip->address_text}", 'ip_conflict', 'warning',
                "Konflik IP {$ip->address_text}", $ip->device_id, null, $ip->device_id ? ($sites[$ip->device_id] ?? null) : null,
                $ip->conflict_detail, (string) $ip->conflict_detail);
        });

        // 5. Alert rule poller (bila ada aturan alert yang aktif)
        DB::table('alerts')->join('alert_rules', 'alert_rules.id', '=', 'alerts.rule_id')
            ->whereIn('alerts.state', [AlertState::ACTIVE, AlertState::ACKNOWLEDGED, AlertState::WORSE, AlertState::BETTER, AlertState::CHANGED])
            ->where('alert_rules.disabled', 0)
            ->select('alerts.id', 'alerts.device_id', 'alerts.timestamp', 'alert_rules.name', 'alert_rules.severity')
            ->get()->each(function ($a) use (&$obs, $skip, $sites): void {
                if ($skip($a->device_id)) {
                    return;
                }
                $dev = Device::find($a->device_id);
                $isDown = preg_match(self::DOWN_RULE_PATTERN, $a->name) === 1;
                $key = $isDown ? "device:{$a->device_id}:down" : "alert:{$a->id}";
                $severity = $a->severity === 'critical' ? 'high' : ($a->severity === 'warning' ? 'warning' : 'information');
                $obs[] = $this->o('librenms_alert', $a->id, $key, $isDown ? 'device_down' : 'alert', $severity,
                    ($dev?->displayName() ?? "Device #{$a->device_id}") . ": {$a->name}", $a->device_id, null, $sites[$a->device_id] ?? null,
                    "Alert rule \"{$a->name}\" ({$a->severity})", "Rule \"{$a->name}\" (alert #{$a->id})", $a->timestamp ? \Carbon\Carbon::parse($a->timestamp) : null);
            });

        // 6. Trigger expression ANTAREJA yang sedang problem
        \Antareja\Models\Trigger::where('enabled', true)->where('state', 'problem')->get()
            ->each(function (\Antareja\Models\Trigger $t) use (&$obs, $skip, $sites): void {
                if ($t->device_id && $skip($t->device_id)) {
                    return;
                }
                $unit = TriggerEngine::sensorUnit($t);
                $value = \Antareja\Models\Trigger::fmt($t->last_value, $unit);
                $item = TriggerEngine::itemLabel($t);
                $obs[] = $this->o('trigger', $t->id, "trigger:{$t->id}", 'trigger', $t->severity,
                    "{$t->name} ({$value})", $t->device_id, $t->source === 'port' ? (int) $t->ref_id : null,
                    $t->device_id ? ($sites[$t->device_id] ?? null) : null,
                    $t->expression($item) . " — nilai {$value}", "{$t->name}: {$value}", $t->state_changed_at);
            });

        // 7. Perubahan konfigurasi tanpa change request (dari backup konfigurasi), sampai di-acknowledge
        \Antareja\Models\ConfigVersion::with('device')->where('unauthorized', true)->whereNull('acknowledged_at')
            ->orderBy('collected_at')->get()->groupBy('device_id')
            ->each(function ($versions, $deviceId) use (&$obs, $skip, $sites): void {
                if ($skip($deviceId)) {
                    return;
                }
                $v = $versions->last();
                $name = $v->device?->displayName() ?? "Device #{$deviceId}";
                $obs[] = $this->o('config', $deviceId, "config:{$deviceId}", 'config_change', 'average',
                    "Konfigurasi {$name} berubah tanpa change request", (int) $deviceId, null, $sites[$deviceId] ?? null,
                    $versions->count() . " versi belum diverifikasi; terakhir +{$v->lines_added}/-{$v->lines_removed} baris",
                    "{$name}: versi {$v->collected_at->format('d M H:i')}", $versions->first()->collected_at);
            });

        // 8. Anomali statistik aktif (informasi; flapping = warning)
        \Antareja\Models\Anomaly::where('status', 'open')->get()->each(function ($a) use (&$obs, $skip, $sites): void {
            if ($a->device_id && $skip($a->device_id)) {
                return;
            }
            $obs[] = $this->o('anomaly', $a->id, "anomaly:{$a->kind}:{$a->ref}", 'anomaly', $a->kind === 'flapping' ? 'warning' : 'information',
                (\Antareja\Models\Anomaly::KINDS[$a->kind] ?? 'Anomali') . ': ' . $a->label, $a->device_id, null,
                $a->device_id ? ($sites[$a->device_id] ?? null) : null, $a->score !== null ? 'skor ' . $a->score : null, $a->label, $a->detected_at);
        });

        // 9. Kesehatan gambar CCTV (kamera online tetapi gambarnya tidak berguna)
        \Antareja\Models\CctvStream::with('device')->where('enabled', true)->where('health_check', true)
            ->whereNotNull('health')->where('health', '!=', 'ok')->get()
            ->each(function ($s) use (&$obs, $skip, $sites): void {
                if (! $s->device || $skip($s->device_id) || ! $s->device->status) {
                    return; // kamera down sudah ditangani sumber "perangkat down"
                }
                $meta = $s->healthMeta();
                $obs[] = $this->o('cctv', $s->id, "cctv:{$s->device_id}", 'cctv_image', in_array($s->health, ['blank', 'frozen', 'error'], true) ? 'average' : 'warning',
                    "{$s->device->displayName()}: {$meta['label']}", $s->device_id, null, $sites[$s->device_id] ?? null,
                    $s->health_detail, $meta['label'], $s->health_changed_at);
            });

        return $obs;
    }

    private function o(string $source, string|int $ref, string $key, string $class, string $severity, string $title,
        ?int $deviceId, ?int $portId, ?int $siteId, ?string $detail, string $label, $since = null): array
    {
        return compact('source', 'ref', 'key', 'class', 'severity', 'title', 'detail', 'label', 'since') + [
            'device_id' => $deviceId ?: null, 'port_id' => $portId, 'site_id' => $siteId,
        ];
    }

    private function raiseSeverity(Problem $problem, string $severity, string $title): void
    {
        if (Severity::problemRank($severity) > Severity::problemRank($problem->severity)) {
            $old = $problem->severity;
            $problem->forceFill(['severity' => $severity, 'title' => mb_substr($title, 0, 255)])->save();
            $problem->log('severity', "Severity naik {$old} → {$severity}");
        }
    }

    /**
     * Korelasi parent/child & maintenance untuk semua problem terbuka.
     */
    private function correlate(): void
    {
        $open = Problem::open()->get();
        $downByDevice = $open->where('class', 'device_down')->whereNotNull('device_id')->keyBy('device_id');
        $parentsOf = DB::table('device_relationships')->get()->groupBy('child_device_id')
            ->map(fn (Collection $rows) => $rows->pluck('parent_device_id')->all());
        $maintenance = Device::whereIn('device_id', $open->pluck('device_id')->filter()->unique())->get()
            ->filter(fn (Device $d) => $d->isUnderMaintenance())->pluck('device_id')->flip();

        foreach ($open as $problem) {
            $parent = null;
            if ($problem->device_id) {
                // port down / quality di perangkat yang sendirinya down → anak dari problem perangkat itu
                if ($problem->class !== 'device_down' && isset($downByDevice[$problem->device_id])) {
                    $parent = $downByDevice[$problem->device_id];
                }
                // perangkat yang induknya (mapping terverifikasi / dependensi) down
                foreach ($parentsOf[$problem->device_id] ?? [] as $parentDeviceId) {
                    if (! $parent && isset($downByDevice[$parentDeviceId]) && $downByDevice[$parentDeviceId]->id !== $problem->id) {
                        $parent = $downByDevice[$parentDeviceId];
                    }
                }
            }

            $reason = isset($maintenance[$problem->device_id]) ? 'maintenance' : ($parent ? 'parent' : null);
            $parentId = $parent?->id;

            if ($reason !== $problem->suppressed_reason || $parentId !== $problem->parent_problem_id) {
                $problem->forceFill(['suppressed_reason' => $reason, 'parent_problem_id' => $parentId])->save();
                if ($reason === 'parent') {
                    $problem->log('suppressed', "Ditautkan ke problem induk: {$parent->title}");
                    // anak mengikuti incident induk
                    if ($parent->incident_id && ! $problem->incident_id) {
                        $problem->forceFill(['incident_id' => $parent->incident_id])->save();
                        $parent->incident?->note('link', "Problem terdampak ditautkan: {$problem->title}");
                    }
                } elseif ($reason === 'maintenance') {
                    $problem->log('suppressed', 'Perangkat dalam jendela maintenance');
                } else {
                    $problem->log('unsuppressed', 'Tidak lagi ditekan');
                }
            }
        }
    }

    /** Ack yang dilakukan langsung di halaman alert poller ikut tercatat di problem. */
    private function acknowledgeFromPoller(): void
    {
        $acked = DB::table('alerts')->where('state', AlertState::ACKNOWLEDGED)->pluck('id')->map(fn ($id) => (string) $id);
        if ($acked->isEmpty()) {
            return;
        }
        ProblemSource::with('problem')->where('source', 'librenms_alert')->where('active', true)->whereIn('source_ref', $acked)->get()
            ->each(function (ProblemSource $s): void {
                $p = $s->problem;
                if ($p && $p->isOpen() && ! $p->acknowledged_at) {
                    $p->forceFill(['acknowledged_at' => now(), 'ack_message' => 'Acknowledge dari halaman alert poller'])->save();
                    $p->log('acknowledged', 'Acknowledge dari halaman alert poller');
                }
            });
    }

    /**
     * Acknowledge problem; diteruskan ke alert poller yang menjadi sumbernya.
     */
    public function acknowledge(Problem $problem, int $userId, string $message): void
    {
        $problem->forceFill(['acknowledged_at' => now(), 'acknowledged_by' => $userId, 'ack_message' => mb_substr($message, 0, 255)])->save();
        $problem->log('acknowledged', $message !== '' ? "Acknowledge: {$message}" : 'Acknowledge', $userId);

        $alertIds = $problem->sources()->where('source', 'librenms_alert')->where('active', true)->pluck('source_ref');
        foreach ($alertIds as $id) {
            $alert = \App\Models\Alert::find($id);
            if ($alert && $alert->state !== AlertState::ACKNOWLEDGED) {
                $alert->state = AlertState::ACKNOWLEDGED;
                $alert->note = trim(($alert->note ? $alert->note . PHP_EOL : '') . date('Y-m-d H:i') . " ANTAREJA: {$message}");
                $alert->save();
            }
        }

        app(AuditLogger::class)->log('updated', 'problem', "Problem \"{$problem->title}\" di-acknowledge: {$message}");
    }
}
