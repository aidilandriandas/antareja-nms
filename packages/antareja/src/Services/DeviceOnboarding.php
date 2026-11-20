<?php

namespace Antareja\Services;

use Antareja\Models\DeviceDetail;
use App\Actions\Device\ValidateDeviceAndCreate;
use App\Models\Device;
use LibreNMS\Exceptions\HostExistsException;
use LibreNMS\Exceptions\HostUnreachableException;

/**
 * Menambah perangkat ke monitoring lewat jalur resmi LibreNMS (ValidateDeviceAndCreate: cek ping & SNMP, cegah duplikat),
 * lalu langsung mengisi data ANTAREJA: nama tampilan, kategori (= grup), dan site — supaya perangkat mudah dicari
 * dan tidak muncul sebagai alamat IP saja.
 */
class DeviceOnboarding
{
    public function __construct(
        private readonly InventorySync $inventory,
        private readonly DeviceGroupSync $groups,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param  array{hostname: string, name: ?string, category: ?string, site_id: ?int, area_id?: ?int, mode: string,
     *               community?: ?string, v3_user?: ?string, v3_auth_pass?: ?string, v3_auth_algo?: ?string,
     *               v3_priv_pass?: ?string, v3_priv_algo?: ?string, force?: bool}  $data
     * @return array{ok: bool, device: ?Device, message: string}
     */
    public function add(array $data): array
    {
        $hostname = trim($data['hostname']);
        $name = trim((string) ($data['name'] ?? ''));
        $attrs = ['hostname' => $hostname, 'display_template' => $name !== '' ? $name : null];

        switch ($data['mode']) {
            case 'ping':
                $attrs += ['snmp_disable' => 1, 'os' => 'ping', 'sysName' => $name !== '' ? $name : $hostname];
                break;
            case 'v3':
                $auth = (string) ($data['v3_auth_pass'] ?? '');
                $priv = (string) ($data['v3_priv_pass'] ?? '');
                $attrs += [
                    'snmpver' => 'v3',
                    'authlevel' => ($auth !== '' ? 'auth' : 'noAuth') . (($auth !== '' && $priv !== '') ? 'Priv' : 'NoPriv'),
                    'authname' => $data['v3_user'] ?? '',
                    'authpass' => $auth,
                    'authalgo' => $data['v3_auth_algo'] ?? 'SHA',
                    'cryptopass' => $priv,
                    'cryptoalgo' => $data['v3_priv_algo'] ?? 'AES',
                ];
                break;
            default: // v2c; community kosong = pakai daftar community di Pengaturan Sistem
                $attrs['snmpver'] = 'v2c';
                if (! empty($data['community'])) {
                    $attrs['community'] = $data['community'];
                }
        }

        $device = new Device($attrs);
        try {
            $created = (new ValidateDeviceAndCreate($device, (bool) ($data['force'] ?? false), $data['mode'] !== 'ping'))->execute();
        } catch (HostUnreachableException $e) {
            $reasons = $e->getReasons();

            return ['ok' => false, 'device' => null, 'message' => 'Tidak bisa dihubungi: ' . $e->getMessage()
                . ($reasons ? ' (' . implode('; ', array_slice($reasons, 0, 2)) . ')' : '')
                . '. Cek IP, firewall, atau pengaturan SNMP — atau centang "tetap tambahkan".'];
        } catch (HostExistsException $e) {
            return ['ok' => false, 'device' => null, 'message' => 'Sudah terdaftar: ' . $e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'device' => null, 'message' => 'Gagal: ' . $e->getMessage()];
        }
        if (! $created) {
            return ['ok' => false, 'device' => null, 'message' => 'Gagal menyimpan perangkat.'];
        }

        // Detail ANTAREJA dibuat otomatis oleh hook Device::created; lengkapi dengan isian engineer
        $detail = $this->inventory->ensure($device);
        $fill = array_filter([
            'category' => $data['category'] ?? null,
            'site_id' => $data['site_id'] ?? null,
            'area_id' => $data['area_id'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        if ($fill) {
            $detail->forceFill($fill)->saveQuietly();
        }
        $this->groups->syncDevice($device->device_id, $detail->category);
        $this->audit->log('created', $detail, sprintf('Perangkat %s (%s) ditambahkan lewat form ANTAREJA', $device->displayName(), $hostname));

        return ['ok' => true, 'device' => $device, 'message' => ($data['mode'] !== 'ping' && $device->snmp_disable)
            ? 'Ditambahkan — SNMP tidak menjawab, dipantau dengan ping saja' : 'Ditambahkan'];
    }
}
