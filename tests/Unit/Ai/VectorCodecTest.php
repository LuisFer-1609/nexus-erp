<?php

use App\Services\Ai\Documents\Chunker;
use App\Services\Ai\Documents\RowDocument;
use App\Services\Ai\LocalEmbeddingClient;
use App\Services\Ai\VectorCodec;

test('un vector se empaqueta y se recupera sin perder precisión útil', function () {
    $vector = [0.25, -0.5, 0.125, 0.0, 1.5];

    $restored = VectorCodec::unpack(VectorCodec::pack($vector));

    expect($restored)->toHaveCount(5);
    foreach ($vector as $i => $value) {
        expect($restored[$i])->toEqualWithDelta($value, 1e-6);
    }
});

test('normalizar deja el vector con longitud 1 y el producto punto es la similitud coseno', function () {
    $a = VectorCodec::normalize([3.0, 4.0]);

    expect(VectorCodec::dot($a, $a))->toEqualWithDelta(1.0, 1e-9);
    expect(VectorCodec::dot($a, VectorCodec::normalize([-4.0, 3.0])))->toEqualWithDelta(0.0, 1e-9);
});

test('el cliente local da mayor similitud a textos relacionados que a los distintos', function () {
    $client = new LocalEmbeddingClient(256);

    $query = $client->embedQuery('leads con prioridad alta');
    [$related, $unrelated] = $client->embedDocuments([
        'lead de prioridad alta en etapa nuevo',
        'asistencia del empleado con retardo de diecisiete minutos',
    ]);

    expect(VectorCodec::dot($query, $related))->toBeGreaterThan(VectorCodec::dot($query, $unrelated));
    expect(VectorCodec::dot($related, $related))->toEqualWithDelta(1.0, 1e-6);
});

test('un documento corto queda en un solo fragmento con su encabezado', function () {
    $chunks = (new Chunker(1200, 120))->split(new RowDocument('[leads #1]', ['full_name: Ana', 'priority: alta']));

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0])->toStartWith('[leads #1]')
        ->and($chunks[0])->toContain('full_name: Ana');
});

test('un texto muy largo se divide en varios fragmentos que respetan el límite y repiten el encabezado', function () {
    $long = 'notes: '.implode(' ', array_fill(0, 400, 'palabra'));
    $chunker = new Chunker(300, 40);

    $chunks = $chunker->split(new RowDocument('[tasks #9]', ['title: Revisar contrato', $long]));

    expect(count($chunks))->toBeGreaterThan(3);
    foreach ($chunks as $chunk) {
        expect(mb_strlen($chunk))->toBeLessThanOrEqual(300)
            ->and($chunk)->toStartWith('[tasks #9]');
    }
    expect($chunks[0])->toContain('title: Revisar contrato');
});
