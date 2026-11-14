<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Jakarta');
session_start();

const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'db_clinic';

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $db->set_charset('utf8mb4');
} catch (mysqli_sql_exception $ex) {
    http_response_code(500);
    exit('Koneksi database gagal. Pastikan database db_clinic sudah diimport dan kredensial di config.php benar.');
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_show(): void
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="alert ' . e($f['type']) . '">' . e($f['message']) . '</div>';
    }
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function is_valid_date(string $value, string $format): bool
{
    $d = DateTime::createFromFormat('!' . $format, $value);
    return $d !== false && $d->format($format) === $value;
}

function validate_multi($input, array $allowed): array
{
    if ($input === null) {
        return [[], null];
    }
    if (!is_array($input)) {
        return [[], 'Format pilihan tidak valid.'];
    }
    $selected = [];
    foreach ($input as $item) {
        if (!is_string($item) || !in_array($item, $allowed, true)) {
            return [[], 'Terdapat pilihan yang tidak tersedia.'];
        }
        $selected[$item] = true;
    }
    return [array_keys($selected), null];
}

function value_exists(mysqli $db, string $table, string $column, string $value, string $pk, int $ignoreId): bool
{
    $sql = "SELECT 1 FROM `$table` WHERE `$column` = ? AND `$pk` <> ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('si', $value, $ignoreId);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $found;
}

function json_to_list(?string $json): array
{
    $arr = json_decode($json ?? '[]', true);
    return is_array($arr) ? array_values(array_filter($arr, 'is_string')) : [];
}