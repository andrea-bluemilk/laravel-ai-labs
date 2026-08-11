<?php

namespace App\Jobs;

use App\Models\SecurityGuard;
use App\Models\Checkin;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        foreach(SecurityGuard::where('is_active', true)->get() as $guard) {
            $checkin = Checkin::create([
                'guard_id' => $guard->id,
                'status' => \App\Enums\CheckinStatus::CALLED_PENDING,
                'called_at' => now(),
            ]);

            $response = Http::withToken(config('services.vapi.api_key'))->post('https://api.vapi.ai/call/phone', [
                'phoneNumberId' => config('services.vapi.phone_number_id'),
                'assistantId' => config('services.vapi.assistant_id'),
                'customer' => [
                    'name' => $guard->name,
                    'number' => $guard->phone_number,
                ],
                'metadata' => [
                    'checkin_id' => $checkin->id,
                    'guard_id' => $guard->id,
                ]
            ]);

            Log::info("Chiamata di controllo di sicurezza effettuata per la guardia {$guard->name} ({$guard->phone_number}). Risposta VAPI: " . $response->body());
        }
    }
}
