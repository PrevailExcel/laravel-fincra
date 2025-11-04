<?php

namespace PrevailExcel\Fincra\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Config;

class VerifyFincraWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Fincra-Signature');

        if (!$this->verifySignature($payload, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        return $next($request);
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload
     * @param string|null $signature
     * @return bool
     */
    protected function verifySignature(string $payload, ?string $signature): bool
    {
        if (!$signature) {
            return false;
        }

        $webhookSecret = Config::get('fincra.webhookSecret');
        $computedSignature = hash_hmac('sha512', $payload, $webhookSecret);

        return hash_equals($computedSignature, $signature);
    }
}