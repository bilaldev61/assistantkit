# Integrating AssistantKit into a site

> New here? [`README.md`](README.md) shows it running in 60 seconds; [`RULES.md`](RULES.md)
> is how the words and rules get written. This page is the wiring.

The Kit answers chat messages. It owns the *thinking*: what the message means, when to
defer, when to capture an email, which tool to call, what the prompt says. It owns none
of your data. You supply four small classes and one vocabulary file. Plain PHP 8.2+.

## 1. The four ports

### `Catalogue` — "here is my data, as arrays"

```php
search(array $criteria, int $limit): array;  // ['total' => int, 'items' => list<array>]
find(string $slug): ?array;
facts(array $item): array;                   // array<string,string> keyed like the pack
```

- `$criteria` keys: `area`, `beds`, `price_min`, `price_max`, `deal` (`rent`|`sale`), `type`.
  Ignore what you cannot apply.
- Each item carries `slug`, `title`, `url`, plus a `summary` you want quoted.
- `facts()` returns ready-to-say sentences. A missing key means "defer to the team",
  which is safer than guessing.

### `ConversationStore` — "here is where a chat lives"

```php
load(string $id, ?string $itemSlug = null): Conversation;
save(Conversation $conversation): void;
recordQuestion(Conversation $c, string $question, ?string $itemSlug = null): void;
questions(Conversation $c): array;            // [['question' => ..., 'item' => ...], ...]
```

`Conversation` carries `messages` (`role`, `content`, `answered_by`), `visitorMessages`,
`itemSlug`, `leadRef`. `questions()` is your unanswered-questions log — the most useful
report this system produces.

### `LeadSink` — "this is what happens when someone leaves an email"

```php
capture(array $lead): string;   // return your lead id; the Kit stores it on the chat
```

`$lead` = `name`, `email`, `phone`, `message`, `item`, `source`, `chat`.

### `LanguageModel` — "here is my model, or none"

```php
isAvailable(): bool;
converse(string $system, array $messages, array $tools, callable $runTool): string;
```

- Map the Kit's tool definitions to your provider's shape (OpenAI calls the field
  `parameters`; others use `inputSchema`).
- `$runTool($name, $input)` returns a value. Return `'Error: ...'` as **text**, never
  throw — the model must be able to correct itself.
- `isAvailable() === false` runs rules-only. That is a supported mode: no key, no cost,
  no latency.

## 2. The tools

The engine ships **four built-in tools**, and they are built-in because each one sits on a
port you already implement:

| Tool | Runs on | Writes |
|---|---|---|
| `search_items` | `Catalogue::search()` | no |
| `get_item` | `Catalogue::find()` + `facts()` | no |
| `save_lead` | `LeadSink::capture()` | yes |
| `record_unanswered_question` | `ConversationStore::recordQuestion()` | yes |

You cannot edit or loosen one from a site: a registration that reuses a built-in name is
ignored, so the engine's schema always wins, and every argument is re-validated before a
handler runs.

**Anything else you add yourself**, in your own code. `check_stock`, `order_status`,
`book_appointment` all depend on *your* tables, so the Kit cannot guess their shape. Copy a
template from `examples/tools/`, wire the lookup, register it, and declare it in the pack:

```php
use AssistantKit\Tools\ToolRegistry;

$assistant = new Assistant(
    $catalogue, $store, $leads, $pack, $config, $model,
    new ToolRegistry($stock->tool()),   // your own tools
);
```

```php
// packs/<your-niche>.php
'tools' => ['search_items', 'get_item', 'save_lead', 'check_stock'],
```

Three locks decide what the model can see, and all three must agree:

1. the **pack** lists the name (allowlist — nothing is inherited from a newer engine);
2. the **site config** does not trim it away (`'tools' => null | array`);
3. a **handler** is registered for it in `ToolRegistry` (a name with no code behind it is
   dropped, never advertised).

A site that does none of this keeps the four built-ins. That is how a property site stays
free of order lookups. Details, auth rules and the menu budget: [`TOOLS.md`](TOOLS.md).

## 3. The pack

One file per industry: `packs/<name>.php`. Copy `packs/_template.php`.

It holds nouns, item types, criteria patterns, area names and aliases, facts (question
pattern → fact key → sentence to say instead), process steps, agency intents, the
greeting/thanks patterns, and the `tools` allowlist.

- Put the *specific* intents first. "Is the price negotiable?" mentions the price;
  negotiation must be listed before `price` or it quotes a figure.
- Every fact needs a `defer` sentence. A deferral that asks for a name and email is a
  lead; a wrong answer is a liability.
- Never put a number in a process step. Amounts belong to the team.
- Omit a tool from `tools` and the model cannot see it — the allowlist is the whole
  switch.

## 4. Bootstrap

```php
$assistant = new Assistant(
    $catalogue,                     // Catalogue
    $store,                         // ConversationStore
    $leads,                         // LeadSink
    RulesPack::load(__DIR__ . '/packs/property.php'),
    require __DIR__ . '/config/assistant.php',
    $model,                         // LanguageModel or null
    $tools,                         // ToolRegistry or null
);

$reply = $assistant->reply($chatId, $visitorMessage, $itemSlug);
$reply->text;    // say this
$reply->kind;    // 'lead', 'ai', 'fallback', 'rule:fact_price', ...
```

Or, if your config and packs sit in one directory:

```php
$assistant = Assistant::fromConfig(
    __DIR__,                        // config/assistant.php + packs/{pack}.php
    $catalogue, $store, $leads, $model, $tools,
);
```

`config/assistant.php` holds brand, agent name, phone, limits, the pack to load, optional
tool narrowing (`'tools'`), and `'visitor' => ['signed_in' => ...]` for tools that need a
person. Everything visitor-visible is there or in the pack — never in the engine.

## 5. Checklist

- [ ] Copy `src/`, `config/`, `packs/`; register the `AssistantKit\` namespace.
- [ ] Pick a pack or copy `packs/_template.php` and write your niche's words.
- [ ] Implement `Catalogue`. Start with `facts()` returning `[]`.
- [ ] Implement `ConversationStore` — two tables, or your existing chat rows.
- [ ] Implement `LeadSink` — one insert is enough to start.
- [ ] Implement `LanguageModel`, or pass `null` and ship rules-only first.
- [ ] Put the real phone, email and hours in `config/assistant.php`.
- [ ] Decide the tool menu: the four built-ins, or add `examples/tools/` templates.
- [ ] Set `'visitor' => ['signed_in' => ...]` if any tool reads a person's own record.
- [ ] Ask ten questions your visitors actually ask; fix the pack, not the engine.
- [ ] Watch `questions()` — that list is next week's work.

## 6. What is not included

No chat widget, no admin screen, no rate limiting, no spend cap, no log retention.
Those are site features. The Kit will happily be called from anything — CLI, queue
worker, HTTP endpoint, or a test.
