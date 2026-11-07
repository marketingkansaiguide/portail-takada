import sys
path = sys.argv[1]
src = open(path, encoding='utf-8', newline='').read()

old = "PM_PLUGIN_DIR . 'credentials.json'"
n = src.count(old)
assert n == 8, n
src = src.replace(old, "pm_google_credentials_path()")

anchor = "define('PM_PLUGIN_DIR', plugin_dir_path(__FILE__));"
assert src.count(anchor) == 1
src = src.replace(anchor, anchor + """

/**
 * Clé du compte de service Google (Drive). Correctif 1.53.29 : elle peut être rangée
 * hors du dossier web en définissant PM_GOOGLE_CREDENTIALS (chemin absolu) dans
 * wp-config.php. Sans cette constante, l'emplacement historique est conservé.
 */
function pm_google_credentials_path() {
    if (defined('PM_GOOGLE_CREDENTIALS') && is_readable(PM_GOOGLE_CREDENTIALS)) {
        return PM_GOOGLE_CREDENTIALS;
    }
    return PM_PLUGIN_DIR . 'credentials.json';
}""", 1)

open(path, 'w', encoding='utf-8', newline='').write(src)
print('ok', n, 'occurrences')
