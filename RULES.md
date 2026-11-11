# Building the rules

The Kit is a rules engine that can call a model. **The rules are yours to write**, and they
are the only reason the assistant sounds like your business instead of a generic chatbot.

This page is how to write them, in the order you actually write them.

If you have not seen the thing run yet, do that first: `README.md` → 60 seconds.

---

## 1. The five things a site supplies

Two are data you write by hand, three are small classes you plug in.

| # | In plain words | Code | You write how much |
|---|---|---|---|
| 1 | **Shelf** — where your items live | `Catalogue` (`search`, `find`, `facts`) | 40–80 lines |
| 2 | **Notepad** — where a chat lives | `ConversationStore` (`load`, `save`, `recordQuestion`, `questions`) | 40–80 lines |
| 3 | **Inbox** — where a lead lands | `LeadSink` (`capture`) | 15 lines |
| 4 | **Phone line** — the optional model | `LanguageModel` (`isAvailable`, `converse`) or `null` | 40 lines or `null` |
| 5 | **Dictionary** — the words | `config/assistant.php` + `packs/<niche>.php` | **this page** |

The rules are #5 plus the sentences `facts()` returns inside #1. Everything else is plumbing.

---

## 2. The three places rules come from

| Where | Answers | Who reads it |
|---|---|---|
| `config/assistant.php` | what *you* say: brand, phone, hours, process steps, limits | the engine, verbatim |
| `packs/<niche>.php` | what *visitors* say: types, areas, aliases, question patterns | the regex layer |
| `Catalogue::facts($item)` | what is *true* about one item: "AED 2,100,000" | the fact layer |

That split is the whole design. The engine never invents a sentence: either it finds a
pattern in the pack and a fact from your data, or it says so and asks for an email.

---

## 3. The pack, key by key

`packs/_template.php` is a working skeleton. Copy it to `packs/yours.php` and fill it in.

| Key | Decides | Example |
|---|---|---|
| `name` | the pack's identity in config | `'property'` |
| `nouns` | how the assistant refers to your things | `one: 'property'`, `many: 'properties'` |
| `types` | words visitors type → your label | `'flat' => 'Apartment'` |
| `criteria` | which phrases become a **search filter** | `'beds' => '/\b(\d+)\s*(?:bed|bedrooms?)\b/i'` |
| `aliases` | shorthand → the real place name | `'jvc' => 'Jumeirah Village Circle'` |
| `areas` | places you actually serve (anything else is ignored) | `'Dubai Marina'` |
| `facts` | recognised questions, in priority order | see below |
| `process` | "how does it work" topics → config steps | `'renting' => '/\brent\|tenancy\b/i'` |
| `agency` | questions about you → brand fields in config | `'contact' => '/\bphone\|call\b/i'` |
| `intents` | `greeting`, `thanks`, `search`, `judgement` | short, narrow regexes |
| `tools` | the allowlist of actions this trade may use | `['search_items', 'get_item', 'save_lead', 'record_unanswered_question']` |

`judgement` is the intent the listing layer reads: a compare-or-recommend question stays
with the model even when it carries real filters, because the shelf can say *what
matches*, never *which is better*. A lone type word is treated the same way — "write me a
poem about an apartment" is not a request for every apartment on the shelf.

### The `facts` block is the heart of it

```php
'facts' => [
    'negotiable' => [                       // 1. narrower question first
        'match' => '/\b(negotiable|discount|best price)\b/i',
        'defer' => 'whether there is room on the price',   // no 'fact' key on purpose
    ],
    'price' => [                            // 2. then the broader one
        'match' => '/\b(price|cost|how much)\b/i',
        'fact'  => 'price',                 // key in Catalogue::facts()
        'defer' => 'the price',             // said when there is no value
    ],
];
```

- **Order matters.** The first pattern that matches wins, so narrow intents go above broad
  ones. "Is the price negotiable?" contains "price"; if `price` came first it would quote a
  figure and quietly promise a discount you never approved.
- **`fact` is optional.** With no `fact` key, the question is always deferred — that is how
  you say "we never quote this in chat".
- **`defer` is a noun phrase, not a sentence.** The engine writes "I do not want to guess
  about *whether there is room on the price*, so I will not…" and then asks for a name and
  email.
- **No value in `facts()` = deferral, not a guess.** `return null` / omit the key. A
  confident wrong price is the only bug that costs real money.

### When a deferral is skipped

A missing fact does **not** always produce a deferral. It is skipped when the message looks
like a search rather than a question about one item — no item open, and the message
contains a digit, matches the `search` intent, or names one of your `areas`. So "3 bed
under 2m in Marina" is answered by the search layer instead of "I do not want to guess
about the price".

---

## 4. Write your first pack in 30 minutes

**Step 1 — collect ten real questions.** Not invented ones. Pull them from WhatsApp,
email, the contact form, or just write down what you get asked on the phone. Ten is enough
to start; the deferral log (`questions()`) will hand you the next twenty. Facing a blank
page? `php bin/rules-draft.php` (§6) turns your data columns or a pasted question log into
a starting table so you are editing suggestions, not writing regex from nothing.

**Step 2 — put each question in one of four buckets.**

| Bucket | Where it goes | Example |
|---|---|---|
| About **one item** | `facts` + a value in `facts()` | "how much is it?" → `price` |
| About **finding** something | `criteria`, `types`, `areas`, `aliases` | "2 bed in Marina under 2m" |
| About **how you work** | `process` + the words in `config` | "how does renting work?" |
| About **you** | `agency` + `brand` in config | "what are your hours?" |

Anything left over is a genuine dead end: it belongs in the fallback, which files the
question for your team. Do not invent a rule for it yet.

**Step 3 — write the map.** A bike shop, filling the same four buckets:

| Visitor asks | Bucket | The line you write |
|---|---|---|
| "have you got a 54 in the Allez?" | item | `fact: 'stock'` — or a `check_stock` tool if stock moves hourly |
| "how much is the Allez?" | item | `fact: 'price'` |
| "do you do 0% finance?" | item/fees | `defer` — never quote a rate |
| "what size am I?" | how you work | `process: 'sizing'` + three steps in config |
| "can I book a fitting?" | action | `BookAppointmentTool` (see `examples/tools/`) |
| "where are you?" | you | `agency: 'office'` + `brand.office` |
| "how much for a custom build?" | none — it is configurable | `RequestQuoteTool`, never `price` |
| "do you deliver to Sharjah?" | item | `fact: 'delivery'` or a `delivery_eta` tool |

**Step 4 — copy the structure, not the words.**

```php
// packs/bikes.php
return [
    'name'  => 'bikes',
    'nouns' => ['one' => 'bike', 'many' => 'bikes', 'thing' => 'model'],
    'types' => ['road bike' => 'Road', 'mtb' => 'Mountain'],
    'criteria' => [
        'price' => [
            'min' => '/\b(?:from|over)\s*(?:aed\s*)?([\d.,]+)\s*(k)?/i',
            'max' => '/\b(?:under|below|up to)\s*(?:aed\s*)?([\d.,]+)\s*(k)?/i',
        ],
        'type' => '/\b(road bike|mtb|hybrid)\b/i',
        'area' => '/\b(in|at|near)\s+([a-z][a-z\s]{2,30})/i',
    ],
    'areas' => ['Dubai', 'Abu Dhabi'],
    'facts' => [
        'stock' => ['match' => '/\b(in stock|available|have you got)\b/i', 'fact' => 'stock', 'defer' => 'current stock'],
        'price' => ['match' => '/\b(price|how much|cost)\b/i', 'fact' => 'price', 'defer' => 'the price of this model'],
    ],
    'process' => ['sizing' => '/\b(size|sizing|fit)\b/i', 'servicing' => '/\b(service|servicing|tune)\b/i'],
    'agency' => ['contact' => '/\b(phone|call|whatsapp)\b/i', 'hours' => '/\b(hours|open)\b/i'],
    'intents' => [
        'greeting' => '/^(hi|hey|hello)\b/i',
        'thanks'   => '/\b(thanks|thank you)\b/i',
        'search'   => '/\b(looking for|do you have|show me)\b/i',
        'judgement'=> '/\b(better|best|recommend|which one)\b/i',
    ],
    'tools' => ['search_items', 'get_item', 'save_lead', 'record_unanswered_question'],
];
```

Then the words the engine says:

```php
// config/assistant.php
'brand' => ['name' => 'Your Shop', 'agent' => 'Nadia', 'phone' => '+971 …', 'hours' => '…'],
'process' => [
    'sizing' => [
        'title' => 'How we get your size right',
        'steps' => ['You tell us your height and inside leg.', 'We check the frame geometry and stock.', 'We book a fitting and confirm it in writing.'],
        'note'  => 'Any change of size is agreed before we order.',
    ],
],
'pack' => 'bikes',
```

**Step 5 — run it and read `kind`.**

```sh
php bin/demo.php
```

Every reply prints its `kind`. That one word tells you which layer answered:

| `kind` | Meaning | What to do |
|---|---|---|
| `rule:fact_price` | a recorded fact answered | nothing — this is the win |
| `rule:deferral_negotiable` | recognised, but no value | add the value to `facts()` if it is safe, else improve the deferral wording |
| `rule:search` | criteria became a filter | check the filter was the one you meant |
| `rule:search_none` | filter found nothing | too narrow, or a rule that should defer instead |
| `rule:process` | config steps were read | edit the words in config |
| `rule:agency_contact` | brand field quoted | edit `brand` |
| `rule:repeat` | the same sentence arrived twice | the visitor is stuck; the reply asks them to rephrase |
| `ai` | the model answered | a rule is missing — add it to the pack |
| `fallback` | nobody could answer | the question is now in `questions()`, your to-do list |

If a reply is wrong, fix the pack — not the engine. That is the loop: ask, read `kind`,
edit one line, ask again.

---

## 5. Mistakes that cost money

| Mistake | What happens | Fix |
|---|---|---|
| Broad `facts` entry above a narrow one | "is it negotiable?" quotes the price | narrow intents first |
| A number in a `process` step | it becomes a promise you cannot keep | amounts belong to the team |
| `facts()` returning a guess | a confident wrong price or stock count | return `null`, let it defer |
| `areas` containing everything | "at night" becomes an area | list only real places |
| One tool doing two things | the model picks the wrong one | one verb per tool |
| 15 tools on a small model | worse answers, more cost | 4–8 tools; put the rest in `process` |
| Pack copied but nobody edited it | polite, generic, useless | ten real questions first |
| Type words written in one number only | "apartments" drops the filter and the whole shelf is shown | put the plural inside the pattern: `(apartment|flat)s?` |
| A rule that demands correct spelling | "office hourss" costs a model call and still gets no answer | match the stem (`h[ou]*rs*`), not the dictionary word |
| A search rule that fires on any mention of a noun | "a poem about an apartment" is answered with listings | a judgement, or nothing to filter on, goes to the model instead |

---

## 6. The commands that draft, build and keep the rules honest

The Kit ships its own rule tooling. No extra install, no Node, no npm — it is PHP files in
`tools/` behind a few small scripts.

```sh
php bin/rules-draft.php inventory rules/inventory.example.json
                                                    # your columns -> a rules table
php bin/rules-draft.php questions rules/questions.example.txt --pack=property
                                                    # your question log -> what to add next
php bin/rules.php lint                              # is this pack sane?
php bin/rules.php ask "how much is it?" --item=marina-2br
                                                    # which rule answers, and what it says
php bin/rules.php cases                             # replay rules/<pack>.cases.php
php bin/rules-build.php rules/bikes.rules.php --out=packs/bikes.php
                                                    # words in, a pack out
```

**`lint`** reads the pack the way the engine reads it and catches the mistakes that are
invisible in the file:

| Finding | Why it matters |
|---|---|
| `facts.price.match is not a valid regex` | the rule silently never fires |
| `facts.x has neither fact nor defer` | it can only block the rules below it |
| `process.sizing has no steps in config` | the pattern can never answer |
| `intents.foo is never read by the engine` | a typo in the intent name |
| `alias 'JVC' is not lowercase` | messages are normalised, so it never matches |
| `tool 'check_stock' is not a built-in` | it stays hidden until you register a handler |

**`ask`** traces one sentence through the real engine: the `kind`, the first fact rule that
matched, and the reply. `facts negotiable (fact: none, defer: whether there is room on the
price)` means "recognised on purpose, and deferred on purpose".

**`cases`** replays `rules/property.cases.php` — 22 rows of `ask` → expected `kind`, with
optional `item`, `contains` and `missing`. Every bug you fix gets a row; the same file runs
inside the test suite (`tests/RulesToolingTest.php`), so a rule change that breaks "is it
negotiable?" fails the build instead of a customer's chat.

**`rules-build`** is the generator. You write `rules/bikes.rules.php`, which is just words:

```php
'facts' => [
    ['key' => 'negotiable', 'phrases' => ['negotiable', 'any discount', 'best price'], 'defer' => 'whether there is room on the price'],
    ['key' => 'price',      'phrases' => ['price', 'how much', 'cost'], 'fact' => 'price', 'defer' => 'the price of this model'],
],
```

and it writes the escaped, ordered regexes into `packs/bikes.php`. Two things it will not
do: it will not guess your words, and it will not build a table where a broader rule sits
above a narrower one —

```
Refusing to build: 1 ambiguous rule(s).
  - phrase "best price" is listed under fact "negotiable" but the pattern of fact "price",
    which sits above it, catches that phrase first
```

`packs/bikes.php` is committed as generated output, and a test fails if it drifts from its
table, so a hand edit that forgets to rebuild is caught. For an existing pack like
`property.php`, hand-editing is still fine — run `lint` and `cases` afterwards.

**`rules-draft` is the blank-page solver.** It writes a rules *table* (the words), never a
pack directly — you still run `rules-build` on it. Two feeds, both with no model and no
database:

- **`inventory <file.json>`** — describe the columns your site can actually answer. Each
  fact becomes a row; a row you mark with a `fact` key is wired to quote that value, a row
  you leave unmarked becomes an honest deferral. Generic `intents`, `agency` answers and a
  `price` search come baked in, so the very first build already greets and answers "what
  are your hours?".

  ```json
  { "name": "furniture",
    "facts": [
      { "key": "price",    "asks": ["how much", "price"], "fact": "price" },
      { "key": "delivery", "asks": ["do you deliver", "shipping"] }
    ] }
  ```
  → `price` quotes your data; `delivery` defers to the team until you add the column.

- **`questions <log> [--pack=name]`** — paste the unanswered-questions report
  (`ConversationStore::questions()`, one question per line, or JSON). It clusters wording
  by shared topic words, **reports the topics your pack already answers** (so you do not
  redraft them), and drafts the rest as deferrals. This is the loop in §4 turned into a
  command: the log tells you what to add, the drafter writes the starting row.

  ```sh
  php bin/rules-draft.php questions log.txt --pack=property --out=rules/next.rules.php
  ```

A draft is a *starting vocabulary, not a finished pack*: every drafted row defers until you
look at your data and wire a real `fact`. It never invents a value — the same rule as
`facts()` returning `null`.

**`kit-scaffold` is the same idea, for the code instead of the words.** A rules table only
answers if a `Catalogue` can feed it, and that class is the one port a tool cannot finish by
judgement alone. `php bin/kit-scaffold.php scaffold/<site>.json` reads a db spec (your table,
columns and which columns are safe to state) and writes a PDO `Catalogue` — marking every
column you did *not* flag as a comment, so `facts()` stays silent and the rule defers, exactly
like an unmarked draft row. The memory and leads ports need no drafting at all:
`AssistantKit\Pdo\PdoStore` and `PdoLeads` are ready to use. Full wiring:
[`INTEGRATION.md`](INTEGRATION.md) §1.

## 7. Where to go next

- [`INTEGRATION.md`](INTEGRATION.md) — the four ports, in code, with a checklist.
- [`TOOLS.md`](TOOLS.md) — per-site tool menus, allowlists, sign-in rules.
- [`examples/tools/`](examples/tools/) — six tested tools to copy.
- [`demo/widget/`](demo/widget/) — the whole thing in a browser, no database.
