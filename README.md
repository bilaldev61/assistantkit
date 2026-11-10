# AssistantKit

**A rules-first chat assistant for sites that have data.** Your catalogue answers first, an
optional language model handles only what the rules cannot, and every dead end asks for a
name and an email so no lead is lost.

- Plain PHP 8.2+, no framework, no database, no API key needed to try it.
- The engine is ~2,000 lines you read once and own.
- One copy per site. It does not call home and it does not host anything.

```
visitor message
   ├── 1. capture      an email in the message becomes a lead            (no model)
   ├── 2. cap          after N messages, hand over to a human            (no model)
   ├── 3. quick rules  contact, hours, facts about the item in view      (no model)
   ├── 4. search rules "3 bed under 2m in Marina" — from your catalogue  (no model)
   ├── 5. model        optional; gets a small, validated tool menu only
   └── 6. fallback     files the question for your team, asks for email
```

Each layer is cheaper than the next and the first that can answer ends the turn. That is
the whole idea: the expensive thing runs only when nothing else could.

---

## Try it in 60 seconds

```sh
git clone https://github.com/bilaldev61/assistantkit.git
cd assistantkit/demo/widget
php -S localhost:8099 server.php
```

Open <http://localhost:8099/> and talk to the bubble. No key, no database, nothing installed.

| Say | You get |
|---|---|
| "what is available in Dubai Marina?" | a search answer from the catalogue |
| open `?item=marina-2br`, then "how much is it?" | the recorded price |
| "is the price negotiable?" | a deferral — it will not invent a discount |
| "how does renting work?" | your process steps from config |
| "Ahmed Khan, ahmed@example.com" | a lead, with the question attached |
| "can you hold it for my cousin?" | filed for the team, fallback reply |

Prefer a terminal?

```sh
php bin/demo.php                                    # scripted conversation, prints every kind
php bin/rules.php lint                              # is this pack sane?
php bin/rules.php ask "how much is it?" --item=marina-2br
php bin/rules.php cases                             # 22 expected answers, replayed
php /path/to/any/vendor/bin/phpunit -c phpunit.xml  # the suite: 46 tests, no setup
```

Starting a brand-new site with no rules yet? Draft them instead of facing a blank page:

```sh
php bin/rules-draft.php inventory rules/inventory.example.json   # your columns -> a rules table
php bin/rules-draft.php questions rules/questions.example.txt    # the questions asked -> what to add
```

---

## The five things a site supplies

| # | In plain words | Where |
|---|---|---|
| 1 | **Shelf** — your items | `Catalogue`: `search`, `find`, `facts` |
| 2 | **Notepad** — the chat | `ConversationStore`: `load`, `save`, `recordQuestion`, `questions` |
| 3 | **Inbox** — where leads land | `LeadSink`: `capture` |
| 4 | **Phone line** — the model | `LanguageModel`, or `null` for rules-only |
| 5 | **Dictionary** — the words | `config/assistant.php` + `packs/<niche>.php` |

The assistant is only as specific as #1 and #5. `facts()` must return `null` for anything
unknown — never a guess, because a confident wrong price is the one bug that costs money.

**How to write #5:** [`RULES.md`](RULES.md) — the key-by-key pack guide, a 30-minute
worksheet and the `kind` → fix table.

---

## Tools: 4 active, 6 ready to copy

Four tools are **registered and working in the repo today**, because each one sits on a
port you already implement:

```
search_items  ·  get_item  ·  save_lead  ·  record_unanswered_question
```

A site's pack decides which of those it may use (`'tools' => [...]`), so the property demo
site runs exactly those four.

Six more are **templates, not active** — they sit on *your* tables, so the Kit cannot guess
their shape. `examples/tools/`:

| Template | Kind | Sign-in | Answers |
|---|---|---|---|
| `OrderStatusTool` | read | yes | "where is my order?" |
| `CheckStockTool` | read | no | "have you got a 54cm?" |
| `DeliveryEtaTool` | read | no | "how long will delivery take?" |
| `BookAppointmentTool` | write | no | "can I view it Saturday?" |
| `RequestQuoteTool` | write | no | "how much for a custom build?" |
| `CallbackRequestTool` | write | no | "can someone call me?" |

A copied template stays inert until **three locks** agree: the pack lists the name, the
site config keeps it, and a handler is registered in `ToolRegistry`. Copy, register,
declare — three lines. Details: [`TOOLS.md`](TOOLS.md).

```php
$assistant = new Assistant(
    $catalogue, $store, $leads, $pack, $config, $model,
    new ToolRegistry($stock->tool()),   // ← the only new line
);
```

---

## Read in this order

| If you want to… | Read |
|---|---|
| see it work | this page, then `demo/widget/` |
| write your rules and words | [`RULES.md`](RULES.md) |
| start rules for a brand-new site | [`RULES.md`](RULES.md) §6 — `bin/rules-draft.php` |
| check and prove your rules | [`RULES.md`](RULES.md) §6 — `bin/rules.php`, `bin/rules-build.php` |
| wire it into a real app | [`INTEGRATION.md`](INTEGRATION.md) |
| add order tracking, stock, bookings | [`TOOLS.md`](TOOLS.md) + `examples/tools/` |
| change the industry | copy `packs/property.php` or `packs/_template.php` |

---

## Wiring it in, briefly

```php
use AssistantKit\Assistant;

$assistant = Assistant::fromConfig(
    root: __DIR__,
    catalogue: new ProductCatalogue(),
    store: new ChatStore($chat),
    leads: new LeadSink($chat),
    model: new OpenRouterModel($key),   // or null
);

$reply = $assistant->reply((string) $chat->id, $message, $itemSlug);

// $reply->text  ← show this
// $reply->kind  ← 'lead', 'rule:fact_price', 'rule:search', 'ai', 'fallback', ...
```

Store `kind` with every message: it tells you how your site is really being answered, and
which questions deserve a better rule.

The engine does **not** validate input, rate-limit or render. Cap the message length, limit
per IP, verify the item slug exists, and never let a visitor string reach HTML. Checklist:
[`INTEGRATION.md`](INTEGRATION.md).

---

## Project map

```
src/                 the engine — no framework, no dependencies
  Assistant.php        capture → cap → quick rules → search rules → model → fallback
  Contracts/           Catalogue, ConversationStore, LeadSink, LanguageModel
  Rules/               QuickAnswers (regex), RulesSearch (catalogue queries)
  Capture/             ContactCapture (name / email / phone from a sentence)
  Tools/               Tool, ToolRegistry, ToolBox (the four built-ins)
  Pack/ Prompt/ Value/ Support/
  Demo/                fakes: FakeCatalogue, MemoryStore, MemoryLeadSink, ScriptedModel
packs/               property.php (real), bikes.php (generated), _template.php
config/              brand, contacts, hours, limits, asks, which pack, tool narrowing
bin/demo.php         scripted conversation in the terminal
bin/rules.php        lint a pack · trace one question · replay the cases
bin/rules-build.php  words in, a pack out (rules/<niche>.rules.php → packs/)
bin/rules-draft.php  a new site in: draft a rules table from your columns or your question log
tools/               RulesInspector, CaseRunner, RulesCompiler, RulesDrafter — the rule tooling
rules/               bikes.rules.php (builder input), property.cases.php (expectations),
                     inventory.example.json + questions.example.txt (drafter samples)
demo/widget/         the runnable widget over fake data (start here)
examples/tools/      six tool templates
tests/               the suite — no database, no network
bootstrap.php        PSR-4 autoloader, so it runs without composer install
README.md            this page
RULES.md             how to build the rules and the words
INTEGRATION.md       the four ports in code, step by step
TOOLS.md             per-site tool menu: allowlist, narrowing, sign-in
LICENSE              MIT
```

---

## What this is not

- **No crawler, no vector store, no RAG.** Answers come from your catalogue and your config.
  At a few hundred items that is faster, cheaper and far easier to keep true.
- **No UI product.** `demo/widget` exists so the engine can be *seen*. Copy it as a start.
- **No admin screens, rate limits or spend caps.** Those belong to the server holding your
  API key.
- **No multi-tenant hosting.** One copy per site, embedded.

---

## Licence

MIT — see [LICENSE](LICENSE). Ship it **bring your own API key**, otherwise you pay for
strangers' traffic.
