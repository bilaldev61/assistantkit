<?php

namespace AssistantKit\Demo;

use AssistantKit\Contracts\LeadSink;

/**
 * Leads collected in memory, exposed so tests and the demo can read them.
 * A real sink writes an enquiry row or posts to a CRM and returns its id.
 */
final class MemoryLeadSink implements LeadSink
{
    /** @var list<array<string, mixed>> */
    public array $leads = [];

    public function capture(array $lead): string
    {
        $this->leads[] = $lead;

        return 'lead-' . count($this->leads);
    }
}
