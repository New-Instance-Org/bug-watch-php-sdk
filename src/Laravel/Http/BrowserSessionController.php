<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Laravel\Http;

use Illuminate\Http\JsonResponse;

use function NewInstance\BugWatch\mintBrowserSession;

final class BrowserSessionController
{
    /** @var (callable(array{projectKey:string,endpoint?:string}):array{token:string,expiresAt:int})|null */
    public $minter = null;

    public function __invoke(): JsonResponse
    {
        /** @var mixed $rawKey */
        $rawKey = config('bugwatch.key', '');
        $key = is_string($rawKey) ? $rawKey : '';
        /** @var mixed $rawEndpoint */
        $rawEndpoint = config('bugwatch.endpoint', 'https://api.newinstance.cloud');
        $endpoint = is_string($rawEndpoint) ? $rawEndpoint : 'https://api.newinstance.cloud';
        try {
            /** @var callable(array{projectKey:string,endpoint?:string}):array{token:string,expiresAt:int} $mint */
            $mint = $this->minter ?? static function (array $o): array {
                /** @var array{projectKey:string,endpoint?:string} $o */
                return mintBrowserSession($o);
            };
            /** @var array{projectKey:string,endpoint?:string} $mintArgs */
            $mintArgs = ['projectKey' => $key, 'endpoint' => $endpoint];
            $session = $mint($mintArgs);

            return new JsonResponse($session);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'failed to mint BugWatch session'], 502);
        }
    }
}
