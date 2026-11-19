<?php

namespace App\Support;

final class YouTubeUrl
{
    public static function extractVideoId(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $trimmed = trim($input);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $trimmed) === 1) {
            return $trimmed;
        }

        $patterns = [
            '/^(?:https?:\/\/)?(?:(?:www|m)\.)?youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})(?:[\/?#].*)?$/i',
            '/^(?:https?:\/\/)?(?:(?:www|m)\.)?youtube\.com\/live\/([a-zA-Z0-9_-]{11})(?:[\/?#].*)?$/i',
            '/^(?:https?:\/\/)?(?:(?:www|m)\.)?youtube(?:-nocookie)?\.com\/(?:embed|v)\/([a-zA-Z0-9_-]{11})(?:[\/?#].*)?$/i',
            '/^(?:https?:\/\/)?youtu\.be\/([a-zA-Z0-9_-]{11})(?:[\/?#].*)?$/i',
            '/^(?:https?:\/\/)?(?:(?:www|m)\.)?youtube\.com\/watch\?(?:.*&)?v=([a-zA-Z0-9_-]{11})(?:[&#].*)?$/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $trimmed, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    public static function isValid(?string $input): bool
    {
        return self::extractVideoId($input) !== null;
    }

    public static function detectType(?string $input): string
    {
        return $input && stripos($input, 'youtube.com/shorts/') !== false ? 'short' : 'video';
    }
}
