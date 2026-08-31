<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Misura quanti frame audio in ingresso Vapi riceve davvero rispetto alla durata
 * reale della chiamata. Il registratore di Vapi e' pilotato dal clock dei frame in
 * arrivo, quindi il rapporto fra durata registrata e durata reale e' una misura
 * diretta della resa del media fork.
 *
 * Una resa sana e' >95%. Sotto il 50% il transport sta perdendo frame.
 */
#[Signature('vapi:frame-yield {--limit=10 : Quante chiamate recenti analizzare} {--min-duration=3 : Ignora le chiamate piu corte di N secondi} {--since= : Solo chiamate dopo questo istante, es. "2026-08-31 15:00" o "today"}')]
#[Description('Misura la resa dei frame audio in ingresso sulle chiamate Vapi recenti')]
class MeasureVapiFrameYield extends Command
{
    public function handle(): int
    {
        $token = config('services.vapi.api_key');

        if (! $token) {
            $this->error('services.vapi.api_key non configurata.');

            return self::FAILURE;
        }

        $http = Http::withToken($token)->timeout(30);
        $minDuration = (int) $this->option('min-duration');
        $since = $this->option('since') ? strtotime((string) $this->option('since')) : null;

        if ($this->option('since') && $since === false) {
            $this->error('Valore di --since non interpretabile.');

            return self::FAILURE;
        }

        $calls = $http->get('https://api.vapi.ai/call', ['limit' => (int) $this->option('limit')])->json();

        if (! is_array($calls)) {
            $this->error('Risposta inattesa da Vapi.');

            return self::FAILURE;
        }

        $rows = [];
        $yields = [];

        foreach ($calls as $call) {
            if (! isset($call['startedAt'], $call['endedAt'])) {
                continue;
            }

            if ($since !== null && strtotime((string) $call['createdAt']) < $since) {
                continue;
            }

            $duration = strtotime($call['endedAt']) - strtotime($call['startedAt']);

            if ($duration < $minDuration) {
                continue;
            }

            $recorded = $this->recordedSeconds($http, $call['id']);

            if ($recorded === null) {
                continue;
            }

            $yield = $duration > 0 ? ($recorded / $duration) * 100 : 0.0;
            $yields[] = $yield;

            $rows[] = [
                substr((string) $call['createdAt'], 0, 19),
                $duration.'s',
                number_format($recorded, 2).'s',
                sprintf('%.1f%%', $yield),
                $yield >= 95 ? 'ok' : ($yield >= 50 ? 'degradato' : 'ROTTO'),
                substr((string) ($call['endedReason'] ?? '-'), 0, 38),
            ];
        }

        if ($rows === []) {
            $this->warn('Nessuna chiamata da analizzare con questi filtri.');

            return self::SUCCESS;
        }

        $this->table(['chiamata (UTC)', 'reale', 'ricevuto', 'resa', 'stato', 'endedReason'], $rows);
        $this->newLine();
        $this->line(sprintf('Resa media: <options=bold>%.1f%%</> su %d chiamate.', array_sum($yields) / count($yields), count($yields)));

        return self::SUCCESS;
    }

    /**
     * Durata in secondi dell'audio effettivamente registrato, letta dall'header WAV.
     */
    private function recordedSeconds(PendingRequest $http, string $callId): ?float
    {
        $url = $http->get("https://api.vapi.ai/call/{$callId}")->json('artifact.presignedMonoUrl');

        if (! is_string($url)) {
            return null;
        }

        $wav = @file_get_contents($url);

        if ($wav === false || strlen($wav) < 44) {
            return null;
        }

        // Header WAV canonico: byte rate a offset 28, dimensione dati a offset 40.
        $byteRate = unpack('V', substr($wav, 28, 4))[1] ?? 0;
        $dataSize = unpack('V', substr($wav, 40, 4))[1] ?? 0;

        return $byteRate > 0 ? $dataSize / $byteRate : null;
    }
}
