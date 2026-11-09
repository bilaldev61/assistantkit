<?php

/**
 * Template: is this size in stock?
 *
 * Public information, so no auth. Say the number plainly or say nothing:
 * never let the model guess availability, because a wrong "yes" costs a refund.
 *
 * Declare it in the site's pack: 'tools' => [..., 'check_stock'].
 */

use AssistantKit\Tools\Tool;

final class CheckStockTool
{
    /**
     * @param  callable(string, string): array{units: int, restock: ?string}  $countStock  (model, size) => stock
     */
    public function __construct(private $countStock) {}

    public function tool(): Tool
    {
        return new Tool(
            'check_stock',
            'Check how many units of one exact product and size are in stock. Give the number only; if it is zero, say when it is expected back and offer to take details.',
            [
                'type'       => 'object',
                'properties' => [
                    'model' => ['type' => 'string', 'description' => 'The product name or model exactly as the visitor said it.'],
                    'size'  => ['type' => 'string', 'description' => 'The size or variant, for example 54cm or Large.'],
                ],
                'required'   => ['model', 'size'],
            ],
            fn(array $input): array => $this->lookup($input),
        );
    }

    /** @param array<string, mixed> $input */
    private function lookup(array $input): array
    {
        $stock = ($this->countStock)(trim((string) $input['model']), trim((string) $input['size']));
        $units = max(0, (int) ($stock['units'] ?? 0));

        return [
            'in_stock' => $units > 0,
            'units'    => $units,
            'restock'  => $units > 0 ? null : ($stock['restock'] ?? null),
        ];
    }
}
