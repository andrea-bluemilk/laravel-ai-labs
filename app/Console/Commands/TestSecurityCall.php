<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\SecurityGuard;
use App\Models\Checkin;
use App\Jobs\MakeSecurityCheckCallJob;

#[Signature('app:test-security-call')]
#[Description('Command description')]
class TestSecurityCall extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        MakeSecurityCheckCallJob::dispatch();
        return;
        $guard = SecurityGuard::where('is_active', true)->first();

        $checkin = Checkin::create([
                'security_guard_id' => $guard->id,
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
                    'security_guard_id' => $guard->id,
                ]
            ]);

            $this->info("Chiamata di controllo di sicurezza effettuata per la guardia {$guard->name} ({$guard->phone_number}). Risposta VAPI: " . $response->body());
            Log::info("Chiamata di controllo di sicurezza effettuata per la guardia {$guard->name} ({$guard->phone_number}). Risposta VAPI: " . $response->body());
    }
}
