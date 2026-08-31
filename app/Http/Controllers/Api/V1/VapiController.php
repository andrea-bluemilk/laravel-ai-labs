<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CheckinStatus;
use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Mail\AlertMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VapiController extends Controller
{
    /**
     * Motivi per cui il cliente non ha mai preso la chiamata.
     */
    private const NO_ANSWER_REASONS = [
        'customer-did-not-answer',
        'customer-busy',
        'customer-did-not-give-microphone-permission',
        'voicemail',
    ];

    /**
     * Guasti della piattaforma: non dicono nulla sullo stato della guardia.
     *
     * Nota: `silence-timed-out` NON sta qui di proposito. Una guardia che risponde
     * e non riesce a parlare e' esattamente il caso che questo sistema deve
     * intercettare, quindi resta un alert.
     */
    private const TECHNICAL_FAILURE_PREFIXES = [
        'call.start.error',
        'call.in-progress.error',
        'pipeline-error',
        'assistant-error',
        'assistant-not-found',
        'db-error',
        'unknown-error',
        'vapifault',
        'phone-call-provider-bypass-enabled-but-no-call-received',
    ];

    public function handleWebhook(Request $request): JsonResponse
    {
        $message = $request->input('message');

        if (! is_array($message)) {
            Log::warning('Webhook Vapi senza chiave message.', ['payload' => $request->all()]);

            return response()->json(['status' => 'ignored'], 200);
        }

        Log::info('Webhook Vapi ricevuto.', ['type' => $message['type'] ?? null]);

        if (($message['type'] ?? null) !== 'end-of-call-report') {
            return response()->json(['status' => 'ignored'], 200);
        }

        $checkinId = $message['call']['metadata']['checkin_id'] ?? null;
        $checkin = $checkinId ? Checkin::find($checkinId) : null;

        // Rispondiamo comunque 200: un 4xx/5xx fa ritentare Vapi all'infinito
        // su un payload che non diventera' mai processabile.
        if (! $checkin) {
            Log::warning('Checkin non trovato per il report di fine chiamata Vapi.', [
                'checkin_id' => $checkinId,
                'call_id' => $message['call']['id'] ?? null,
            ]);

            return response()->json(['status' => 'ignored'], 200);
        }

        $endedReason = (string) ($message['endedReason'] ?? '');
        $transcript = (string) ($message['transcript'] ?? '');

        [$status, $alertReason] = $this->classify($endedReason, $message);

        $checkin->status = $status;
        $checkin->response_text = $transcript;
        $checkin->save();

        Log::info('Checkin aggiornato dal report di fine chiamata Vapi.', [
            'checkin_id' => $checkin->id,
            'call_id' => $message['call']['id'] ?? null,
            'ended_reason' => $endedReason,
            'status' => $status->value,
        ]);

        if ($alertReason !== null) {
            $this->triggerAlert($checkin, $alertReason);
        }

        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Determina l'esito del checkin e l'eventuale motivo di allarme.
     *
     * @param  array<string, mixed>  $message
     * @return array{0: CheckinStatus, 1: string|null}
     */
    private function classify(string $endedReason, array $message): array
    {
        if (in_array($endedReason, self::NO_ANSWER_REASONS, true)) {
            return [CheckinStatus::COMPLETED_NO_ANSWER, 'Mancata risposta / Segreteria'];
        }

        if (Str::startsWith($endedReason, self::TECHNICAL_FAILURE_PREFIXES)) {
            // Nessun alert alla centrale: la guardia non c'entra, la chiamata va rifatta.
            Log::error('Chiamata di controllo fallita per guasto tecnico.', [
                'checkin_id' => $message['call']['metadata']['checkin_id'] ?? null,
                'call_id' => $message['call']['id'] ?? null,
                'ended_reason' => $endedReason,
            ]);

            return [CheckinStatus::FAILED_TECHNICAL, null];
        }

        // Vapi salta la structured output quando la chiamata non e' mai diventata
        // una conversazione: un null qui non e' una risposta negativa.
        if ($this->structuredOutputWasSkipped($message, 'checkin_status')) {
            Log::error('Vapi ha saltato la structured output: esito non determinabile.', [
                'checkin_id' => $message['call']['metadata']['checkin_id'] ?? null,
                'call_id' => $message['call']['id'] ?? null,
                'ended_reason' => $endedReason,
            ]);

            return [CheckinStatus::FAILED_TECHNICAL, null];
        }

        if ($this->extractStructuredOutput($message, 'checkin_status') === true) {
            return [CheckinStatus::COMPLETED_OK, null];
        }

        return [CheckinStatus::CALLED_ALERT, "Risposta anomala o richiesta aiuto (endedReason: {$endedReason})"];
    }

    /**
     * Vapi indicizza le structured output con un UUID casuale, quindi si cercano per nome.
     *
     * @param  array<string, mixed>  $message
     */
    private function extractStructuredOutput(array $message, string $name): mixed
    {
        foreach ($message['artifact']['structuredOutputs'] ?? [] as $output) {
            if (($output['name'] ?? null) === $name) {
                return $output['result'] ?? null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function structuredOutputWasSkipped(array $message, string $name): bool
    {
        foreach ($message['artifact']['skippedStructuredOutputs'] ?? [] as $skipped) {
            if (($skipped['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }

    private function triggerAlert(Checkin $checkin, string $reason): void
    {
        Log::alert("Controllo di sicurezza per la guardia {$checkin->security_guard?->name} delle ore {$checkin->called_at} fallito: {$reason}");
        Mail::to('nicola.nugnes@mondialpol.it')->send(new AlertMail($checkin, $reason)); 
    }
}
