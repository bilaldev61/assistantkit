<?php

namespace AssistantKit\Tools;

/**
 * One callable the model may choose. The engine owns the name and the argument
 * shape; the host site owns only what the handler does.
 *
 * `requiresAuth` is what stops "track my order" from becoming a way to read
 * somebody else's order: the tool is not offered to, and not run for, an
 * anonymous visitor.
 */
final class Tool
{
    /** @var callable(array<string, mixed>, array<string, mixed>): mixed */
    private $handler;

    /**
     * @param  array<string, mixed>  $parameters  JSON-schema style properties
     * @param  callable(array<string, mixed>, array<string, mixed>): mixed  $handler
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
        callable $handler,
        public readonly bool $writes = false,
        public readonly bool $requiresAuth = false,
    ) {
        $this->handler = $handler;
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name'        => $this->name,
            'description' => $this->description,
            'parameters'  => $this->parameters,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $context
     */
    public function call(array $input, array $context): mixed
    {
        $this->validate($input);

        return ($this->handler)($input, $context);
    }

    /**
     * The model is not trusted to respect the schema, so the same rules are
     * checked here before any site code runs.
     *
     * @param  array<string, mixed>  $input
     */
    private function validate(array $input): void
    {
        $properties = (array) ($this->parameters['properties'] ?? []);

        foreach ((array) ($this->parameters['required'] ?? []) as $key) {
            $value = $input[$key] ?? null;

            if ($value === null || (is_string($value) && trim($value) === '')) {
                throw new ToolError(sprintf('%s requires "%s".', $this->name, $key));
            }
        }

        foreach ($properties as $key => $schema) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];
            $type = (string) (($schema['type'] ?? '') ?: '');

            $ok = match ($type) {
                'string'  => is_string($value),
                'integer' => is_int($value) || (is_string($value) && ctype_digit($value)),
                'number'  => is_numeric($value),
                'boolean' => is_bool($value),
                default   => true,
            };

            if (! $ok) {
                throw new ToolError(sprintf('%s expects "%s" to be %s.', $this->name, $key, $type));
            }

            if (! empty($schema['enum']) && is_array($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
                throw new ToolError(sprintf(
                    '%s expects "%s" to be one of: %s.',
                    $this->name,
                    $key,
                    implode(', ', array_map('strval', $schema['enum'])),
                ));
            }
        }
    }
}
