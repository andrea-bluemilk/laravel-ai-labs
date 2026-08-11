<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use App\Models\ChatMessage;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Tool;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;

// Identificativo della chat corrente (per ora fisso) - potrebbe essere user_id, session_id, o qualsiasi altro identificatore univoco per la conversazione
const CHAT_SESSION_ID = 'chat-locale';

Route::get('/', function () {
    return view('welcome');
});

// ROUTES PER TEST UCP
Route::get('prodotto/{product}', [\App\Http\Controllers\CatalogueController::class, 'show'])->name('catalogue.show');

Route::get('/.well-known/ucp', function () {
    return response()->json([
        "spec_version" => "2026-01-23",
        "merchant_name" => config('app.name'),
        "capabilities" => [
            "shopping.checkout" => [
                "transport" => "REST",
                "url" => url('/api/v1/ucp/checkout')
            ]
        ],
        "payment_handlers" => [
            [
                "type" => "stripe_payment_element",
                "merchant_id" => config('payment.gateways.stripe.options.sandbox.public_key')
            ],
            [
                "type" => "paypal_wallet",
                "merchant_id" => config('payment.gateways.paypal.options.sandbox.client_id')
            ]
        ]
    ]);
});

Route::get('/ucp-feed/feed.xml', [\App\Http\Controllers\UcpFeedController::class, 'generateFeed']);















// ROUTES PER TEST AI AGENT
Route::get('/chat', function () {
    $rawHistory = ChatMessage::where('session_id', CHAT_SESSION_ID)
        ->orderBy('created_at', 'asc')
        ->get();

    $html = '<h1>Chat Locale con Qwen3 (e Memoria!)</h1>';
    $html .= '<div style="border:1px solid #ccc; padding:10px; margin-bottom:20px; max-width:600px; min-height:200px;">';
    if ($rawHistory->isEmpty()) {
        $html .= '<p style="color:#888;">Nessun messaggio. Inizia a chattare!</p>';
    } else {
        foreach ($rawHistory as $msg) {
            $ruolo = $msg->role === 'user' ? '<b>Tu:</b>' : '<b>AI:</b>';
            $html .= "<p>{$ruolo} {$msg->content}</p>";
        }
    }
    $html .= '</div>';

    $html .= '<form action="/chat/invia" method="POST">';
    $html .= csrf_field();
    $html .= '<input type="text" name="messaggio" placeholder="Scrivi qualcosa..." style="width:400px; padding:5px;" required autofocus>';
    $html .= '<button type="submit" style="padding:5px 10px; margin-left:5px;">Invia</button>';
    $html .= '</form>';

    $html .= '<br><a href="/chat/reset" style="color:red;">Svuota Memoria</a>';

    return $html;

});

Route::post('/chat/invia', function (Request $request) {
    $nuovoTestoUtente = $request->input('messaggio');
    ChatMessage::create([
        'session_id' => CHAT_SESSION_ID,
        'role' => 'user',
        'content' => $nuovoTestoUtente,
    ]);

    $rawHistory = ChatMessage::where('session_id', CHAT_SESSION_ID)
        ->orderBy('created_at', 'asc')
        ->get();


    // Generiamo la risposta passando TUTTA la cronologia a Qwen
    $prismMessages = [];
    foreach ($rawHistory as $msg) {
        if ($msg->role === 'user') {
            $prismMessages[] = new UserMessage($msg->content);
        } else {
            $prismMessages[] = new AssistantMessage($msg->content);
        }
    }
    $response = Prism::text()
        ->using(Provider::Ollama, 'qwen3:8b')
        ->withSystemPrompt('Sei un assistente amichevole. Ricorda le informazioni che l utente ti dà durante la conversazione.')
        ->withMessages($prismMessages)
        ->generate();

    ChatMessage::create([
        'session_id' => CHAT_SESSION_ID,
        'role' => 'assistant',
        'content' => $response->text,
    ]);

    Session::put('chat_history', $rawHistory);

    return redirect('/chat');
});

Route::get('/chat/reset', function () {
    ChatMessage::where('session_id', CHAT_SESSION_ID)->delete();
    return redirect('/chat');
});

Route::get('/ai-agent', function () {
    $tool = Tool::as('controlla_disponibilita_prodotto')
    ->for('Usa questo strumento per verificare quanti pezzi di un determinato prodotto sono rimasti in magazzino.')
    ->withStringParameter('prodotto', 'Il nome del prodotto da cercare (es. iphone, scarpe, maglietta)')
    ->using(function (string $prodotto) :string {
            $magazzino = [
                'iphone' => 5,
                'scarpe' => 0,
                'maglietta' => 20
            ];

            $prodottoPulito = strtolower($prodotto);
            if(array_key_exists($prodottoPulito, $magazzino)) {
                return "Il prodotto '{$prodotto}' ha una disponibilità di: " . $magazzino[$prodottoPulito] . " pezzi.";
            }

            return "Il prodotto '{$prodotto}' non esiste a sistema.";
        });


    $response = Prism::text()
    ->using(Provider::Ollama, 'qwen3:8b')
    ->withSystemPrompt('Sei un assistente del magazzino. Sii cordiale e usa gli strumenti a tua disposizione per rispondere alle domande.')
    ->withPrompt('Ciao! Un cliente mi chiede se abbiamo ancora delle scarpe in deposito e quante magliette ci sono.')
    ->withTools([$tool])
    ->withMaxSteps(3)
    ->generate();

    return response($response->text, 200);
});

Route::get('/ai-test', function () {
    $response = Prism::text()
    ->using(Provider::Ollama, 'qwen3:8b')
    ->withSystemPrompt('Sei un assistente che estrae dati. Rispondi SEMPRE e SOLO in formato JSON.')
    ->withPrompt('Estrai i prodotti e le quantità da questo testo: "Vorrei prenotare tre pizze margherita, due birre medie e una coca zero grazie!"')
    ->generate();

    return response($response->text, 200)
        ->header('Content-Type', 'application/json');
});
