<?php

namespace Antareja\Services;

use Antareja\Support\Catalog;
use App\Models\DeviceGroup;
use Illuminate\Support\Facades\DB;

/**
 * Grup perangkat otomatis: satu Device Group LibreNMS (tipe static) per kategori ANTAREJA — "CCTV" berisi semua kamera,
 * "Access Point" semua AP, dst. Karena berupa Device Group LibreNMS biasa, grup ini juga bisa dipakai untuk filter daftar
 * perangkat, cakupan alert rule, dan hak akses per grup. Grup dikenali dari kolom desc ("ANTAREJA:auto:<kategori>"),
 * jadi grup buatan engineer sendiri tidak pernah disentuh.
 */
class DeviceGroupSync
{
    public const MARK = 'ANTAREJA:auto:';

    /** @return array<string, DeviceGroup> kategori => grup */
    public function groups(bool $create = false): array
    {
        $existing = DeviceGroup::where('desc', 'like', self::MARK . '%')->get()
            ->keyBy(fn (DeviceGroup $g) => substr($g->desc, strlen(self::MARK)));
        if (! $create) {
            return $existing->all();
        }
        foreach (Catalog::GROUP_NAMES as $category => $name) {
            if (! $existing->has($category)) {
                $group = new DeviceGroup(['name' => $this->uniqueName($name), 'desc' => self::MARK . $category, 'type' => 'static']);
                $group->save();
                $existing[$category] = $group;
            }
        }

        return $existing->all();
    }

    /** Pindahkan satu perangkat ke grup kategorinya (dan keluarkan dari grup otomatis lain). */
    public function syncDevice(int $deviceId, ?string $category): void
    {
        $groups = $this->groups(create: $category !== null);
        $managed = collect($groups)->map->id->values();
        DB::table('device_group_device')->where('device_id', $deviceId)->whereIn('device_group_id', $managed)
            ->when($category && isset($groups[$category]), fn ($q) => $q->where('device_group_id', '!=', $groups[$category]->id))
            ->delete();
        if ($category && isset($groups[$category])) {
            DB::table('device_group_device')->insertOrIgnore(['device_group_id' => $groups[$category]->id, 'device_id' => $deviceId]);
        }
    }

    /** Samakan seluruh isi grup otomatis dengan kategori di inventaris. @return array{groups: int, members: int} */
    public function syncAll(): array
    {
        $groups = $this->groups(create: true);
        $members = 0;
        foreach ($groups as $category => $group) {
            $ids = DB::table('ant_device_details')->where('category', $category)
                ->whereIn('device_id', DB::table('devices')->select('device_id'))->pluck('device_id');
            $group->devices()->sync($ids);
            $members += $ids->count();
        }

        return ['groups' => count($groups), 'members' => $members];
    }

    private function uniqueName(string $name): string
    {
        $candidate = $name;
        $i = 2;
        while (DeviceGroup::where('name', $candidate)->exists()) {
            $candidate = $name . ' (' . $i++ . ')';
        }

        return $candidate;
    }
}
