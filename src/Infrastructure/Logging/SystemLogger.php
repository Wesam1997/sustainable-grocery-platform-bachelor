<?php
declare(strict_types=1);

final class SystemLogger implements LoggerInterface
{
    public function error(string $event, array $context = []): void
    {
        error_log(json_encode(['time' => gmdate('c'), 'level' => 'error', 'event' => $event, 'context' => $context], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }
}
