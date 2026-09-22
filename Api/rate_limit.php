<?php
declare(strict_types=1);

function takeRateLimit(
    mysqli $conn,
    string $scope,
    string $identifier,
    int $limit = 5,
    int $windowSeconds = 900
): int {
    $key = hash('sha256', $scope . ':' . $identifier);

    mysqli_begin_transaction($conn);

    try {
        // Opret tælleren, hvis den ikke findes.
        // Ved eksisterende tæller låses rækken til transaktionen er færdig.
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO auth_rate_limits
                (bucket_key, attempts, window_started_at, blocked_until)
             VALUES (?, 0, UTC_TIMESTAMP(), NULL)
             ON DUPLICATE KEY UPDATE bucket_key = bucket_key"
        );

        mysqli_stmt_bind_param($stmt, 's', $key);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Rækkelåsen forhindrer samtidige requests i at omgå grænsen.
        $stmt = mysqli_prepare(
            $conn,
            "SELECT attempts,
                    TIMESTAMPDIFF(
                        SECOND,
                        window_started_at,
                        UTC_TIMESTAMP()
                    ) AS elapsed
             FROM auth_rate_limits
             WHERE bucket_key = ?
             FOR UPDATE"
        );

        mysqli_stmt_bind_param($stmt, 's', $key);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        $attempts = (int) $row['attempts'];
        $elapsed = max(0, (int) $row['elapsed']);

        if ($elapsed >= $windowSeconds) {
            // En ny periode starter med dette forsøg.
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE auth_rate_limits
                 SET attempts = 1,
                     window_started_at = UTC_TIMESTAMP(),
                     blocked_until = NULL
                 WHERE bucket_key = ?"
            );

            mysqli_stmt_bind_param($stmt, 's', $key);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } elseif ($attempts >= $limit) {
            mysqli_commit($conn);

            // Antal sekunder til brugeren må forsøge igen.
            return max(1, $windowSeconds - $elapsed);
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE auth_rate_limits
                 SET attempts = attempts + 1
                 WHERE bucket_key = ?"
            );

            mysqli_stmt_bind_param($stmt, 's', $key);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        mysqli_commit($conn);

        return 0;
    } catch (Throwable $exception) {
        mysqli_rollback($conn);
        throw $exception;
    }
}