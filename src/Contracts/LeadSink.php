<?php

namespace AssistantKit\Contracts;

/**
 * Where a captured name and email goes: an enquiry row, a CRM contact, a
 * webhook. Returning the host's own reference is what lets the transcript say
 * "this chat turned into lead #48".
 */
interface LeadSink
{
    /**
     * @param  array{name: string|null, email: string, phone: string|null, message: string|null, item: string|null, source: string, chat: string}  $lead
     * @return string the host's reference for this lead
     */
    public function capture(array $lead): string;
}
