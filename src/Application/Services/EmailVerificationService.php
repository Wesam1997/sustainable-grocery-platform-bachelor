<?php
declare(strict_types=1);

namespace App\Application\Services;

use mysqli;
use RuntimeException;

final class EmailVerificationService
{
    public function __construct(
        private mysqli $connection
    ) {}

    public function createToken(int $userId): string
    {
        // Generate a secure random token
        $token = bin2hex(random_bytes(32));

        // Store only the SHA-256 hash
        $tokenHash = hash('sha256', $token);

        // Link expires after 24 hours
        $expiresAt = (new \DateTimeImmutable('+24 hours'))
            ->format('Y-m-d H:i:s');

        $stmt = $this->connection->prepare(
            'INSERT INTO email_verification_tokens
             (user_id, token_hash, expires_at)
             VALUES (?, ?, ?)'
        );

        try {
            $stmt->bind_param(
                'iss',
                $userId,
                $tokenHash,
                $expiresAt
            );

            $stmt->execute();
        } finally {
            $stmt->close();
        }

        // Return the original token for the email link
        return $token;
    }

public function verifyToken(string $token): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return false;
    }

    $tokenHash = hash('sha256', $token);

    $this->connection->begin_transaction();

    try {
        $stmt = $this->connection->prepare(
            'SELECT id, user_id
             FROM email_verification_tokens
             WHERE token_hash = ?
               AND expires_at > NOW()
             LIMIT 1
             FOR UPDATE'
        );

        try {
            $stmt->bind_param('s', $tokenHash);
            $stmt->execute();

            $record = $stmt->get_result()->fetch_assoc();
        } finally {
            $stmt->close();
        }

        if (!$record) {
            $this->connection->rollback();
            return false;
        }

        $userId = (int) $record['user_id'];

        $stmt = $this->connection->prepare(
            'UPDATE users
             SET email_verified_at = NOW()
             WHERE id = ?
               AND email_verified_at IS NULL'
        );

        try {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
        } finally {
            $stmt->close();
        }

        // Remove all verification tokens for this user.
        $stmt = $this->connection->prepare(
            'DELETE FROM email_verification_tokens
             WHERE user_id = ?'
        );

        try {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
        } finally {
            $stmt->close();
        }

        $this->connection->commit();

        return true;
    } catch (\Throwable $exception) {
        $this->connection->rollback();
        throw $exception;
    }
}

}
