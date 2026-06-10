<?php
/**
 * Config migration script for Nano Node Monitor.
 *
 * Reads the existing modules/config.php, applies any pending schema
 * migrations, backs the old file up, and writes a clean, minimal
 * config.php containing only the values that differ from the defaults.
 *
 * Usage (from the project root):
 *   php scripts/migrate-config.php            migrate modules/config.php
 *   php scripts/migrate-config.php --dry-run  print the result, change nothing
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

$dryRun     = in_array('--dry-run', $argv, true);
$modulesDir = dirname(__DIR__) . '/modules';
$configPath = $modulesDir . '/config.php';

require_once $modulesDir . '/constants.php';

if (!file_exists($configPath)) {
    exit("No modules/config.php found - nothing to migrate. " .
         "Copy config.sample.php to config.php first.\n");
}

// resolve symlinks (Docker links modules/config.php to the /opt volume) so
// the rewritten config and its backup both land on the persistent target
$configPath = realpath($configPath);

// ---------------------------------------------------------------------------
// Inspect the config source BEFORE executing it. Configs that contain custom
// PHP logic (host switching on $_SERVER, guards that die(), conditionals)
// must not be flattened: rewriting would bake in one branch and could even
// abort this script mid-include. Detect those token-wise and skip.
// ---------------------------------------------------------------------------

function nnm_config_is_scripted($source, &$reason)
{
    $flagged = array(
        T_IF => 'if', T_ELSEIF => 'elseif', T_ELSE => 'else', T_SWITCH => 'switch',
        T_FOR => 'for', T_FOREACH => 'foreach', T_WHILE => 'while', T_DO => 'do',
        T_FUNCTION => 'function', T_EXIT => 'exit/die', T_ECHO => 'echo',
        T_PRINT => 'print', T_REQUIRE => 'require', T_REQUIRE_ONCE => 'require_once',
        T_INCLUDE => 'include', T_INCLUDE_ONCE => 'include_once',
    );
    $superglobals = array('$_SERVER', '$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_ENV', '$GLOBALS');

    foreach (token_get_all($source) as $token) {
        if (!is_array($token)) {
            continue;
        }
        if (isset($flagged[$token[0]])) {
            $reason = $flagged[$token[0]];
            return true;
        }
        if ($token[0] === T_VARIABLE && in_array($token[1], $superglobals, true)) {
            $reason = $token[1];
            return true;
        }
    }
    return false;
}

$source = file_get_contents($configPath);

// version marker readable without executing the file
if (preg_match('/\$configVersion\s*=\s*(\d+)\s*;/', $source, $m) && (int) $m[1] >= CONFIG_VERSION) {
    exit("config.php is already at schema version {$m[1]} - nothing to do.\n");
}

$reason = '';
if (nnm_config_is_scripted($source, $reason)) {
    echo "config.php contains custom PHP logic ($reason) - automatic migration skipped.\n";
    echo "Review config.sample.php for current options and update config.php manually,\n";
    echo "then set \$configVersion = " . CONFIG_VERSION . "; to silence this notice.\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Load defaults and the user's config in an isolated scope, mirroring the
// include order of modules/includes.php.
// ---------------------------------------------------------------------------

function nnm_load_config_state($modulesDir, $configPath)
{
    include $modulesDir . '/defaults.php';
    $defaults = get_defined_vars();
    unset($defaults['modulesDir'], $defaults['configPath']);

    include $configPath;
    $effective = get_defined_vars();
    unset($effective['modulesDir'], $effective['configPath'], $effective['defaults']);

    return array($defaults, $effective);
}

list($defaults, $cfg) = nnm_load_config_state($modulesDir, $configPath);

$fromVersion = (int) ($cfg['configVersion'] ?? 0);

if ($fromVersion >= CONFIG_VERSION) {
    exit("config.php is already at schema version $fromVersion - nothing to do.\n");
}

// ---------------------------------------------------------------------------
// Migration steps. Key = the schema version the step migrates TO.
// Steps run sequentially, so each rule fires exactly once per config.
// ---------------------------------------------------------------------------

$migrations = array(

    // v0 -> v1
    1 => function (array $cfg, array &$notes) {
        // defunct block explorers -> blocklattice
        if (in_array($cfg['blockExplorer'] ?? '', array('ninja', 'nanocrawler'), true)) {
            $notes[] = "blockExplorer '{$cfg['blockExplorer']}' is defunct -> 'blocklattice'";
            $cfg['blockExplorer'] = 'blocklattice';
        }
        // one-time move of dark theme users onto the new modern theme;
        // later schema versions must not touch the theme again
        if (($cfg['themeChoice'] ?? '') === 'dark') {
            $notes[] = "themeChoice 'dark' -> 'modern' (one-time, v1 only)";
            $cfg['themeChoice'] = 'modern';
        }
        return $cfg;
    },

    // v1 -> v2
    2 => function (array $cfg, array &$notes) {
        // meltingice explorer is defunct -> blocklattice
        if (($cfg['blockExplorer'] ?? '') === 'meltingice') {
            $notes[] = "blockExplorer 'meltingice' is defunct -> 'blocklattice'";
            $cfg['blockExplorer'] = 'blocklattice';
        }
        return $cfg;
    },
);

$notes = array();
for ($v = $fromVersion + 1; $v <= CONFIG_VERSION; $v++) {
    if (isset($migrations[$v])) {
        $cfg = $migrations[$v]($cfg, $notes);
    }
    $cfg['configVersion'] = $v;
}

// ---------------------------------------------------------------------------
// Render the new config.php: only values that differ from the defaults.
// ---------------------------------------------------------------------------

// dynamic defaults: skip when the stored value just mirrors them
if (($cfg['nanoNodeName'] ?? null) === gethostname()) {
    unset($cfg['nanoNodeName']);
}
if (array_key_exists('nanoDonationAccount', $cfg)
        && ($cfg['nanoDonationAccount'] ?? null) === ($cfg['nanoNodeAccount'] ?? null)) {
    unset($cfg['nanoDonationAccount']);
}

$sections = array(
    'Node'       => array('nanoNodeAccount', 'nanoDonationAccount', 'nanoNodeRPCIP', 'nanoNodeRPCPort'),
    'General'    => array('currency', 'themeChoice', 'blockExplorer', 'widgetType',
                          'autoRefreshInSeconds', 'nanoNodeName', 'nodeLocation',
                          'welcomeMsg', 'nanoNumDecimalPlaces'),
    'Cache'      => array('cacheTimeToLive', 'cache'),
    'Monitoring' => array('uptimerobotApiKey', 'googleAnalyticsId'),
    'Social'     => array('socials'),
);

$out  = "<?php\n\n";
$out .= "// Nano Node Monitor configuration\n";
$out .= "// Migrated by scripts/migrate-config.php on " . date('Y-m-d') . "\n";
$out .= "// All available options are documented in config.sample.php.\n\n";
$out .= "// Config schema version - leave as is\n";
$out .= "\$configVersion = " . (int) $cfg['configVersion'] . ";\n";

foreach ($sections as $label => $keys) {
    $lines = array();
    foreach ($keys as $key) {
        if (!array_key_exists($key, $cfg)) {
            continue;
        }
        $isCustom = !array_key_exists($key, $defaults) || $cfg[$key] !== $defaults[$key];
        // arrays: loose-compare so identical contents count as default
        if (is_array($cfg[$key]) && array_key_exists($key, $defaults) && $cfg[$key] == $defaults[$key]) {
            $isCustom = false;
        }
        if ($isCustom) {
            $lines[] = '$' . $key . ' = ' . var_export($cfg[$key], true) . ";\n";
        }
    }
    if ($lines) {
        $out .= "\n// ----------- $label -----------\n\n" . implode('', $lines);
    }
}

// ---------------------------------------------------------------------------
// Report, back up, write.
// ---------------------------------------------------------------------------

echo "Migrating config.php: schema version $fromVersion -> " . CONFIG_VERSION . "\n";
foreach ($notes as $note) {
    echo "  - $note\n";
}

if ($dryRun) {
    echo "\n--dry-run: new config.php would be:\n\n$out";
    exit(0);
}

$backupPath = $configPath . '.bak-' . date('Ymd-His');
if (!copy($configPath, $backupPath)) {
    exit("ERROR: could not back up config.php to $backupPath - aborting.\n");
}
echo "  - old config backed up to " . basename($backupPath) . "\n";

if (file_put_contents($configPath, $out, LOCK_EX) === false) {
    exit("ERROR: could not write $configPath. Old config restored from backup.\n");
}

// sanity check: the new file must parse
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($configPath), $lintOut, $lintCode);
if ($lintCode !== 0) {
    copy($backupPath, $configPath);
    exit("ERROR: generated config failed php -l, old config restored.\n");
}

echo "Done. New config.php written at schema version " . CONFIG_VERSION . ".\n";
