<?php

require __DIR__ . '/../bootstrap.php';

use AssistantKit\Assistant;
use AssistantKit\Demo\FakeCatalogue;
use AssistantKit\Demo\MemoryLeadSink;
use AssistantKit\Demo\MemoryStore;

$store = new MemoryStore();
$leads = new MemoryLeadSink();

// No LanguageModel: every answer below comes from rules and config alone.
$assistant = Assistant::fromConfig(dirname(__DIR__), new FakeCatalogue(), $store, $leads);

$turns = [
    ['hello', null],
    ['how does renting work', null],
    ['do you have a 2 bedroom apartment in dubai marina for sale', null],
    ['is it negotiable?', 'marina-2br'],
    ['how much is it?', 'marina-2br'],
    ['I am Sara, sara@example.com', 'marina-2br'],
    ['is it negotiable?', 'marina-2br'],
    ['what is your phone number', null],
    ['what nationality is the owner', null],
];

echo "AssistantKit demo - rules and config only, no model, no database.\n";

foreach ($turns as [$message, $item]) {
    $reply = $assistant->reply('demo-chat', $message, $item);

    echo "\n> " . $message . ($item !== null ? '   [item: ' . $item . ']' : '') . "\n";
    echo '  kind: ' . $reply->kind . "\n";

    foreach (explode("\n", $reply->text) as $line) {
        echo '  ' . $line . "\n";
    }
}

echo "\nCaptured leads: " . count($leads->leads) . "\n";

foreach ($leads->leads as $lead) {
    echo '  ' . ($lead['name'] ?? '-') . ' <' . $lead['email'] . '> item=' . ($lead['item'] ?? '-')
        . ' asked="' . ($lead['message'] ?? '-') . "\"\n";
}

echo "Questions filed for the team:\n";

foreach ($store->questions($store->load('demo-chat')) as $question) {
    echo '  - ' . $question['question'] . "\n";
}
