<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyUcpSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if(app()->environment('local') && $request->has('skip_ucp_signature')) {
            // In ambiente di sviluppo, bypassiamo la verifica per facilitare i test (assicurati di non fare questo in produzione!)
            return $next($request);
        }

        // 1. Recupera gli header di sicurezza richiesti dallo standard UCP
        $signature = $request->header('X-UCP-Signature');
        $timestamp = $request->header('X-UCP-Timestamp');

        if(!$signature || !$timestamp) {
            return response()->json(['error' => 'UNAUTHORIZED', 'message' => 'Missing required UCP signature headers.'], 401);
        }

        // 2. Prevenzione Replay Attack: Verifica che la richiesta non sia più vecchia di 5 minuti (300 secondi)
        if(abs(time() - (int)$timestamp) > 300) {
            return response()->json(['error' => 'REQUEST_EXPIRED', 'message' => 'Request timestamp is too old.'], 401);
        }

        // 3. Payload di verifica (solitamente Timestamp + Body grezzo della richiesta)
        $rawBody = $request->getContent();
        $payload = $timestamp . '.' . $rawBody;

        // 4. Chiave pubblica ufficiale del provider AI - chiave pubblica fornita dal circuito partner (es. Google Merchant Console nella sezione Sviluppatori UCP):
        $publicKeyPem = config('services.ucp.public_key');
        if (!$publicKeyPem) {
            Log::error('Chiave pubblica UCP non configurata nel file .env o nel config.');
            return response()->json(['error' => 'INTERNAL_SERVER_ERROR'], 500);
        }

        // 5. Verifica della firma digitale utilizzando OpenSSL
        // Nota: Il protocollo definisce l'algoritmo (es. SHA256). Adatta OPENSSL_ALGO_SHA256 se necessario.
        $decodedSignature = base64_decode($signature);
        $isVerified = openssl_verify($payload, $decodedSignature, $publicKeyPem, OPENSSL_ALGO_SHA256);

        if ($isVerified !== 1) {
            Log::warning('Tentativo di richiesta UCP con firma non valida da IP: ' . $request->ip());
            return response()->json(['error' => 'INVALID_SIGNATURE', 'message' => 'Invalid UCP signature.'], 401);
        }

        return $next($request);
    }
}
