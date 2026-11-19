<?php

namespace Antareja\Support;

/**
 * Definisi role awal ANTAREJA NMS di atas RBAC LibreNMS (spatie/laravel-permission).
 *
 * ICT Admin memakai role bawaan LibreNMS "admin" (super admin, lolos semua Gate),
 * sehingga tidak didefinisikan ulang di sini.
 */
class RoleBlueprints
{
    /**
     * Permission milik ANTAREJA. Grup "ant-*" ditambahkan ke resources/definitions/permissions.yaml
     * agar tampil di editor role LibreNMS.
     */
    public const PERMISSIONS = [
        'ant-dashboard' => ['ant-dashboard.technical', 'ant-dashboard.management'],
        'ant-site' => ['ant-site.view', 'ant-site.update'],
        'ant-inventory' => ['ant-inventory.view', 'ant-inventory.update', 'ant-inventory.delete'],
        'ant-discovery' => ['ant-discovery.view', 'ant-discovery.approve'],
        'ant-mapping' => ['ant-mapping.view', 'ant-mapping.update', 'ant-mapping.verify'],
        'ant-ipam' => ['ant-ipam.view', 'ant-ipam.update'],
        'ant-cctv' => ['ant-cctv.view', 'ant-cctv.update'],
        'ant-quality' => ['ant-quality.view', 'ant-quality.update'],
        'ant-incident' => ['ant-incident.view', 'ant-incident.create', 'ant-incident.update', 'ant-incident.close'],
        'ant-sla' => ['ant-sla.view', 'ant-sla.update'],
        'ant-maintenance' => ['ant-maintenance.view', 'ant-maintenance.update', 'ant-maintenance.approve'],
        'ant-report' => ['ant-report.view', 'ant-report.export'],
        'ant-audit' => ['ant-audit.view'],
    ];

    public const ROLES = [
        'ict-network' => [
            // LibreNMS
            'device.viewAll', 'device.view', 'device.create', 'device.update', 'device.updateNotes', 'device.debug',
            'port.viewAll', 'port.view', 'port.update',
            'device-group.viewAll', 'device-group.view', 'device-group.create', 'device-group.update',
            'port-group.viewAll', 'port-group.view', 'port-group.create', 'port-group.update',
            'location.viewAll', 'location.view', 'location.create', 'location.update',
            'alert.viewAll', 'alert.view', 'alert.detail', 'alert.update',
            'alert-log.viewAll', 'alert-log.view',
            'alert-rule.viewAll', 'alert-rule.view', 'alert-rule.create', 'alert-rule.update',
            'alert-schedule.view', 'alert-schedule.create', 'alert-schedule.update', 'alert-schedule.delete',
            'custom-map.viewAll', 'custom-map.view', 'custom-map.create', 'custom-map.update',
            'link.viewAll', 'link.view', 'vlan.viewAll',
            'inventory.viewAll', 'inventory.view',
            'eventlog.viewAll', 'eventlog.view', 'syslog.viewAll', 'syslog.view',
            'outage.viewAll', 'outage.view',
            'routing.viewAll', 'routing.view',
            'service.viewAll', 'service.view', 'service.create', 'service.update',
            'processor.viewAll', 'processor.view', 'storage.viewAll', 'storage.view', 'mempool.view',
            'wireless-sensor.update',
            'config-backup.view', 'oxidized.search',
            'tools.oui',
            // ANTAREJA
            'ant-dashboard.technical',
            'ant-site.view', 'ant-site.update',
            'ant-inventory.view', 'ant-inventory.update',
            'ant-discovery.view', 'ant-discovery.approve',
            'ant-mapping.view', 'ant-mapping.update', 'ant-mapping.verify',
            'ant-ipam.view', 'ant-ipam.update',
            'ant-quality.view', 'ant-quality.update',
            'ant-cctv.view',
            'ant-incident.view', 'ant-incident.create', 'ant-incident.update', 'ant-incident.close',
            'ant-sla.view',
            'ant-maintenance.view', 'ant-maintenance.update',
            'ant-report.view',
        ],
        'ict-cctv-support' => [
            // LibreNMS
            'device.viewAll', 'device.view', 'device.updateNotes',
            'port.viewAll', 'port.view',
            'location.viewAll', 'location.view',
            'alert.viewAll', 'alert.view', 'alert.detail', 'alert.update',
            'alert-log.viewAll', 'alert-log.view',
            'alert-schedule.view', 'alert-schedule.create', 'alert-schedule.update',
            'inventory.viewAll', 'inventory.view',
            'eventlog.viewAll', 'eventlog.view',
            'outage.viewAll', 'outage.view',
            'link.viewAll', 'link.view',
            // ANTAREJA
            'ant-dashboard.technical',
            'ant-site.view',
            'ant-inventory.view', 'ant-inventory.update',
            'ant-discovery.view',
            'ant-mapping.view', 'ant-mapping.update',
            'ant-cctv.view', 'ant-cctv.update',
            'ant-ipam.view',
            'ant-quality.view',
            'ant-incident.view', 'ant-incident.create', 'ant-incident.update', 'ant-incident.close',
            'ant-maintenance.view', 'ant-maintenance.update',
            'ant-report.view',
        ],
        'management' => [
            // LibreNMS (hanya baca)
            'device.viewAll', 'device.view',
            'location.viewAll', 'location.view',
            'alert.viewAll', 'alert.view',
            'alert-log.viewAll',
            'outage.viewAll', 'outage.view',
            // ANTAREJA
            'ant-dashboard.management',
            'ant-site.view',
            'ant-inventory.view',
            'ant-quality.view',
            'ant-cctv.view',
            'ant-incident.view',
            'ant-sla.view',
            'ant-maintenance.view',
            'ant-report.view', 'ant-report.export',
        ],
    ];

    public const LABELS = [
        'ict-network' => 'ICT Network',
        'ict-cctv-support' => 'ICT CCTV / Support',
        'admin' => 'ICT Admin',
        'management' => 'Management',
    ];

    /**
     * @return string[]
     */
    public static function allAntarejaPermissions(): array
    {
        return array_merge(...array_values(self::PERMISSIONS));
    }
}
