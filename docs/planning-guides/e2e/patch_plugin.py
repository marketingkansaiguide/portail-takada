import sys
path = sys.argv[1]
src = open(path, encoding='utf-8', newline='').read()

def replace_once(old, new):
    global src
    count = src.count(old)
    assert count == 1, (count, old[:80])
    src = src.replace(old, new, 1)

# 1. Édition depuis l'administration : la première ligne recréée d'une mission liée au
#    portail reprend son identifiant d'origine (external_mission_id) s'il est libre.
replace_once(
"""        $result = $wpdb->insert(
            $wpdb->prefix . 'pm_availabilities',
            [
                'mission_group_id' => $is_calendar_marker ? null : $mission_group_id,""",
"""        // Correctif 1.53.29 : une mission liée au portail garde son identifiant d'origine
        // (external_mission_id) lorsqu'elle est recréée par l'édition, afin que l'annulation
        // envoyée par le portail retrouve toujours la même ligne. Missions WordPress : inchangé.
        $pm_keep_id = 0;
        if ($inserted_count === 0 && !$is_calendar_marker && !empty($external_source) && !empty($external_mission_id)) {
            $pm_keep_id_taken = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}pm_availabilities WHERE id = %d", (int) $external_mission_id));
            if (!$pm_keep_id_taken) {
                $pm_keep_id = (int) $external_mission_id;
            }
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'pm_availabilities',
            [
                'mission_group_id' => $is_calendar_marker ? null : $mission_group_id,""")

replace_once(
"""                ,'callback_status' => $is_calendar_marker ? null : $callback_status
                ,'mission_type' => $is_calendar_marker ? null : $mission_type
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s']
        );""",
"""                ,'callback_status' => $is_calendar_marker ? null : $callback_status
                ,'mission_type' => $is_calendar_marker ? null : $mission_type
            ] + ($pm_keep_id ? ['id' => $pm_keep_id] : []),
            array_merge(['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s'], $pm_keep_id ? ['%d'] : [])
        );""")

# 2. Annulation par le portail : retrouve aussi une mission recréée avant le correctif
#    (identifiant de ligne différent de external_mission_id).
replace_once(
"""    $mission = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pm_availabilities WHERE id = %d AND external_source = %s AND ticketing_order_item_id = %d LIMIT 1",
        $mission_id,
        'ticketing_portal',
        $ticketing_order_item_id
    ));""",
"""    $mission = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pm_availabilities WHERE (id = %d OR external_mission_id = %d) AND external_source = %s AND ticketing_order_item_id = %d ORDER BY (id = %d) DESC, id ASC LIMIT 1",
        $mission_id,
        $mission_id,
        'ticketing_portal',
        $ticketing_order_item_id,
        $mission_id
    ));""")

# Version
replace_once("Version: 1.53.28", "Version: 1.53.29")

open(path, 'w', encoding='utf-8', newline='').write(src)
print('patch appliqué')
