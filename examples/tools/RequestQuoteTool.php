<?php

/**
 * Template: ask for a price on something that has no price yet
 * (a custom build, a bulk order, a project).
 *
 * This is the tool that stops the assistant from inventing a figure: when a
 * product is configurable, there is no fact to quote, so the honest answer is
 * a quote request.
 *
 * Declare it: 'tools' => [..., 'request_quote'].
 */

use AssistantKit\Tools\Tool;

final class RequestQuoteTool
{
    /**
     * @param  callable(array<string, mixed>, array<string, mixed>): array{reference: string}  $request
     *         (input, context) => reference; throw when it cannot be taken
     */
    public function __construct(private $request) {}

    public function tool(): Tool
    {
        return new Tool(
            'request_quote',
            'Open a quote request when the visitor wants a price for something configured, custom or in bulk. Ask for name and email first, and never state a price yourself.',
            [
                'type'       => 'object',
                'properties' => [
                    'item'     => ['type' => 'string', 'description' => 'What they want priced, in their own words.'],
                    'quantity' => ['type' => 'integer', 'description' => 'How many, when they said.'],
                    'details'  => ['type' => 'string', 'description' => 'The configuration they described: sizes, colours, extras.'],
                ],
                'required'   => ['item'],
            ],
            fn(array $input, array $context): array => $this->open($input, $context),
            writes: true,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function open(array $input, array $context): array
    {
        $result = ($this->request)([
            'item'     => trim((string) $input['item']),
            'quantity' => max(1, (int) ($input['quantity'] ?? 1)),
            'details'  => trim((string) ($input['details'] ?? '')),
        ], $context);

        return [
            'opened'    => true,
            'reference' => (string) ($result['reference'] ?? ''),
        ];
    }
}
