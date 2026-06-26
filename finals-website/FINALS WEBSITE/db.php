<?php
function env_or_default(string $key, string $default): string
{
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

function get_db_config(string $database): array
{
    $localConfigPath = __DIR__ . '/db_config.local.php';
    if (file_exists($localConfigPath)) {
        $cfg = require $localConfigPath;
        if (is_array($cfg) && isset($cfg[$database]) && is_array($cfg[$database])) {
            return $cfg[$database];
        }
    }

    if ($database === 'login') {
        return [
            'host' => env_or_default('LOGIN_DB_HOST', '127.0.0.1'),
            'port' => (int)env_or_default('LOGIN_DB_PORT', '3306'),
            'name' => env_or_default('LOGIN_DB_NAME', 'login_credentials'),
            'user' => env_or_default('LOGIN_DB_USER', 'root'),
            'pass' => env_or_default('LOGIN_DB_PASS', ''),
        ];
    }

    return [
        'host' => env_or_default('MAIN_DB_HOST', '127.0.0.1'),
        'port' => (int)env_or_default('MAIN_DB_PORT', '3306'),
        'name' => env_or_default('MAIN_DB_NAME', 'main'),
        'user' => env_or_default('MAIN_DB_USER', 'root'),
        'pass' => env_or_default('MAIN_DB_PASS', ''),
    ];
}

function connect_db(string $database): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $cfg = get_db_config($database);
    $conn = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name'], $cfg['port']);
    $conn->set_charset('utf8mb4');
    return $conn;
}

function connect_main_db(): mysqli
{
    return connect_db('main');
}

function connect_login_db(): mysqli
{
    return connect_db('login');
}
?>
