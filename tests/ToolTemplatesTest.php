<?php

namespace AssistantKit\Tests;

use AssistantKit\Tools\ToolBox;
use AssistantKit\Tools\ToolError;
use AssistantKit\Tools\ToolRegistry;
use AssistantKit\Value\Conversation;
use BookAppointmentTool;
use CallbackRequestTool;
use CheckStockTool;
use DeliveryEtaTool;
use OrderStatusTool;
use PHPUnit\Framework\TestCase;
use RequestQuoteTool;

require_once dirname(__DIR__) . '/examples/tools/OrderStatusTool.php';
require_once dirname(__DIR__) . '/examples/tools/CheckStockTool.php';
require_once dirname(__DIR__) . '/examples/tools/DeliveryEtaTool.php';
require_once dirname(__DIR__) . '/examples/tools/BookAppointmentTool.php';
require_once dirname(__DIR__) . '/examples/tools/RequestQuoteTool.php';
require_once dirname(__DIR__) . '/examples/tools/CallbackRequestTool.php';

/**
 * The templates are examples, but an example that does not run is a lie. Each
 * one is exercised through Tool::call(), which is the same validation path the
 * engine uses.
 */
final class ToolTemplatesTest extends TestCase
{
    public function test_order_status_needs_a_signed_in_visitor_and_scopes_by_owner(): void
    {
        $tool = (new OrderStatusTool(
            fn(string $number, object $user): ?array => $user->id === 7 && $number === 'BK-1'
                ? ['number' => 'BK-1', 'status' => 'shipped', 'placed' => '2026-01-02']
                : null,
            fn(): object => (object) ['id' => 7],
        ))->tool();

        $this->assertSame('shipped', $tool->call(['number' => 'BK-1'], [])['status']);

        // Same tool, someone else's order number: refused, not leaked.
        $this->expectException(\RuntimeException::class);
        $tool->call(['number' => 'BK-9'], []);
    }

    public function test_order_status_is_hidden_from_anonymous_visitors(): void
    {
        $box = new ToolBox(
            new \AssistantKit\Demo\FakeCatalogue(),
            new \AssistantKit\Demo\MemoryLeadSink(),
            new \AssistantKit\Demo\MemoryStore(),
            new \AssistantKit\Pack\RulesPack(['tools' => ['search_items', 'order_status']]),
            ['visitor' => ['signed_in' => false]],
            new ToolRegistry((new OrderStatusTool(fn(): ?array => null, fn(): ?object => null))->tool()),
        );

        $this->assertNotContains('order_status', array_column($box->definitions(), 'name'));

        $this->expectException(ToolError::class);
        $box->run('order_status', ['number' => 'BK-1'], new Conversation('c1'));
    }

    public function test_check_stock_reports_units_and_restock(): void
    {
        $tool = (new CheckStockTool(fn(string $model, string $size): array => [
            'units'   => $size === '54cm' ? 2 : 0,
            'restock' => 'next week',
        ]))->tool();

        $this->assertSame(['in_stock' => true, 'units' => 2, 'restock' => null], $tool->call(['model' => 'Shimano 105', 'size' => '54cm'], []));
        $this->assertSame(['in_stock' => false, 'units' => 0, 'restock' => 'next week'], $tool->call(['model' => 'Shimano 105', 'size' => '58cm'], []));
    }

    public function test_delivery_refuses_an_area_it_does_not_cover(): void
    {
        $tool = (new DeliveryEtaTool(fn(string $where): ?array => $where === 'Dubai Marina'
            ? ['days_min' => 1, 'days_max' => 2, 'cost' => 'AED 50']
            : null))->tool();

        $this->assertSame('AED 50', $tool->call(['postcode' => 'Dubai Marina'], [])['cost']);

        $this->expectException(\RuntimeException::class);
        $tool->call(['postcode' => 'Abu Dhabi'], []);
    }

    public function test_booking_refuses_a_day_in_the_past(): void
    {
        $booked = [];
        $tool = (new BookAppointmentTool(function (string $date, string $slot) use (&$booked): array {
            $booked[] = [$date, $slot];

            return ['reference' => 'AP-1', 'when' => $date . ' ' . $slot];
        }))->tool();

        try {
            $tool->call(['date' => '2020-01-01', 'slot' => 'morning'], []);
            $this->fail('A past date was booked.');
        } catch (\RuntimeException) {
            $this->assertSame([], $booked);
        }

        $this->assertSame('AP-1', $tool->call(['date' => date('Y-m-d', strtotime('+1 day')), 'slot' => 'evening'], [])['reference']);
    }

    public function test_quote_requests_never_carry_a_price(): void
    {
        $seen = null;
        $tool = (new RequestQuoteTool(function (array $input) use (&$seen): array {
            $seen = $input;

            return ['reference' => 'Q-9'];
        }))->tool();

        $result = $tool->call(['item' => 'custom build', 'quantity' => 0], []);

        $this->assertSame('Q-9', $result['reference']);
        $this->assertSame(1, $seen['quantity'], 'Quantity is floored at one.');
        $this->assertArrayNotHasKey('price', $result);
    }

    public function test_callback_needs_a_usable_phone_number(): void
    {
        $tool = (new CallbackRequestTool(fn(string $name, string $phone, string $when): string => 'CB-1'))->tool();

        $this->assertStringContainsString('CB-1', $tool->call(['name' => 'Ahmed', 'phone' => '+971 50 123 4567'], []));

        $this->expectException(\RuntimeException::class);
        $tool->call(['name' => 'Ahmed', 'phone' => '123'], []);
    }
}
