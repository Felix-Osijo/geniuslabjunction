<?php
function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

function app_url(): string
{
    $config = app_config();
    return rtrim($config['app_url'] ?? 'http://localhost/genius-lab-junction', '/');
}

function send_reset_email(string $to, string $link): void
{
    $config  = app_config();
    $subject = 'Reset your Genius Hub password';
    $body    = "Hi,\n\nUse this link to choose a new password. It works for 1 hour:\n$link\n\n"
             . "If you didn't ask for this, you can ignore this email.\n";

    if (($config['mail_mode'] ?? 'log') === 'mail') {
        $from = $config['mail_from'] ?? 'no-reply@localhost';
        mail($to, $subject, $body, "From: $from\r\nContent-Type: text/plain; charset=UTF-8");
        return;
    }

    error_log("Genius Hub reset link for $to: $link");
}
