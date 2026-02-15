<?php
declare(strict_types=1);

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_int(string $key, array $src): int
{
    if (!array_key_exists($key, $src) || !is_numeric($src[$key])) {
        throw new InvalidArgumentException("{$key} is required integer");
    }
    return (int)$src[$key];
}

function require_str(string $key, array $src, int $maxLen = 1000): string
{
    if (!array_key_exists($key, $src)) {
        throw new InvalidArgumentException("{$key} is required string");
    }
    $val = (string)$src[$key];
    if (mb_strlen($val) > $maxLen) {
        throw new InvalidArgumentException("{$key} exceeds max length {$maxLen}");
    }
    return $val;
}
