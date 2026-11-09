<?php

/**
 * Template: when will it arrive, and what does delivery cost?
 *
 * Public information, no auth. Two habits worth copying:
 *   - the tool returns numbers, never a sentence: the model phrases it;
 *   - a zone it does not know returns an error, not a guess, so the reply
 *     becomes a deferral instead of a promise.
 *
 * Declare it: 'tools' => [..., 'delivery_eta'].
 */

use AssistantKit\Tools\Tool;

final class DeliveryEtaTool
{
    /**
     * @param  callable(string, ?string): ?array{days_min: int, days_max: int, cost: ?string}  $quote
     *         (postcode or city, method) => estimate, or null when the area is not covered
     */
    public function __construct(private $quote) {}

    public function tool(): Tool
    {
        return new Tool(
            'delivery_eta',
            'Estimate delivery time and cost for a postcode or city. If the area is not covered, say delivery there is not confirmed and take the visitor\'s details for the team.',
            [
                'type'       => 'object',
                'properties' => [
                    'postcode' => ['type' => 'string', 'description' => 'Postcode or city as the visitor wrote it.'],
                    'method'   => ['type' => 'string', 'enum' => ['standard', 'express'], 'description' => 'Delivery speed. Default to standard when unstated.'],
                ],
                'required'   => ['postcode'],
            ],
            fn(array $input): array => $this->lookup($input),
        );
    }

    /** @param array<string, mixed> $input */
    private function lookup(array $input): array
    {
        $estimate = ($this->quote)(trim((string) $input['postcode']), $input['method'] ?? null);

        if ($estimate === null) {
            throw new \RuntimeException('That area is not on the delivery list; do not estimate it.');
        }

        return [
            'days_min' => (int) ($estimate['days_min'] ?? 0),
            'days_max' => (int) ($estimate['days_max'] ?? 0),
            'cost'     => $estimate['cost'] ?? null,
        ];
    }
}
