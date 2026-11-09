# AssistantKit

**A rules-first chat assistant for sites that have data.** Your own data answers first, an
optional language model handles only what the rules cannot, and every dead end asks for a
name and an email so no lead is lost.

No framework required. No database required. No API key required to try it.

```
visitor message
   ├── 1. capture      an email in the message becomes a lead            (no model)
   ├── 2. cap          after N visitor messages, hand over               (no model)
   ├── 3. quick rules  contact, hours, facts about the item in view      (no model)
   ├── 4. search rules "3 bed under 2m in Marina" — from your catalogue  (no model)
   ├── 5. model        optional; gets a small validated tool menu only
   └── 6. fallback     files the question for your team, asks for email
```

Each layer is cheaper than the next, and the first that can answer ends the turn. That is
the whole idea: the expensive thing runs only when nothing else could.

---

## Requirements

- PHP **8.2+** (tested on 8.5) with the standard `json`, `mbstring` and `ctype` — nothing else.
- Optional: Composer, only if you want the test suite through `composer install`.
- Optional: an API key for a language model. Without one it runs rules-only, which is a
  fully supported mode, not a degraded one.

---

## See it working in 60 seconds

```sh
git clone https://github.com/bilaldev61/assistantkit.git
cd assistantkit/demo/widget
php -S localhost:8099 server.php
```

Open <http://localhost:8099/> — a floating widget talks to a real `Assistant` over a fake
catalogue and a file store. Try:

| Say | You get |
|---|---|
| "what is available in Dubai Marina?" | a search answer, no model |
| open `?item=marina-2br`, then "how much is it?" | the recorded price, no model |
| "is the price negotiable?" | a deferral — it will not invent a discount |
| "how does renting work?" | the process steps from config |
| "Ahmed Khan, ahmed@example.com" | a lead, saved with the question attached |
| "can you hold it for my cousin?" | filed for the team + a fallback reply |

Everything above runs with **no API key and no database**. `demo/widget/README.md`
explains the endpoint; `demo/widget/server.php` is 50 lines and shows the whole wiring.

Want the CLI version instead?

```sh
php bin/demo.php                                   # a scripted conversation, rules only
php /path/to/any/vendor/bin/phpunit -c phpunit.xml # the test suite (31 tests)
composer test                                      # if you ran composer install first
```

The suite needs no `composer install` of its own: `bootstrap.php` registers a PSR-4
autoloader, so any `phpunit` binary will do.

---

## Use it in your project

**Step 1 — copy the engine.** Either copy `src/` into your app and map `AssistantKit\` to it
(one PSR-4 line), or point Composer at this repository:

```json
{
    "repositories": [{ "type": "vcs", "url": "https://github.com/bilaldev61/assistantkit.git" }],
    "require": { "assistant-kit/assistant": "dev-main" }
}
```

`src/` has zero dependencies and zero framework imports.

**Step 2 — implement four ports.** These are the only things the engine asks of you:

| Port | Method(s) | In plain words | Typical size |
|---|---|---|---|
| `Catalogue` | `search`, `find`, `facts` | "what have you got that matches, and what is true about this one" | 40–80 lines |
| `ConversationStore` | `load`, `save`, `recordQuestion`, `questions` | "remember the chat; keep the questions you could not answer" | 40–80 lines |
| `LeadSink` | `capture` | "save this person's details and the question they asked" | 15 lines |
| `LanguageModel` | `isAvailable`, `converse` | "the optional provider call" — pass `null` to skip the model entirely | 40 lines or `null` |

The rules are only as good as `facts()`. **Return `null` for anything unknown** — never a
guess. A confident wrong price is the one bug that costs real money.

**Step 3 — write your words.**

- `config/assistant.php` — brand, contacts, hours, process steps, limits, `'pack' => 'yours'`
- `packs/yours.php` — nouns, types, areas, aliases, facts, process, agency, intents, `tools`

Copy `packs/_template.php` and fill it in. Roughly 70% of the work on a new site is the
pack; the adapter is the easy half.

**Step 4 — build it per turn and use the reply.**

```php
use AssistantKit\Assistant;

$assistant = Assistant::fromConfig(
    root: __DIR__,
    catalogue: new ProductCatalogue(),   // your four ports
    store: new ChatStore($chat),
    leads: new LeadSink($chat),
    model: new OpenRouterModel($key),    // or null
);

$reply = $assistant->reply((string) $chat->id, $message, $itemSlug);

// $reply->text  ← what to show
// $reply->kind  ← 'lead', 'rule:fact_price', 'rule:search', 'ai', 'fallback', ...
```

Store `kind` with every message. It tells you how your site is really being answered, and
which questions deserve a better rule.

**Step 5 — guard the endpoint yourself.** The engine does not validate input, rate-limit or
render. In a web app: cap the message length, limit per IP, verify the item slug exists,
and never let a visitor-controlled string reach HTML.

Full walkthrough, gotchas and a checklist: [`INTEGRATION.md`](INTEGRATION.md).

---

## Tools: how a site adds actions

The engine ships **four built-ins**, each sitting on one of the ports above, so they work
on any site:

```
search_items  ·  get_item  ·  save_lead  ·  record_unanswered_question
```

Anything else — order status, stock counts, bookings, quotes — depends on *your* tables,
so it is a **template you copy**, not a built-in the Kit pretends to support.
`examples/tools/` has six, tested and ready:

| Template | Kind | Needs sign-in | Answers |
|---|---|---|---|
| `OrderStatusTool` | read | yes | "where is my order?" |
| `CheckStockTool` | read | no | "have you got a 54cm?" |
| `DeliveryEtaTool` | read | no | "how long will delivery take?" |
| `BookAppointmentTool` | write | no | "can I view it Saturday?" |
| `RequestQuoteTool` | write | no | "how much for a custom build?" |
| `CallbackRequestTool` | write | no | "can someone call me?" |

Adding one takes three lines in your own code — a `Tool`, a `ToolRegistry`, and the name in
that site's pack:

```php
$assistant = new Assistant(
    $catalogue, $store, $leads, $pack, $config, $model,
    new ToolRegistry($stock->tool()),                  // the handler
);
```

```php
// packs/yours.php
'tools' => ['search_items', 'get_item', 'save_lead', 'check_stock'],
```

A tool is reachable only when **three locks** agree: the pack lists it, the site config
keeps it, and a handler is registered for it. Add a tool and a property site still has
four. Read [`TOOLS.md`](TOOLS.md) before adding one; keep a site at 4–8 tools.

---

## Project map

```
src/                 the engine (no framework, no dependencies)
  Assistant.php        capture → cap → quick rules → search rules → model → fallback
  Contracts/           Catalogue, ConversationStore, LeadSink, LanguageModel
  Rules/               QuickAnswers (regex), RulesSearch (catalogue queries)
  Capture/             ContactCapture (name/email/phone from a sentence)
  Tools/               Tool, ToolRegistry, ToolBox (the four built-ins)
  Pack/ Value/ Prompt/ Support/
  Demo/                FakeCatalogue, MemoryStore, MemoryLeadSink, ScriptedModel
packs/               property.php (real), _template.php (fill in your own)
config/              brand, contacts, limits, asks, pack name, tool narrowing
bin/demo.php         a scripted conversation in the terminal
demo/widget/         runnable floating widget over fake data (start here)
examples/tools/      six tool templates
tests/               the suite — no database, no network
bootstrap.php        PSR-4 autoloader, so it runs without composer install
INTEGRATION.md       step-by-step adoption guide
TOOLS.md             per-site tool menu: allowlist, narrowing, auth
```

---

## What this is not

- **No crawler, no vector store, no RAG.** Answers come from your catalogue and your
  config. At a few hundred items that is faster, cheaper, and far easier to keep true.
- **No UI product.** `demo/widget` exists so the engine can be *seen*, not to be your
  widget. Copy it as a starting point.
- **No admin screens, rate limits or spend caps.** Those belong to the server that owns
  your API key.
- **No multi-tenant hosting.** One copy per site, embedded.

---

## Adding an industry

Copy `packs/property.php` (or `packs/_template.php`), rename the nouns, types, areas,
aliases, facts and deferral topics, set `'pack' => 'yours'`. The engine does not change.
Same engine, different words: property, marketplace, bike shop, clinic, courses.

---

## Security, honestly

- Prices, stock and policies must come from `facts()` only — never from the prompt.
- Keep a site's tool menu small and omit anything the site cannot do.
- A tool that reads a person's record declares `requiresAuth` and is scoped by owner
  inside the handler; the engine knows *who* is present, never *which rows are theirs*.
- The model is a fallback, not the driver, so prompt injection can reach four validated
  tools at most.

---

## Licence

MIT — see [LICENSE](LICENSE). Ship it **bring your own API key**, otherwise you pay for
strangers' traffic.
