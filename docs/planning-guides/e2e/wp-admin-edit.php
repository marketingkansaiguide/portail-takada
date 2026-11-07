<?php
/**
 * Simule l'enregistrement d'une mission depuis l'administration Planning Guides
 * (même fonction que l'AJAX « pm_update_availability_status » de la modale).
 * Usage : wp eval-file wp-admin-edit.php <availability_id> <employee_id> <start> <end> [status]
 */
[$availabilityId, $employeeId, $start, $end] = array_map('strval', array_slice($args, 0, 4));
$status = $args[4] ?? 'réservé';

global $wpdb;
$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pm_availabilities WHERE id = %d", (int) $availabilityId));
if (! $row) {
    echo json_encode(['success' => false, 'data' => 'ligne introuvable']);
    return;
}

wp_set_current_user(1);
if (! defined('DOING_AJAX')) {
    define('DOING_AJAX', true);
}
add_filter('wp_die_ajax_handler', fn () => function ($message) { throw new RuntimeException('WP_DIE:' . (is_string($message) ? $message : '')); });

$_POST = [
    'nonce' => wp_create_nonce('pm_status'),
    'availability_id' => (string) $row->id,
    'employee_id' => (string) $employeeId,
    'status' => $status,
    'dates_json' => wp_json_encode([['date' => $row->start_date, 'start' => $start, 'end' => $end]]),
    'mission_title' => $row->mission_title,
    'agency' => $row->agency,
    'client' => $row->client,
    'comment' => $row->comment,
    'remuneration' => $row->remuneration,
    'suppress_notifications' => '1',
];
$_REQUEST = $_POST;

ob_start();
try {
    pm_update_availability_status();
} catch (Throwable $e) {
    // wp_send_json_* termine par wp_die : intercepté pour garder la main.
}
$out = trim(ob_get_clean());
echo $out !== '' ? $out : json_encode(['success' => null]);
