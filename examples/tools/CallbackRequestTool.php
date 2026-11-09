<?php

/**
 * Template: "can someone call me?"
 *
 * The cheapest useful write tool on any site, and the honest end of every
 * conversation the assistant cannot finish. Note that it does not replace
 * save_lead: this one schedules a call, save_lead stores the contact.
 *
 * Declare it: 'tools' => [..., 'callback_request'].
 */

use AssistantKit\Tools\Tool;

final class CallbackRequestTool
{
    /**
     * @param  callable(string, string, string, array<string, mixed>): string  $schedule
     *         (name, phone, when, context) => reference; throw when it cannot be taken
     */
    public function __construct(private $schedule) {}

    public function tool(): Tool
    {
        return new Tool(
            'callback_request',
            'Ask the team to call the visitor back. Needs a name and a phone number. Use it when the visitor wants a person, not when they only want an answer.',
            [
                'type'       => 'object',
                'properties' => [
                    'name'  => ['type' => 'string'],
                    'phone' => ['type' => 'string', 'description' => 'As the visitor wrote it, including the country code when they gave one.'],
                    'when'  => ['type' => 'string', 'enum' => ['now', 'morning', 'afternoon', 'evening'], 'description' => 'When to call. Default to now when unstated.'],
                ],
                'required'   => ['name', 'phone'],
            ],
            fn(array $input, array $context): string => $this->request($input, $context),
            writes: true,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $context
     */
    private function request(array $input, array $context): string
    {
        $phone = preg_replace('/[^0-9+]/', '', (string) $input['phone']);

        // Digits only, with a sane minimum: "call me" with no number is a dead end
        // and must be caught here rather than promised to the visitor.
        if ($phone === null || strlen($phone) < 7) {
            throw new \RuntimeException('That phone number is too short to call; ask them to check it.');
        }

        $reference = ($this->schedule)(
            trim((string) $input['name']),
            $phone,
            (string) ($input['when'] ?? 'now'),
            $context,
        );

        return 'Callback booked: ' . $reference;
    }
}
