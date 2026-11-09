<?php

namespace AssistantKit\Tools;

/**
 * Everything the host site can add to the menu. A tool exists here only if the
 * site wrote a handler for it, which is why "listed but not implemented" cannot
 * happen: nothing is offered that the registry does not hold.
 */
final class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function __construct(Tool ...$tools)
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function register(Tool $tool): self
    {
        // A name is an identity: two handlers behind one name would make the
        // menu depend on registration order.
        if (isset($this->tools[$tool->name])) {
            throw new \InvalidArgumentException('A tool is already registered as ' . $tool->name . '.');
        }

        $this->tools[$tool->name] = $tool;

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->tools);
    }

    /** @return list<Tool> */
    public function all(): array
    {
        return array_values($this->tools);
    }
}
