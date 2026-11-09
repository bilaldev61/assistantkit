<?php

/**
 * Template: read one of the visitor's own orders.
 *
 * Copy to app/Assistant/Tools/OrderStatusTool.php, then:
 *   1. register it:    new ToolRegistry((new OrderStatusTool($finder, $user))->tool())
 *   2. declare it:     'tools' => [..., 'order_status']   in that site's pack
 *   3. tell the Kit:   'visitor' => ['signed_in' => auth()->check()]
 *
 * requiresAuth is what stops "track my order" from becoming an order-number
 * guessing endpoint. The owner scoping inside the handler is not optional:
 * the Kit knows who is present, never which rows are theirs.
 */

use AssistantKit\Tools\Tool;

final class OrderStatusTool
{
    /**
     * @param  callable(string, mixed): ?array<string, mixed>  $findOrder  (number, user) => row|null
     * @param  callable(): mixed                               $currentUser returns the logged-in customer or null
     */
    public function __construct(private $findOrder, private $currentUser) {}

    public function tool(): Tool
    {
        return new Tool(
            'order_status',
            'Look up one of the signed-in customer\'s own orders by its number. If they are not signed in, say so and offer to take their details instead.',
            [
                'type'       => 'object',
                'properties' => [
                    'number' => ['type' => 'string', 'description' => 'The order number as the customer wrote it, for example BK-1042.'],
                ],
                'required'   => ['number'],
            ],
            fn(array $input): array => $this->lookup($input),
            requiresAuth: true,
        );
    }

    /** @param array<string, mixed> $input */
    private function lookup(array $input): array
    {
        $user = ($this->currentUser)();

        // Belt and braces: the engine already hid the tool from anonymous visitors.
        if ($user === null) {
            throw new \RuntimeException('The customer must be signed in to track an order.');
        }

        // Replace with the host query. The owner condition is the point.
        $order = ($this->findOrder)(trim((string) $input['number']), $user);

        if ($order === null) {
            throw new \RuntimeException('No order with that number on this account.');
        }

        return [
            'number'  => $order['number'] ?? '',
            'status'  => $order['status'] ?? '',
            'placed'  => $order['placed'] ?? null,
            'shipped' => $order['shipped'] ?? null,
        ];
    }
}
