<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CheckinStatus;
use App\Http\Controllers\Controller;
use App\Models\Checkin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VapiController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $payload = $request->all();
        Log::info('Received webhook payload: ', $payload['message']);
        if ($payload['message']['type'] === 'end-of-call-report') {
            $checkinId = $payload['message']['call']['metadata']['checkin_id'] ?? null;
            if (! $checkinId) {
                return response()->json(['error' => 'Checkin ID not found in metadata'], 400);
            }
            $checkin = Checkin::findOrFail($checkinId);

            $transcript = $payload['message']['transcript'] ?? '';
            $endedReason = $payload['message']['endedReason'] ?? '';

            if ($endedReason === 'customer-did-not-answer' || $endedReason === 'voicemail') {
                $checkin->status = CheckinStatus::COMPLETED_NO_ANSWER;
                $checkin->save();
                $this->triggerAlert($checkin, 'Mancata risposta / Segreteria');
            } else {
                $status = $this->extractStructuredOutput($payload, 'checkin_status');
                if ($status == true) {
                    $checkin->status = CheckinStatus::COMPLETED_OK;
                    $checkin->response_text = $transcript;
                    $checkin->save();
                } else {
                    $checkin->status = CheckinStatus::CALLED_ALERT;
                    $checkin->response_text = $transcript;
                    $checkin->save();
                    $this->triggerAlert($checkin, 'Risposta anomala o richiesta aiuto');
                }
            }

            return response()->json(['status' => 'success'], 200);

        }
    }

    /**
     * Vapi returns structured outputs keyed by a random UUID, so look them up by name.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractStructuredOutput(array $payload, string $name): mixed
    {
        $outputs = $payload['message']['artifact']['structuredOutputs'] ?? [];

        foreach ($outputs as $output) {
            if (($output['name'] ?? null) === $name) {
                return $output['result'] ?? null;
            }
        }

        return null;
    }

    private function triggerAlert(Checkin $checkin, string $reason)
    {
        // Log the alert for now. You can replace this with actual alerting logic (e.g., sending an email or notification).
        Log::alert("Controllo di sicurezza per la guardia {$checkin->security_guard?->name} delle ore {$checkin->called_at} fallito: {$reason}");
    }
}
