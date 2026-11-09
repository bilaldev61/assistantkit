<?php

/**
 * Template: book a viewing, a fitting, a service slot.
 *
 * A write tool. Two rules worth keeping when you copy it:
 *   - the day and slot come back as concrete values, so the visitor can confirm;
 *   - the reply must never promise a slot the tool did not return.
 *
 * Declare it: 'tools' => [..., 'book_appointment'].
 */

use AssistantKit\Tools\Tool;

final class BookAppointmentTool
{
    /**
     * @param  callable(string, string, array<string, mixed>): array{reference: string, when: string}  $book
     *         (date, slot, context) => confirmation; throw to refuse
     */
    public function __construct(private $book) {}

    public function tool(): Tool
    {
        return new Tool(
            'book_appointment',
            'Book an appointment for a specific day and time. Ask for the visitor\'s name and email first, then confirm the exact day and slot the tool returns.',
            [
                'type'       => 'object',
                'properties' => [
                    'date' => ['type' => 'string', 'description' => 'The day, as YYYY-MM-DD. Convert "tomorrow" using the date given in the system prompt.'],
                    'slot' => ['type' => 'string', 'enum' => ['morning', 'afternoon', 'evening'], 'description' => 'Part of the day the visitor asked for.'],
                ],
                'required'   => ['date', 'slot'],
            ],
            fn(array $input, array $context): array => $this->order($input, $context),
            writes: true,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function order(array $input, array $context): array
    {
        $date = trim((string) $input['date']);

        // A date in the past is not a booking, it is a typo: refuse it here
        // rather than letting the model apologise later.
        if ($date < date('Y-m-d')) {
            throw new \RuntimeException('That day has already passed; offer the next available one.');
        }

        $booked = ($this->book)($date, (string) $input['slot'], $context);

        return [
            'booked'    => true,
            'reference' => (string) ($booked['reference'] ?? ''),
            'when'      => (string) ($booked['when'] ?? $date),
        ];
    }
}
