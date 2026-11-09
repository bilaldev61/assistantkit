<?php

/**
 * The demo endpoint. Run it from this directory:
 *
 *   cd demo/widget && php -S localhost:8099 server.php
 *
 * POST /chat  {"chat": "...", "message": "...", "item": "marina-2br"}
 * -> {"reply": "...", "kind": "rule:fact_price"}
 *
 * Everything else is served as a static file by the built-in server.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/FileStore.php';

use AssistantKit\Assistant;
use AssistantKit\Demo\FakeCatalogue;
use AssistantKit\Pack\RulesPack;

if (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) !== '/chat') {
    return false;
}

header('Content-Type: application/json; charset=utf-8');

$input = json_decode((string) file_get_contents('php://input'), true);
$input = is_array($input) ? $input : [];

$message = trim((string) ($input['message'] ?? ''));
$chat = preg_replace('/[^a-z0-9-]/i', '', (string) ($input['chat'] ?? '')) ?: 'guest';
$item = trim((string) ($input['item'] ?? '')) ?: null;

if ($message === '') {
    echo json_encode(['reply' => 'Ask me about the listings, the process, or how to reach the team.', 'kind' => 'demo']);
    return true;
}

$assistant = new Assistant(
    new FakeCatalogue(),
    new FileStore($chat),
    new FileLeadSink($chat),
    RulesPack::load(__DIR__ . '/../../packs/property.php'),
    require __DIR__ . '/../../config/assistant.php',
    null,   // no model: this demo shows the free rule layer, no key needed
);

$reply = $assistant->reply($chat, $message, $item);

echo json_encode(['reply' => $reply->text, 'kind' => $reply->kind], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

return true;
