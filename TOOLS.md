# Tools, and how a site switches one off

A tool is a call the model may make. Which tools exist **per site** is decided by that
site's pack; the engine never hands out a menu the site did not ask for.

There are exactly three locks, and a tool needs all three to be reachable.

## 1. The pack declares it (allowlist)

```php
// packs/property.php
'tools' => ['search_items', 'get_item', 'save_lead', 'record_unanswered_question'],
```

```php
// packs/bikes.php
'tools' => ['search_items', 'get_item', 'save_lead', 'check_stock'],
```

The property site's prompt is built from its own list, so `check_stock` is not in it:
the model cannot see it, cannot choose it, and cannot answer around it. This is an
allowlist, not a list of exceptions — a tool added to the engine later is **not**
inherited by packs written before it existed.

## 2. The site's config may narrow it, never widen it

```php
// config/assistant.php   (a Laravel site usually names it config/assistantkit.php)
'tools' => null,                                      // use the pack's list as written
'tools' => ['search_items', 'get_item', 'save_lead'], // trim it further
'tools' => [],                                        // no tools at all: rules only
```

The effective list is **pack ∩ config ∩ registered handlers**. Intersection, never
union: a site cannot switch on a tool its pack does not declare, and a stale pack cannot
smuggle a newer engine tool into a site that never asked for it.

## 3. Nobody can be offered a tool with no code behind it

Tools live in a `ToolRegistry`. The four built-ins are registered by the engine; anything
else is registered by the host site, and a name with no handler is dropped from the menu
instead of advertised.

```php
use AssistantKit\Tools\ToolRegistry;

$assistant = new Assistant(
    $catalogue, $store, $leads, $pack, $config, $model,
    new ToolRegistry($stock->tool(), $book->tool()),   // sites add tools here
);
```

Built-ins cannot be replaced: registering a tool named `save_lead` is ignored, so a site
cannot loosen a schema the engine wrote.

## The four built-ins, and why only four

`search_items`, `get_item`, `save_lead`, `record_unanswered_question` each sit on one of
the four ports (Catalogue, LeadSink, ConversationStore) that every adopter implements.
That is why they work on any site.

Order lookup, stock counts, bookings and delivery estimates sit on **your** tables, and no
two sites agree on their shape. Shipping those as built-ins would mean shipping stubs that
cannot answer. They are templates instead: copy, wire, declare.

## Tools about a person need a person

```php
new Tool(
    'order_status',
    'Look up the signed-in customer\'s own order.',
    ['type' => 'object', 'properties' => ['number' => ['type' => 'string']], 'required' => ['number']],
    fn(array $input, array $context) => $orders->lookup($input),
    requiresAuth: true,
);
```

With `requiresAuth: true` the tool is hidden from anonymous visitors and refused if called
anyway. The site reports who is present via config:

```php
'visitor' => ['signed_in' => auth()->check()],
```

Without this, "track my order" becomes an order-number guessing endpoint.

## The handler is yours; the guard is not

Inside a handler, always scope by owner as well as by the number the visitor typed. The
engine guarantees the tool only exists for a signed-in visitor — it cannot know which
orders are theirs.

## Ready-made templates

`examples/tools/` holds six, each a complete `Tool` with the lookup injected as a closure:

| Template | Kind | Auth | For |
|---|---|---|---|
| `OrderStatusTool` | read | yes | "where is my order?" |
| `CheckStockTool` | read | no | "have you got a 54cm?" |
| `DeliveryEtaTool` | read | no | "how long will delivery take?" |
| `BookAppointmentTool` | write | no | "can I view it Saturday?" |
| `RequestQuoteTool` | write | no | "how much for a custom build?" |
| `CallbackRequestTool` | write | no | "can someone call me?" |

Read `examples/tools/README.md` for the wiring, the per-trade menus and the rules they
encode. `tests/ToolTemplatesTest.php` runs all six, so they cannot rot.

## Menu budget

Keep a site at **4–8 tools**: every tool is re-sent with every message and the model has to
choose. More tools means more tokens, more wrong picks, and worse behaviour on small or
free models.

- One verb per tool (`check_stock`, not `check_stock_and_price`).
- Name it the way the visitor speaks.
- Explanations (returns, warranty, fees) belong in the pack's `process` text, not in a tool.
- Two similar tools is a bug: merge, or say the difference in both descriptions.

## Quick check

| Situation | Result |
|---|---|
| Pack omits it, registry has it | not offered, direct call refused |
| Pack lists it, no handler | not offered, direct call refused |
| Pack + handler, anonymous visitor, `requiresAuth` | not offered, direct call refused |
| Pack + handler + signed in | offered and runs |
| Site config lists a name the pack omits | not offered |
| Site registers its own `save_lead` | ignored, the built-in wins |
