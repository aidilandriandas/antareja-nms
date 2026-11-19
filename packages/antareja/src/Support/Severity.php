<?php

namespace Antareja\Support;

/**
 * Skala severity.
 *  - Problem: 5 tingkat ala Zabbix (information … disaster).
 *  - Incident: 4 tingkat operasional (low … critical).
 * Setiap tingkat punya "tone" design system (warna + ikon + kata).
 */
class Severity
{
    public const PROBLEM = [
        'information' => ['label' => 'Information', 'rank' => 1, 'tone' => 'info'],
        'warning' => ['label' => 'Warning', 'rank' => 2, 'tone' => 'warn'],
        'average' => ['label' => 'Average', 'rank' => 3, 'tone' => 'warn'],
        'high' => ['label' => 'High', 'rank' => 4, 'tone' => 'crit'],
        'disaster' => ['label' => 'Disaster', 'rank' => 5, 'tone' => 'down'],
    ];

    public const INCIDENT = [
        'low' => ['label' => 'Low', 'rank' => 1, 'tone' => 'info'],
        'medium' => ['label' => 'Medium', 'rank' => 2, 'tone' => 'warn'],
        'high' => ['label' => 'High', 'rank' => 3, 'tone' => 'crit'],
        'critical' => ['label' => 'Critical', 'rank' => 4, 'tone' => 'down'],
    ];

    /** Severity incident yang dibuat dari problem. */
    public const PROBLEM_TO_INCIDENT = [
        'information' => 'low', 'warning' => 'low', 'average' => 'medium', 'high' => 'high', 'disaster' => 'critical',
    ];

    public static function problemRank(?string $s): int
    {
        return self::PROBLEM[$s]['rank'] ?? 0;
    }

    public static function incidentRank(?string $s): int
    {
        return self::INCIDENT[$s]['rank'] ?? 0;
    }

    public static function problem(?string $s): array
    {
        return self::PROBLEM[$s] ?? ['label' => (string) $s, 'rank' => 0, 'tone' => 'unknown'];
    }

    public static function incident(?string $s): array
    {
        return self::INCIDENT[$s] ?? ['label' => (string) $s, 'rank' => 0, 'tone' => 'unknown'];
    }
}
