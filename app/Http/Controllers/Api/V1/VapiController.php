<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use App\Models\Checkin;
use Illuminate\Http\Request;

class VapiController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $payload = $request->all();
        Log::info('Received webhook payload: ', $payload);
        if($payload['message']['type'] === 'end-of-call-report') {
            $checkinId = $payload['message']['customer']['metadata']['checkin_id'] ?? null;
            if(!$checkinId) {
                return response()->json(['error' => 'Checkin ID not found in metadata'], 400);
            }
            $checkin = Checkin::findOrFail($checkinId);

            $transcript = $payload['message']['transcript'] ?? '';
            $endedReason = $payload['message']['endedReason'] ?? '';

            if($endedReason === 'customer-did-not-answer' || $endedReason === 'voicemail') {
                $checkin->status = \App\Enums\CheckinStatus::COMPLETED_NO_ANSWER;
                $checkin->save();
                $this->triggerAlert($checkin, 'Mancata risposta / Segreteria');
            } else {
                $status = $request->input('message.analysis.structuredData.checkin_status');
                if ($status == true) {
                    $checkin->status = \App\Enums\CheckinStatus::COMPLETED_OK;
                    $checkin->response_text = $transcript;
                    $checkin->save();
                } else {
                    $checkin->status = \App\Enums\CheckinStatus::CALLED_ALERT;
                    $checkin->response_text = $transcript;
                    $checkin->save();
                    $this->triggerAlert($checkin, 'Risposta anomala o richiesta aiuto');
                }
            }

            return response()->json(['status' => 'success'], 200);

        }
    }

    private function triggerAlert(Checkin $checkin, string $reason)
    {
        // Log the alert for now. You can replace this with actual alerting logic (e.g., sending an email or notification).
        Log::alert("Controllo di sicurezza per la guardia {$checkin->guard->name} delle ore {$checkin->called_at} fallito: {$reason}");
    }
}
