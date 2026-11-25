<?php

use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\GeminiKeyPool;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** @param list<string> $keys */
function useGeminiKeys(array $keys): void
{
    config()->set('nexus_ai.gemini.keys', $keys);
    config()->set('nexus_ai.driver', 'gemini');
    Cache::flush();
}

function geminiAnswer(string $text = 'ok'): array
{
    return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
}

test('los embeddings se piden con la llave del pool y el formato de la API', function () {
    useGeminiKeys(['key-a']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['embeddings' => [['values' => [0.1, 0.2]]]])]);

    $vectors = app(GeminiClient::class)->embed(['hola'], 'RETRIEVAL_DOCUMENT');

    expect($vectors)->toBe([[0.1, 0.2]]);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'models/gemini-embedding-001:batchEmbedContents')
        && $request->header('x-goog-api-key') === ['key-a']
        && $request['requests'][0]['taskType'] === 'RETRIEVAL_DOCUMENT'
        && $request['requests'][0]['model'] === 'models/gemini-embedding-001'
        && $request['requests'][0]['content']['parts'][0]['text'] === 'hola');
});

test('la generación usa el modelo ligero configurado, instrucciones de sistema y devuelve el texto', function () {
    useGeminiKeys(['key-a']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiAnswer('Hola, '.'mundo'))]);

    $text = app(GeminiClient::class)->generate('Eres Nexus', 'Pregunta');

    expect($text)->toBe('Hola, mundo');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'models/gemini-3.1-flash-lite:generateContent')
        && $request['systemInstruction']['parts'][0]['text'] === 'Eres Nexus'
        && $request['contents'][0]['role'] === 'user'
        && $request['generationConfig']['maxOutputTokens'] > 0);
});

test('con varias llaves el pool reparte las consultas al azar entre todas', function () {
    useGeminiKeys(['key-a', 'key-b', 'key-c']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiAnswer())]);

    $client = app(GeminiClient::class);
    for ($i = 0; $i < 60; $i++) {
        $client->generate('s', 'p');
    }

    $used = Http::recorded()->map(fn (array $pair): string => $pair[0]->header('x-goog-api-key')[0])->unique();

    expect($used)->toHaveCount(3);
});

test('si una llave llega a su cuota se usa otra y la agotada descansa', function () {
    useGeminiKeys(['bad', 'good']);
    Http::fake(fn (Request $request) => $request->header('x-goog-api-key') === ['bad']
        ? Http::response(['error' => ['message' => 'quota']], 429)
        : Http::response(geminiAnswer('respuesta')));

    $client = app(GeminiClient::class);
    for ($i = 0; $i < 6; $i++) {
        expect($client->generate('s', 'p'))->toBe('respuesta');
    }
});

test('una llave que recibe 429 queda apartada y no se vuelve a elegir hasta que descanse', function () {
    useGeminiKeys(['solo']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'quota']], 429)]);

    expect(fn () => app(GeminiClient::class)->generate('s', 'p'))->toThrow(AiUnavailableException::class);
    expect(app(GeminiKeyPool::class)->available())->toBe([]);

    Http::fake();
    expect(fn () => app(GeminiClient::class)->generate('s', 'p'))->toThrow(AiUnavailableException::class);
    Http::assertNothingSent();
});

test('una llave inválida se aparta por largo rato y se prueba con las demás', function () {
    useGeminiKeys(['invalida', 'valida']);
    Http::fake(fn (Request $request) => $request->header('x-goog-api-key') === ['invalida']
        ? Http::response(['error' => ['message' => 'API key not valid', 'details' => [['reason' => 'API_KEY_INVALID']]]], 400)
        : Http::response(geminiAnswer('bien')));

    expect(app(GeminiClient::class)->generate('s', 'p'))->toBe('bien');
});

test('sin llaves configuradas falla con un mensaje claro y no llama a Google', function () {
    useGeminiKeys([]);
    Http::fake();

    expect(fn () => app(GeminiClient::class)->generate('s', 'p'))
        ->toThrow(AiUnavailableException::class, 'GEMINI_API_KEYS');
    Http::assertNothingSent();
});

test('un error de solicitud (no de llave) no se reintenta con otras llaves', function () {
    useGeminiKeys(['key-a', 'key-b']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'contenido inválido']], 400)]);

    expect(fn () => app(GeminiClient::class)->generate('s', 'p'))->toThrow(AiUnavailableException::class, '400');
    Http::assertSentCount(1);
});
