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

function extract_youtube_video_id(?string $input): ?string
{
    $raw = trim((string)$input);
    if ($raw === '') {
        return null;
    }

    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $raw) === 1) {
        return $raw;
    }

    $parts = @parse_url($raw);
    if (!is_array($parts)) {
        return null;
    }

    $host = strtolower((string)($parts['host'] ?? ''));
    $path = (string)($parts['path'] ?? '');

    if ($host === 'youtu.be') {
        $id = ltrim($path, '/');
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1 ? $id : null;
    }

    if (str_contains($host, 'youtube.com')) {
        parse_str((string)($parts['query'] ?? ''), $query);
        $v = (string)($query['v'] ?? '');
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $v) === 1) {
            return $v;
        }

        if (preg_match('#/embed/([A-Za-z0-9_-]{11})#', $path, $m) === 1) {
            return $m[1];
        }
        if (preg_match('#/shorts/([A-Za-z0-9_-]{11})#', $path, $m) === 1) {
            return $m[1];
        }
    }

    return null;
}
