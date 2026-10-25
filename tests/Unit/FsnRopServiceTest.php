<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Services\FsnRopService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FsnRopServiceTest extends TestCase
{
    use RefreshDatabase;

    private FsnRopService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->service = new FsnRopService();
    }

    public function test_fsn_rop_service_calculates_analysis_accurately(): void
    {
        $startDate = Carbon::now()->subDays(90)->startOfDay();
        $endDate = Carbon::now()->startOfDay();

        $result = $this->service->calculateAnalysis($startDate, $endDate);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('period', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('items', $result);

        $this->assertEquals(20, $result['summary']['total_items']);
        $this->assertGreaterThan(0, $result['summary']['fast_count']);
        $this->assertGreaterThan(0, $result['summary']['slow_count']);
        $this->assertGreaterThan(0, $result['summary']['non_count']);

        foreach ($result['items'] as $item) {
            $tor = $item['tor'];
            $category = $item['fsn_category'];

            if ($category === 'F') {
                $this->assertGreaterThan(3.0, $tor);
                $this->assertNotNull($item['safety_stock']);
                $this->assertNotNull($item['rop']);
                $this->assertGreaterThanOrEqual(1, $item['rop']);
            } elseif ($category === 'S') {
                $this->assertGreaterThanOrEqual(1.0, $tor);
                $this->assertLessThanOrEqual(3.0, $tor);
                $this->assertNull($item['safety_stock']);
                $this->assertNull($item['rop']);
            } else {
                $this->assertEquals('N', $category);
                $this->assertLessThan(1.0, $tor);
                $this->assertNull($item['safety_stock']);
                $this->assertNull($item['rop']);
            }
        }
    }

    public function test_fsn_rop_service_applies_results_to_items(): void
    {
        $startDate = Carbon::now()->subDays(90)->startOfDay();
        $endDate = Carbon::now()->startOfDay();

        $analysis = $this->service->calculateAnalysis($startDate, $endDate);
        $updatedCount = $this->service->applyAnalysisToItems($analysis['items']);

        $this->assertEquals(20, $updatedCount);

        // Verifikasi langsung ke basis data
        $fastItem = Item::where('fsn_category', 'F')->first();
        $this->assertNotNull($fastItem);
        $this->assertNotNull($fastItem->safety_stock);
        $this->assertNotNull($fastItem->rop);

        $slowItem = Item::where('fsn_category', 'S')->first();
        if ($slowItem) {
            $this->assertNull($slowItem->safety_stock);
            $this->assertNull($slowItem->rop);
        }

        $nonItem = Item::where('fsn_category', 'N')->first();
        if ($nonItem) {
            $this->assertNull($nonItem->safety_stock);
            $this->assertNull($nonItem->rop);
        }
    }

    public function test_fsn_rop_service_gets_low_stock_items(): void
    {
        $lowStockItems = $this->service->getLowStockItems();

        $this->assertNotEmpty($lowStockItems);

        foreach ($lowStockItems as $item) {
            $this->assertTrue($item->is_low_stock);
            $this->assertNotEquals('N', $item->fsn_category);
            $this->assertLessThanOrEqual($item->order_threshold, $item->stock);
        }
    }

    public function test_fsn_rop_service_calculates_profit_correctly(): void
    {
        $profitData = $this->service->calculateProfit('all');

        $this->assertIsArray($profitData);
        $this->assertArrayHasKey('total_sales', $profitData);
        $this->assertArrayHasKey('total_cost', $profitData);
        $this->assertArrayHasKey('total_profit', $profitData);
        $this->assertArrayHasKey('daily_trend', $profitData);

        $this->assertGreaterThan(0, $profitData['total_sales']);
        $this->assertGreaterThan(0, $profitData['total_profit']);

        $expectedProfit = round($profitData['total_sales'] - $profitData['total_cost'], 2);
        $this->assertEquals($expectedProfit, $profitData['total_profit']);
    }
}
