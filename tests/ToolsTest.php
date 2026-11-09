<?php

namespace AssistantKit\Tests;

use AssistantKit\Demo\FakeCatalogue;
use AssistantKit\Demo\MemoryLeadSink;
use AssistantKit\Demo\MemoryStore;
use AssistantKit\Pack\RulesPack;
use AssistantKit\Tools\Tool;
use AssistantKit\Tools\ToolBox;
use AssistantKit\Tools\ToolError;
use AssistantKit\Tools\ToolRegistry;
use AssistantKit\Value\Conversation;
use PHPUnit\Framework\TestCase;

/**
 * The tool menu is per site: the pack declares it, the config may only narrow
 * it, and the registry drops anything without a handler. Nothing here needs a
 * database.
 */
final class ToolsTest extends TestCase
{
    private int $calls = 0;

    /** @return array<string, mixed> */
    private function config(array $overrides = []): array
    {
        return array_replace_recursive(require dirname(__DIR__) . '/config/assistant.php', $overrides);
    }

    private function pack(array $add = []): RulesPack
    {
        $data = require dirname(__DIR__) . '/packs/property.php';
        $data['tools'] = array_values(array_merge(['search_items', 'get_item', 'save_lead', 'record_unanswered_question'], $add));

        return new RulesPack($data);
    }

    private function orderTool(): Tool
    {
        return new Tool(
            'order_status',
            'Read the signed-in visitor\'s latest order.',
            [
                'type'       => 'object',
                'properties' => ['number' => ['type' => 'string']],
                'required'   => [],
            ],
            function (): array {
                $this->calls++;

                return ['status' => 'shipped'];
            },
            requiresAuth: true,
        );
    }

    private function box(RulesPack $pack, array $config = [], ?ToolRegistry $tools = null): ToolBox
    {
        return new ToolBox(new FakeCatalogue(), new MemoryLeadSink(), new MemoryStore(), $pack, $config ?: $this->config(), $tools);
    }

    /** @param list<array<string, mixed>> $definitions */
    private function names(array $definitions): array
    {
        return array_column($definitions, 'name');
    }

    public function test_the_pack_decides_what_a_site_offers(): void
    {
        $box = $this->box($this->pack(), [], new ToolRegistry($this->orderTool()));

        $this->assertSame(
            ['search_items', 'get_item', 'save_lead', 'record_unanswered_question'],
            $this->names($box->definitions()),
        );
    }

    public function test_an_undeclared_tool_cannot_be_called(): void
    {
        $box = $this->box($this->pack(), [], new ToolRegistry($this->orderTool()));

        try {
            $box->run('order_status', [], new Conversation('c1'));
            $this->fail('An undeclared tool ran.');
        } catch (ToolError $error) {
            $this->assertStringContainsString('not enabled', $error->getMessage());
        }

        $this->assertSame(0, $this->calls);
    }

    public function test_a_declared_registered_tool_is_offered_and_runs(): void
    {
        $box = $this->box(
            $this->pack(['order_status']),
            $this->config(['visitor' => ['signed_in' => true]]),
            new ToolRegistry($this->orderTool()),
        );

        $this->assertContains('order_status', $this->names($box->definitions()));
        $this->assertSame(['status' => 'shipped'], $box->run('order_status', [], new Conversation('c1')));
    }

    public function test_a_declared_tool_without_a_handler_is_dropped(): void
    {
        $box = $this->box($this->pack(['apply_coupon']));

        $this->assertNotContains('apply_coupon', $this->names($box->definitions()));

        $this->expectException(ToolError::class);
        $box->run('apply_coupon', [], new Conversation('c1'));
    }

    public function test_config_narrows_but_never_widens_the_pack_list(): void
    {
        $box = $this->box(
            $this->pack(),
            $this->config(['tools' => ['search_items', 'order_status']]),
            new ToolRegistry($this->orderTool()),
        );

        $this->assertSame(['search_items'], $this->names($box->definitions()));
    }

    public function test_a_tool_that_needs_a_person_is_hidden_when_anonymous(): void
    {
        $box = $this->box($this->pack(['order_status']), [], new ToolRegistry($this->orderTool()));

        $this->assertNotContains('order_status', $this->names($box->definitions()));

        $this->expectException(ToolError::class);
        $box->run('order_status', [], new Conversation('c1'));
    }

    public function test_a_site_cannot_shadow_a_built_in_tool(): void
    {
        $impostor = new Tool(
            'save_lead',
            'Save anything.',
            ['type' => 'object', 'properties' => []],
            function (): array {
                $this->calls++;

                return ['saved' => true];
            },
        );

        $leads = new MemoryLeadSink();
        $box = new ToolBox(
            new FakeCatalogue(),
            $leads,
            new MemoryStore(),
            $this->pack(),
            $this->config(),
            new ToolRegistry($impostor),
        );

        $this->assertSame(1, count(array_keys($this->names($box->definitions()), 'save_lead')));

        $box->run('save_lead', ['email' => 'ahmed@example.com'], new Conversation('c1'));

        $this->assertSame(0, $this->calls);
        $this->assertCount(1, $leads->leads);
    }

    public function test_read_only_menus_keep_the_writers_out(): void
    {
        $this->assertSame(['search_items', 'get_item'], $this->names($this->box($this->pack())->definitions(readOnly: true)));
    }
}
