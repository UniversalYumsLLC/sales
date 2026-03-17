<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;

$subdomain = config('fulfil.environments.sandbox.subdomain');
$token = config('fulfil.environments.sandbox.token');
$invoiceId = 7680643;

// Get full invoice state
$response = Http::timeout(30)->withHeaders([
    'Authorization' => 'Bearer ' . $token,
    'Content-Type' => 'application/json',
])->put("https://{$subdomain}.fulfil.io/api/v2/model/account.invoice/search_read", [
    'filters' => [['id', '=', $invoiceId]],
    'fields' => ['id', 'number', 'state', 'move', 'cancel_move', 'create_date', 'write_date'],
    'limit' => 1
]);

$invoice = $response->json()[0] ?? null;
echo "=== INVOICE ===\n";
echo json_encode($invoice, JSON_PRETTY_PRINT) . "\n\n";

// Get journal entry if exists
if ($invoice && $invoice['move']) {
    $jeResponse = Http::timeout(30)->withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
    ])->put("https://{$subdomain}.fulfil.io/api/v2/model/account.move/search_read", [
        'filters' => [['id', '=', $invoice['move']]],
        'fields' => ['id', 'number', 'state', 'origin', 'create_date', 'write_date'],
        'limit' => 1
    ]);
    echo "=== JOURNAL ENTRY ===\n";
    echo json_encode($jeResponse->json(), JSON_PRETTY_PRINT) . "\n";
} else {
    echo "=== NO JOURNAL ENTRY (move is null) ===\n";
}
