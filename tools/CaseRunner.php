<?php

/**
 * Runs a case table against a pack through the real engine, so a rule cannot be
 * changed without one of these failing. Used by bin/rules.php cases and by the
 * test suite.
 *
 * A case looks like:
 *   ['ask' => 'how much is it?', 'item' => 'marina-2br', 'kind' => 'rule:fact_price']
 *   ['ask' => 'is it negotiable?', 'item' => 'marina-2br', 'kinds' => ['rule:deferral_negotiable'], 'missing' => 'AED']
 */
final class CaseRunner
{
    public function __construct(private readonly RulesInspector $inspector) {}

    /**
     * @param  list<array<string, mixed>>  $cases
     * @return list<array{ask: string, expect: string, got: string, ok: bool, text: string}>
     */
    public function run(array $cases): array
    {
        $results = [];

        foreach ($cases as $index => $case) {
            $ask = (string) ($case['ask'] ?? '');
            $expect = $this->expectation($case);
            $item = isset($case['item']) ? (string) $case['item'] : null;

            $reply = $this->inspector->probe($ask, $item);
            $ok = in_array($reply['kind'], $this->kinds($case), true);

            if ($ok && isset($case['contains']) && ! str_contains($reply['text'], (string) $case['contains'])) {
                $ok = false;
                $expect .= ' containing "' . $case['contains'] . '"';
            }

            if ($ok && isset($case['missing']) && str_contains($reply['text'], (string) $case['missing'])) {
                $ok = false;
                $expect .= ' without "' . $case['missing'] . '"';
            }

            $results[] = [
                'ask'    => $ask . ($item !== null ? ' [' . $item . ']' : ''),
                'expect' => $expect,
                'got'    => (string) $reply['kind'],
                'ok'     => $ok,
                'text'   => (string) $reply['text'],
            ];
        }

        return $results;
    }

    /** @param array<string, mixed> $case */
    private function kinds(array $case): array
    {
        if (isset($case['kinds'])) {
            return array_values(array_map('strval', (array) $case['kinds']));
        }

        return isset($case['kind']) ? [(string) $case['kind']] : [];
    }

    /** @param array<string, mixed> $case */
    private function expectation(array $case): string
    {
        $kinds = $this->kinds($case);

        return $kinds === [] ? '(no expectation)' : implode(' or ', $kinds);
    }
}
