<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Session;

final class BrowserSession
{
    private const PATH = '/api/v1/bugwatch/browser-session';

    /**
     * @param callable(string,list<string>):array{status:int,body:string}|null $sender
     * @return array{token:string,expiresAt:int}
     */
    public static function mint(
        string $projectKey,
        string $endpoint = 'https://api.newinstance.cloud',
        ?callable $sender = null,
    ): array {
        $url = rtrim($endpoint, '/') . self::PATH;
        $headers = ['x-api-key: ' . $projectKey, 'Content-Type: application/json'];
        $sender ??= self::defaultSender();

        $res = $sender($url, $headers);
        if ($res['status'] < 200 || $res['status'] >= 300) {
            throw new \RuntimeException('BugWatch: failed to mint browser session (HTTP ' . $res['status'] . ')');
        }

        /** @var array{token?:string,expiresAt?:int} $data */
        $data = json_decode($res['body'], true) ?: [];
        if (!isset($data['token'], $data['expiresAt'])) {
            throw new \RuntimeException('BugWatch: malformed browser-session response.');
        }

        return ['token' => (string) $data['token'], 'expiresAt' => (int) $data['expiresAt']];
    }

    /** @return callable(string,list<string>):array{status:int,body:string} */
    private static function defaultSender(): callable
    {
        return static function (string $url, array $headers): array {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => '{}',
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT_MS => 15000,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

            return ['status' => $status, 'body' => is_string($body) ? $body : ''];
        };
    }
}
