{{-- Bar persentase → ProgressBar design system. $value (0-100), $warn (80), $crit (90) --}}
<x-antareja::progress :value="$value" :warn-at="$warn ?? 80" :crit-at="$crit ?? 90" :label="$label ?? 'Utilisasi'" />
