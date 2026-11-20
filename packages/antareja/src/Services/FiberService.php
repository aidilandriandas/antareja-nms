<?php

namespace Antareja\Services;

use Antareja\Models\FiberCable;
use Antareja\Models\FiberCore;
use Antareja\Models\FiberJoint;
use Antareja\Models\FiberOdf;
use Antareja\Models\FiberOdfPort;
use Antareja\Models\FiberSplice;
use Illuminate\Support\Facades\DB;

/**
 * Manajemen fiber: pembuatan kabel + core, penelusuran jalur end-to-end, lokasi gangguan dari jarak OTDR,
 * dan estimasi loss budget. Jalur hanya disusun dari data yang dicatat (ODF port ↔ core ↔ splice), tidak ditebak.
 */
class FiberService
{
    public const SPLICE_LOSS_DB = 0.1;

    public const CONNECTOR_LOSS_DB = 0.5;

    public function createCable(array $data): FiberCable
    {
        return DB::transaction(function () use ($data) {
            $cable = FiberCable::create($data);
            $now = now();
            $rows = [];
            for ($i = 1; $i <= $cable->core_count; $i++) {
                $rows[] = ['cable_id' => $cable->id, 'core_no' => $i, 'status' => 'free', 'created_at' => $now, 'updated_at' => $now];
            }
            FiberCore::insert($rows);

            return $cable;
        });
    }

    /** Buat port ODF 1..N yang belum ada. */
    public function ensurePorts(FiberOdf $odf): void
    {
        $have = $odf->ports()->pluck('port_no')->flip();
        for ($i = 1; $i <= $odf->port_count; $i++) {
            if (! isset($have[$i])) {
                FiberOdfPort::create(['odf_id' => $odf->id, 'port_no' => $i]);
            }
        }
    }

    /**
     * Telusuri jalur sebuah core dari ujung ODF (bila ada) melewati splice di joint sampai ujung lain.
     *
     * @return array{hops: array<int, array>, complete: bool, length_m: float, splices: int, connectors: int, issue: ?string, start: ?FiberOdfPort, end: ?FiberOdfPort}
     */
    public function trace(FiberCore $core): array
    {
        $core->loadMissing('cable');
        // Tentukan titik awal: ODF port yang memegang core ini di salah satu ujung kabel (utamakan sisi A)
        $startPort = FiberOdfPort::with(['odf', 'device', 'port'])->where('core_id', $core->id)->get()
            ->sortBy(fn ($p) => $core->cable->sideAt('odf', $p->odf_id) === 'a' ? 0 : 1)->first();
        $from = $startPort ? ['odf', (int) $startPort->odf_id] : [$core->cable->a_type, (int) $core->cable->a_id];

        $hops = [];
        $length = 0.0;
        $splices = 0;
        $connectors = $startPort ? 1 : 0;
        $issue = null;
        $end = null;
        $visited = [];
        $current = $core;

        if ($startPort) {
            $hops[] = ['kind' => 'odf', 'port' => $startPort, 'at' => 0.0];
        } else {
            $hops[] = ['kind' => 'point', 'label' => $core->cable->endpointLabel('a'), 'at' => 0.0];
        }

        for ($guard = 0; $guard < 50; $guard++) {
            if (isset($visited[$current->id])) {
                $issue = 'Jalur berputar (data splice melingkar).';
                break;
            }
            $visited[$current->id] = true;
            $cable = $current->cable;
            $side = $cable->sideAt($from[0], $from[1]) ?? 'a';
            $other = $side === 'a' ? [$cable->b_type, (int) $cable->b_id] : [$cable->a_type, (int) $cable->a_id];
            $segLen = (float) ($cable->length_m ?? 0);
            $hops[] = ['kind' => 'cable', 'cable' => $cable, 'core' => $current, 'length_m' => $segLen, 'at' => $length,
                'from' => $from[0] === 'odf' ? 'ODF ' . FiberOdf::find($from[1])?->name : 'Joint ' . FiberJoint::find($from[1])?->code,
                'to' => $cable->endpointLabel($side === 'a' ? 'b' : 'a')];
            $length += $segLen;
            if ($current->status === 'broken') {
                $issue = "Core {$current->label()} berstatus putus/rusak.";
            }

            if ($other[0] === 'odf') {
                $end = FiberOdfPort::with(['odf', 'device', 'port'])->where('odf_id', $other[1])->where('core_id', $current->id)->first();
                if ($end) {
                    $connectors++;
                    $hops[] = ['kind' => 'odf', 'port' => $end, 'at' => $length];
                } else {
                    $issue ??= "Core {$current->label()} belum terpasang ke port ODF di ujung " . FiberOdf::find($other[1])?->name . '.';
                    $hops[] = ['kind' => 'point', 'label' => 'ODF ' . FiberOdf::find($other[1])?->name . ' (port belum dicatat)', 'at' => $length];
                }
                break;
            }

            $joint = FiberJoint::find($other[1]);
            $splice = FiberSplice::where('joint_id', $other[1])->where(fn ($q) => $q->where('core_a_id', $current->id)->orWhere('core_b_id', $current->id))->first();
            if (! $splice) {
                $issue ??= "Core {$current->label()} tidak disambung di joint {$joint?->code}.";
                $hops[] = ['kind' => 'point', 'label' => 'Joint ' . ($joint?->label() ?? '?') . ' (tanpa splice)', 'at' => $length];
                break;
            }
            $splices++;
            $hops[] = ['kind' => 'joint', 'joint' => $joint, 'splice' => $splice, 'at' => $length];
            $current = FiberCore::with('cable')->find($splice->core_a_id === $current->id ? $splice->core_b_id : $splice->core_a_id);
            $from = ['joint', (int) $other[1]];
        }

        return ['hops' => $hops, 'complete' => $issue === null && $end !== null, 'length_m' => $length, 'splices' => $splices,
            'connectors' => $connectors, 'issue' => $issue, 'start' => $startPort, 'end' => $end];
    }

    /**
     * Lokasi gangguan dari jarak OTDR (diukur dari titik awal jalur).
     * $slackPct: kelebihan panjang serat terhadap panjang kabel (helix + gulungan cadangan), biasanya 1–3%.
     *
     * @return array{found: bool, message: string, hop: ?array, offset_m: ?float, remaining_m: ?float}
     */
    public function locate(array $trace, float $distance, float $slackPct = 0.0): array
    {
        $factor = 1 + max(0, $slackPct) / 100;
        $cum = 0.0;
        foreach ($trace['hops'] as $hop) {
            if ($hop['kind'] !== 'cable') {
                continue;
            }
            $len = $hop['length_m'] * $factor;
            if ($distance <= $cum + $len) {
                $offset = ($distance - $cum) / $factor;

                return ['found' => true, 'hop' => $hop, 'offset_m' => round($offset), 'remaining_m' => round($hop['length_m'] - $offset),
                    'message' => sprintf('Kabel %s, ±%s m dari %s (±%s m sebelum %s).', $hop['cable']->code,
                        number_format($offset, 0, ',', '.'), $hop['from'], number_format($hop['length_m'] - $offset, 0, ',', '.'), $hop['to'])];
            }
            $cum += $len;
        }

        return ['found' => false, 'hop' => null, 'offset_m' => null, 'remaining_m' => null,
            'message' => sprintf('Jarak %s m melebihi panjang jalur tercatat (%s m). Periksa panjang kabel atau faktor slack.',
                number_format($distance, 0, ',', '.'), number_format($cum, 0, ',', '.'))];
    }

    /** Estimasi loss end-to-end per panjang gelombang. @return array<int, float> */
    public function lossBudget(array $trace): array
    {
        $mode = null;
        $byMode = [];
        foreach ($trace['hops'] as $hop) {
            if ($hop['kind'] === 'cable') {
                $mode ??= $hop['cable']->mode;
                $byMode[] = $hop['length_m'];
            }
        }
        $mode ??= 'sm';
        $km = array_sum($byMode) / 1000;
        $spliceLoss = 0.0;
        foreach ($trace['hops'] as $hop) {
            if ($hop['kind'] === 'joint') {
                $spliceLoss += $hop['splice']->loss_db ?? self::SPLICE_LOSS_DB;
            }
        }
        $out = [];
        foreach (FiberCable::ATTENUATION[$mode] as $wl => $att) {
            $out[$wl] = round($km * $att + $spliceLoss + $trace['connectors'] * self::CONNECTOR_LOSS_DB, 2);
        }

        return $out;
    }

    /** Core yang punya ujung di titik (odf/joint) tertentu, untuk pilihan di form. */
    public function coresAt(string $type, int $id)
    {
        return FiberCore::with('cable')->whereHas('cable', fn ($q) => $q->where(fn ($w) => $w->where('a_type', $type)->where('a_id', $id))
            ->orWhere(fn ($w) => $w->where('b_type', $type)->where('b_id', $id)))->get()->sortBy(fn ($c) => [$c->cable->code, $c->core_no])->values();
    }
}
