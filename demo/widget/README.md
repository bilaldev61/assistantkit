# Reference widget

A floating bubble, a panel, and one endpoint — 120 lines of plain JS and 40 lines of PHP.
No framework, no build step, no database, no API key. It exists so the engine can be
*seen*, not as a product.

## Run it

```
cd demo/widget
php -S localhost:8099 server.php
```

Open <http://localhost:8099/>. The bubble is bottom-right. `?item=marina-2br` pretends
to be a listing page, which is what makes "how much is it?" answerable.

## Try these

| Message | What it proves |
|---|---|
| "What is available in Dubai Marina?" | search over the fake catalogue |
| "how much is it?" (on `?item=marina-2br`) | a recorded fact, no model |
| "Is the price negotiable?" | deferral instead of quoting a figure |
| "How does renting work?" | process answer from config |
| "Ahmed Khan, ahmed@example.com" | lead captured, question attached |
| "Can you hold it for my cousin?" | filed for the team, fallback text |

Leads append to `sys_get_temp_dir()/assistantkit-demo/leads.jsonl`; each chat is one JSON
file next to it. Delete that directory to start clean.

## Files

- `index.html` — a page with one `<script>` tag.
- `widget.js` — the widget. Styles itself, renders replies with `textContent`.
- `server.php` — the endpoint: builds `Assistant` with the demo doubles.
- `FileStore.php` — a chat store and a lead sink on the filesystem, so multi-turn works
  without a database.

## Adding a model

`server.php` passes `null` for the model, so everything you see is the free rule layer.
To let the model answer the leftovers, implement `LanguageModel::converse()` against your
provider and pass it as the sixth argument. Nothing else changes.

## What this is not

It has no auth, no rate limiting, no history UI, no retention, no styling options. Copy
it as a starting point, not as the widget you ship.
