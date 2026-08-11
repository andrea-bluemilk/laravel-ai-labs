<?php

namespace App\Jobs;

use App\Models\Guard;
use App\Models\Checkin;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class MakeSecurityCheckCallJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach(Guard::where('is_active', true)->get() as $guard) {
            $checkin = Checkin::create([
                'guard_id' => $guard->id,
                'status' => \App\Enums\CheckinStatus::CALLED_PENDING,
                'called_at' => now(),
            ]);

            $response = Http::withToken(config('services.vapi.api_key'))->post('https://api.vapi.ai/call/phone', [
                'phoneNumberId' => config('services.vapi.phone_number_id'),
                'customer' => [
                    'name' => $guard->name,
                    'number' => $guard->phone_number,
                ],
                'metadata' => [
                    'checkin_id' => $checkin->id,
                    'guard_id' => $guard->id,
                ],
                'firstMessage' => [
                    'text' => 'Chiamata di controllo di sicurezza. Per favore conferma che è tutto a posto dicendo "tutto bene" oppure premendo il tasto 1.',
                ],
                'assistant' => [
                    'model' => [
                        'provider' => 'openai',
                        'model' => 'gpt-4o-mini',
                    ],
                    'voice' => [
                        'provider' => 'elevenlabs',
                        'voiceId' => 'id_voce_italiana_naturale',
                    ],
                ]
            ]);
        }
    }
}
