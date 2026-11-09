<?php

namespace AssistantKit\Support;

final class Text
{
    /**
     * Lowercase, single-spaced, trimmed. Every rule matches against this, so a
     * visitor typing "  HOW   MUCH?  " behaves like "how much".
     */
    public static function normalise(?string $text): string
    {
        $text = mb_strtolower(trim((string) $text));

        return preg_replace('/\s+/', ' ', $text) ?? $text;
    }

    public static function hasDigit(string $text): bool
    {
        return (bool) preg_match('/\d/', $text);
    }

    /** @param array<string, string|null> $values */
    public static function fill(string $template, array $values): string
    {
        $map = [];

        foreach ($values as $key => $value) {
            if (is_string($value) && $value !== '') {
                $map[':' . $key] = $value;
            }
        }

        return strtr($template, $map);
    }

    public static function sentence(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return $text;
        }

        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    /** @return list<string> */
    public static function words(string $text): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
