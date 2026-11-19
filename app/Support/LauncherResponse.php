<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LauncherResponse
{
    public const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public static function json(Request $request, mixed $data, int $status = 200): Response
    {
        $json = (string) json_encode($data, self::JSON_FLAGS);

        if ($status !== 200) {
            return JsonResponse::fromJsonString($json, $status);
        }

        $etag = '"' . sha1($json) . '"';
        $headers = [
            'ETag' => $etag,
            'Cache-Control' => 'no-cache',
        ];

        if (self::matchesIfNoneMatch($request->headers->get('If-None-Match'), $etag)) {
            return response('', 304)->withHeaders($headers);
        }

        return JsonResponse::fromJsonString($json, 200, $headers);
    }

    public static function matchesIfNoneMatch(?string $ifNoneMatch, string $etag): bool
    {
        if ($ifNoneMatch === null || trim($ifNoneMatch) === '') {
            return false;
        }

        $ifNoneMatch = trim($ifNoneMatch);
        if ($ifNoneMatch === '*') {
            return true;
        }

        $target = trim($etag, '"');

        foreach (explode(',', $ifNoneMatch) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            if (str_starts_with($candidate, 'W/') || str_starts_with($candidate, 'w/')) {
                $candidate = trim(substr($candidate, 2));
            }

            $candidate = trim($candidate, '"');
            if ($candidate !== '' && hash_equals($target, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
