<?php
const LOGIN_MAX_FAILS    = 5;
const LOGIN_IP_MAX_FAILS = 20;
const LOGIN_WINDOW_MIN   = 15;

function login_locked(mysqli $conn, string $key, string $ip): bool
{
    $window = LOGIN_WINDOW_MIN;

    $stmt = $conn->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE identifier = ? AND attempted_at > NOW() - INTERVAL ? MINUTE'
    );
    $stmt->bind_param('si', $key, $window);
    $stmt->execute();
    if ((int) $stmt->get_result()->fetch_row()[0] >= LOGIN_MAX_FAILS) {
        return true;
    }

    $stmt = $conn->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip = ? AND attempted_at > NOW() - INTERVAL ? MINUTE'
    );
    $stmt->bind_param('si', $ip, $window);
    $stmt->execute();

    return (int) $stmt->get_result()->fetch_row()[0] >= LOGIN_IP_MAX_FAILS;
}

function record_login_failure(mysqli $conn, string $key, string $ip): void
{
    $stmt = $conn->prepare('INSERT INTO login_attempts (identifier, ip) VALUES (?, ?)');
    $stmt->bind_param('ss', $key, $ip);
    $stmt->execute();

    $conn->query('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
}

function clear_login_failures(mysqli $conn, string $key): void
{
    $stmt = $conn->prepare('DELETE FROM login_attempts WHERE identifier = ?');
    $stmt->bind_param('s', $key);
    $stmt->execute();
}
