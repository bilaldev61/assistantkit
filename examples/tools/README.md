# Tool templates

Six ready-made tools. They are **examples, not built-ins**: nothing here is registered
until a site copies the file, wires the lookup, and adds the name to its pack.

The Kit's own four (`search_items`, `get_item`, `save_lead`, `record_unanswered_question`)
are different: they sit on the four ports every site must implement, so they work anywhere.
These six sit on *your* tables, which is exactly why they must be copied rather than shipped.

| Template | Kind | Auth | Answers |
|---|---|---|---|
| `OrderStatusTool` | read | yes | "where is my order?" |
| `CheckStockTool` | read | no | "have you got a 54cm?" |
| `DeliveryEtaTool` | read | no | "how long to Dubai Marina, and how much?" |
| `BookAppointmentTool` | write | no | "can I view it Saturday morning?" |
| `RequestQuoteTool` | write | no | "how much for a custom build?" |
| `CallbackRequestTool` | write | no | "can someone call me?" |

## Using one

1. Copy the file into the site, e.g. `app/Assistant/Tools/CheckStockTool.php`.
2. Wire the lookup — every template takes a closure, so the class stays framework-free:

```php
$stock = new CheckStockTool(
    fn(string $model, string $size): array => [
        'units'   => Part::where('model', $model)->where('size', $size)->value('stock') ?? 0,
        'restock' => 'next week',
    ],
);
```

3. Register it where the Assistant is built:

```php
new Assistant($catalogue, $store, $leads, $pack, $config, $model,
    new ToolRegistry($stock->tool()));
```

4. Declare it in **that site's** pack:

```php
'tools' => ['search_items', 'get_item', 'save_lead', 'check_stock'],
```

Sites that skip steps 3 and 4 never see it. That is the whole point of the allowlist.

## Menu budget

Keep a site at **4–8 tools**. Every tool is re-sent with every message, and the more
similar names there are, the more often the model picks the wrong one — small and free
models first.

- One verb per tool. `check_stock`, not `check_stock_and_price`.
- Name it the way the visitor speaks: `order_status`, not `get_order_by_token`.
- Long-tail questions (returns policy, warranty terms) belong in the pack's `process`
  text or a deferral, **not** in a tool. Tools are for doing, not for explaining.
- Two similar tools is a bug: merge them, or make the distinction explicit in both
  descriptions.

## Which to install, by trade

| Site | Menu |
|---|---|
| Property / estate agency | the four built-ins + `BookAppointmentTool` |
| Bike shop | the four built-ins + `CheckStockTool` + `RequestQuoteTool` (custom builds) |
| Marketplace | the four built-ins + `OrderStatusTool` + `DeliveryEtaTool` |
| Service business | the four built-ins + `BookAppointmentTool` + `CallbackRequestTool` |

## The one rule that matters

A tool that answers with a number (price, stock, ETA) must return **nothing** when the
number is unknown, and let the reply become a deferral. A confident wrong "yes, in stock"
costs a refund; a deferral costs one email.
