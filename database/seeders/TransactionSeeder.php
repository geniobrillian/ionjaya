<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $items = Item::all()->keyBy('id');
        $startDate = Carbon::now()->subDays(90)->startOfDay();
        $endDate = Carbon::now()->startOfDay();

        // 1. Buat Transaksi Pembelian (Restock berkala tiap ~14 hari untuk barang fast)
        $currentRestockDate = $startDate->copy()->addDays(3);
        while ($currentRestockDate->lte($endDate)) {
            $purchaseDate = $currentRestockDate->toDateString();

            // Restock beberapa barang Fast Moving
            $restockItems = [1, 2, 3, 4, 5, 6, 7, 8];
            $selectedRestock = array_slice($restockItems, 0, rand(4, 8));

            $transaction = Transaction::create([
                'date' => $purchaseDate,
                'transaction_type' => 'pembelian',
                'total' => 0,
                'user_id' => 1, // Super Admin
            ]);

            $totalPurchase = 0;
            foreach ($selectedRestock as $itemId) {
                $item = $items[$itemId];
                $qty = rand(15, 30);
                $unitPrice = $item->purchase_price;
                $subtotal = $qty * $unitPrice;

                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'item_id' => $item->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                $totalPurchase += $subtotal;
            }

            $transaction->update(['total' => $totalPurchase]);
            $currentRestockDate->addDays(rand(12, 16));
        }

        // 2. Buat Transaksi Penjualan harian (90 hari)
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $salesDate = $date->toDateString();
            
            // 1-2 transaksi penjualan per hari
            $transactionsToday = rand(1, 2);

            for ($t = 0; $t < $transactionsToday; $t++) {
                $soldDetails = [];

                // Peluang terjual untuk Fast Moving: 75% per hari
                for ($itemId = 1; $itemId <= 8; $itemId++) {
                    if (rand(1, 100) <= 75) {
                        $soldDetails[] = [
                            'item_id' => $itemId,
                            'quantity' => rand(1, 3),
                        ];
                    }
                }

                // Peluang terjual untuk Slow Moving: 7% per hari (sekitar 6 transaksi per 90 hari)
                for ($itemId = 9; $itemId <= 15; $itemId++) {
                    if (rand(1, 100) <= 7) {
                        $soldDetails[] = [
                            'item_id' => $itemId,
                            'quantity' => 1,
                        ];
                    }
                }

                // Peluang terjual untuk Non Moving: 0.5% per hari (0 sampai 1 kali dalam 90 hari)
                for ($itemId = 16; $itemId <= 20; $itemId++) {
                    if (rand(1, 200) === 1) {
                        $soldDetails[] = [
                            'item_id' => $itemId,
                            'quantity' => 1,
                        ];
                    }
                }

                if (empty($soldDetails)) {
                    continue;
                }

                // Buat Header Penjualan
                $saleTransaction = Transaction::create([
                    'date' => $salesDate,
                    'transaction_type' => 'penjualan',
                    'total' => 0,
                    'user_id' => rand(1, 2), // Kasir atau Super Admin
                ]);

                $totalSale = 0;
                foreach ($soldDetails as $detail) {
                    $item = $items[$detail['item_id']];
                    $qty = $detail['quantity'];
                    $price = $item->selling_price;
                    $subtotal = $qty * $price;

                    TransactionDetail::create([
                        'transaction_id' => $saleTransaction->id,
                        'item_id' => $item->id,
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'subtotal' => $subtotal,
                    ]);

                    $totalSale += $subtotal;
                }

                $saleTransaction->update(['total' => $totalSale]);
            }
        }
    }
}
