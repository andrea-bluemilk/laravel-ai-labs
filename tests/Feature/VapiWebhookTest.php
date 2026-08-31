<?php

use App\Enums\CheckinStatus;
use App\Models\Checkin;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

/**
 * Costruisce un payload end-of-call-report nella forma che manda Vapi.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function endOfCallReport(Checkin $checkin, string $endedReason, array $overrides = []): array
{
    return ['message' => array_replace_recursive([
        'type' => 'end-of-call-report',
        'endedReason' => $endedReason,
        'transcript' => '',
        'call' => [
            'id' => '01a0578d-bd83-799a-8943-cbf0e8c46aed',
            'metadata' => ['checkin_id' => $checkin->id],
        ],
        'artifact' => ['structuredOutputs' => [], 'skippedStructuredOutputs' => []],
    ], $overrides)];
}

function postReport(array $payload): TestResponse
{
    return test()->postJson('/api/v1/vapi/call/response', $payload);
}

it('segna ok quando la structured output conferma', function () {
    $checkin = Checkin::factory()->create();

    postReport(endOfCallReport($checkin, 'assistant-ended-call', [
        'transcript' => "AI: Chiamata di controllo.\nUser: Tutto bene.\n",
        'artifact' => ['structuredOutputs' => [
            '6d4593a7-1f11-4f7d-822a-bc6dc23a6b75' => ['name' => 'checkin_status', 'result' => true],
        ]],
    ]))->assertOk();

    expect($checkin->refresh()->status)->toBe(CheckinStatus::COMPLETED_OK)
        ->and($checkin->response_text)->toContain('Tutto bene');
});

it('segna alert quando la structured output nega', function () {
    $checkin = Checkin::factory()->create();
    Log::spy();

    postReport(endOfCallReport($checkin, 'assistant-ended-call', [
        'artifact' => ['structuredOutputs' => [
            '6d4593a7' => ['name' => 'checkin_status', 'result' => false],
        ]],
    ]))->assertOk();

    expect($checkin->refresh()->status)->toBe(CheckinStatus::CALLED_ALERT);
    Log::shouldHaveReceived('alert')->once();
});

it('segna no answer e allarma', function (string $reason) {
    $checkin = Checkin::factory()->create();
    Log::spy();

    postReport(endOfCallReport($checkin, $reason))->assertOk();

    expect($checkin->refresh()->status)->toBe(CheckinStatus::COMPLETED_NO_ANSWER);
    Log::shouldHaveReceived('alert')->once();
})->with(['customer-did-not-answer', 'voicemail', 'customer-busy']);

it('non allarma per un guasto tecnico', function (string $reason) {
    $checkin = Checkin::factory()->create();
    Log::spy();

    postReport(endOfCallReport($checkin, $reason))->assertOk();

    expect($checkin->refresh()->status)->toBe(CheckinStatus::FAILED_TECHNICAL)
        ->and($checkin->status->isRetryable())->toBeTrue();
    Log::shouldNotHaveReceived('alert');
})->with([
    'call.in-progress.error-assistant-did-not-receive-customer-audio',
    'call.start.error-get-transport',
    'pipeline-error-openai-llm-failed',
    'call.in-progress.error-providerfault-outbound-sip-503-service-unavailable',
]);

it('non allarma quando vapi salta la structured output', function () {
    $checkin = Checkin::factory()->create();
    Log::spy();

    // Forma reale: Vapi salta l'output e indicizza per UUID.
    postReport(endOfCallReport($checkin, 'assistant-ended-call', [
        'artifact' => ['skippedStructuredOutputs' => [
            '6d4593a7-1f11-4f7d-822a-bc6dc23a6b75' => [
                'name' => 'checkin_status',
                'unmetCondition' => ['type' => 'endedReason'],
            ],
        ]],
    ]))->assertOk();

    expect($checkin->refresh()->status)->toBe(CheckinStatus::FAILED_TECHNICAL);
    Log::shouldNotHaveReceived('alert');
});

it('tratta il silenzio come alert, non come guasto', function () {
    $checkin = Checkin::factory()->create();
    Log::spy();

    postReport(endOfCallReport($checkin, 'silence-timed-out'))->assertOk();

    expect($checkin->refresh()->status)->toBe(CheckinStatus::CALLED_ALERT);
    Log::shouldHaveReceived('alert')->once();
});

it('risponde 200 su checkin inesistente per non far ritentare vapi', function () {
    $checkin = Checkin::factory()->create();
    $payload = endOfCallReport($checkin, 'assistant-ended-call');
    $payload['message']['call']['metadata']['checkin_id'] = 999999;

    postReport($payload)->assertOk()->assertJson(['status' => 'ignored']);

    expect($checkin->refresh()->status)->toBe(CheckinStatus::CALLED_PENDING);
});

it('ignora i tipi di messaggio diversi da end-of-call-report', function () {
    $checkin = Checkin::factory()->create();

    postReport(['message' => ['type' => 'speech-update', 'status' => 'started']])
        ->assertOk()->assertJson(['status' => 'ignored']);

    expect($checkin->refresh()->status)->toBe(CheckinStatus::CALLED_PENDING);
});

it('non esplode su payload senza message', function () {
    postReport(['foo' => 'bar'])->assertOk()->assertJson(['status' => 'ignored']);
});
