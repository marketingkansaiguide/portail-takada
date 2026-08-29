<?php

return [
    // La sauvegarde s'exécute toutes les 6 heures. Une marge de 2 heures
    // permet de détecter rapidement un échec sans alerter sur un léger retard.
    'backup_max_age_hours' => (int) env('MONITOR_BACKUP_MAX_AGE_HOURS', 8),
    'queue_stale_minutes' => (int) env('MONITOR_QUEUE_STALE_MINUTES', 20),
    'disk_max_usage_percent' => (int) env('MONITOR_DISK_MAX_USAGE_PERCENT', 85),
    'application_error_window_minutes' => (int) env('MONITOR_ERROR_WINDOW_MINUTES', 15),
    'application_error_threshold' => (int) env('MONITOR_ERROR_THRESHOLD', 3),
    'alert_cooldown_minutes' => (int) env('MONITOR_ALERT_COOLDOWN_MINUTES', 360),
];
