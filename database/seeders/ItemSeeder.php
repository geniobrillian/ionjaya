<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // 8 Fast Moving Items
            [
                'id' => 1,
                'item_code' => 'BRG001',
                'item_name' => 'Kampas Rem Depan Honda Beat',
                'purchase_price' => 32000,
                'selling_price' => 45000,
                'stock' => 3, // Di bawah ROP (8) -> MENIPIS
                'fsn_category' => 'F',
                'safety_stock' => 4,
                'rop' => 8,
                'supplier_id' => 1,
            ],
            [
                'id' => 2,
                'item_code' => 'BRG002',
                'item_name' => 'Oli Mesin AHM MPX1 0.8L',
                'purchase_price' => 48000,
                'selling_price' => 62000,
                'stock' => 4, // Di bawah ROP (12) -> MENIPIS
                'fsn_category' => 'F',
                'safety_stock' => 5,
                'rop' => 12,
                'supplier_id' => 1,
            ],
            [
                'id' => 3,
                'item_code' => 'BRG003',
                'item_name' => 'Busi NGK CPR9EA-9',
                'purchase_price' => 18000,
                'selling_price' => 25000,
                'stock' => 5, // Di bawah ROP (10) -> MENIPIS
                'fsn_category' => 'F',
                'safety_stock' => 4,
                'rop' => 10,
                'supplier_id' => 2,
            ],
            [
                'id' => 4,
                'item_code' => 'BRG004',
                'item_name' => 'Ban Luar IRC 80/90-14 Tubeless',
                'purchase_price' => 185000,
                'selling_price' => 230000,
                'stock' => 2, // Di bawah ROP (6) -> MENIPIS
                'fsn_category' => 'F',
                'safety_stock' => 3,
                'rop' => 6,
                'supplier_id' => 3,
            ],
            [
                'id' => 5,
                'item_code' => 'BRG005',
                'item_name' => 'Kampas Rem Belakang Vario',
                'purchase_price' => 35000,
                'selling_price' => 48000,
                'stock' => 45, // AMAN
                'fsn_category' => 'F',
                'safety_stock' => 4,
                'rop' => 9,
                'supplier_id' => 1,
            ],
            [
                'id' => 6,
                'item_code' => 'BRG006',
                'item_name' => 'Oli Mesin Yamalube 0.8L',
                'purchase_price' => 47000,
                'selling_price' => 60000,
                'stock' => 38, // AMAN
                'fsn_category' => 'F',
                'safety_stock' => 5,
                'rop' => 11,
                'supplier_id' => 2,
            ],
            [
                'id' => 7,
                'item_code' => 'BRG007',
                'item_name' => 'Rantai Roda Honda Grand / Supra',
                'purchase_price' => 65000,
                'selling_price' => 85000,
                'stock' => 28, // AMAN
                'fsn_category' => 'F',
                'safety_stock' => 3,
                'rop' => 7,
                'supplier_id' => 3,
            ],
            [
                'id' => 8,
                'item_code' => 'BRG008',
                'item_name' => 'Lampu Depan Beat FI Osram',
                'purchase_price' => 28000,
                'selling_price' => 38000,
                'stock' => 32, // AMAN
                'fsn_category' => 'F',
                'safety_stock' => 3,
                'rop' => 8,
                'supplier_id' => 4,
            ],

            // 7 Slow Moving Items
            [
                'id' => 9,
                'item_code' => 'BRG009',
                'item_name' => 'Gir Belakang Supra X 125',
                'purchase_price' => 45000,
                'selling_price' => 60000,
                'stock' => 2, // Di bawah ROP (4) -> MENIPIS
                'fsn_category' => 'S',
                'safety_stock' => null,
                'rop' => 4,
                'supplier_id' => 1,
            ],
            [
                'id' => 10,
                'item_code' => 'BRG010',
                'item_name' => 'Piringan Cakram Depan Beat',
                'purchase_price' => 70000,
                'selling_price' => 95000,
                'stock' => 12, // AMAN
                'fsn_category' => 'S',
                'safety_stock' => null,
                'rop' => 3,
                'supplier_id' => 2,
            ],
            [
                'id' => 11,
                'item_code' => 'BRG011',
                'item_name' => 'V-Belt Standar Mio Sporty',
                'purchase_price' => 85000,
                'selling_price' => 115000,
                'stock' => 14, // AMAN
                'fsn_category' => 'S',
                'safety_stock' => null,
                'rop' => 3,
                'supplier_id' => 3,
            ],
            [
                'id' => 12,
                'item_code' => 'BRG012',
                'item_name' => 'Rumah Roller Vario 125',
                'purchase_price' => 90000,
                'selling_price' => 120000,
                'stock' => 10, // AMAN
                'fsn_category' => 'S',
                'safety_stock' => null,
                'rop' => 2,
                'supplier_id' => 1,
            ],
            [
                'id' => 13,
                'item_code' => 'BRG013',
                'item_name' => 'Spion Honda Standar',
                'purchase_price' => 25000,
                'selling_price' => 35000,
                'stock' => 16, // AMAN
                'fsn_category' => 'S',
                'safety_stock' => null,
                'rop' => 4,
                'supplier_id' => 2,
            ],
            [
                'id' => 14,
                'item_code' => 'BRG014',
                'item_name' => 'Aki Kering GS Astra GTZ5S',
                'purchase_price' => 195000,
                'selling_price' => 245000,
                'stock' => 11, // AMAN
                'fsn_category' => 'S',
                'safety_stock' => null,
                'rop' => 3,
                'supplier_id' => 3,
            ],
            [
                'id' => 15,
                'item_code' => 'BRG015',
                'item_name' => 'Shockbreaker Belakang Beat',
                'purchase_price' => 190000,
                'selling_price' => 240000,
                'stock' => 9, // AMAN
                'fsn_category' => 'S',
                'safety_stock' => null,
                'rop' => 2,
                'supplier_id' => 4,
            ],

            // 5 Non Moving Items
            [
                'id' => 16,
                'item_code' => 'BRG016',
                'item_name' => 'Karburator Keihin PE28',
                'purchase_price' => 280000,
                'selling_price' => 360000,
                'stock' => 6, // on-demand
                'fsn_category' => 'N',
                'safety_stock' => null,
                'rop' => null,
                'supplier_id' => 2,
            ],
            [
                'id' => 17,
                'item_code' => 'BRG017',
                'item_name' => 'Kabel Gas RX King Original',
                'purchase_price' => 35000,
                'selling_price' => 50000,
                'stock' => 8, // on-demand
                'fsn_category' => 'N',
                'safety_stock' => null,
                'rop' => null,
                'supplier_id' => 3,
            ],
            [
                'id' => 18,
                'item_code' => 'BRG018',
                'item_name' => 'CDI Racing BRT Dual Band',
                'purchase_price' => 420000,
                'selling_price' => 530000,
                'stock' => 5, // on-demand
                'fsn_category' => 'N',
                'safety_stock' => null,
                'rop' => null,
                'supplier_id' => 4,
            ],
            [
                'id' => 19,
                'item_code' => 'BRG019',
                'item_name' => 'Knalpot Standar Supra Fit',
                'purchase_price' => 210000,
                'selling_price' => 275000,
                'stock' => 5, // on-demand
                'fsn_category' => 'N',
                'safety_stock' => null,
                'rop' => null,
                'supplier_id' => 1,
            ],
            [
                'id' => 20,
                'item_code' => 'BRG020',
                'item_name' => 'Spakbor Depan CB150R',
                'purchase_price' => 110000,
                'selling_price' => 150000,
                'stock' => 7, // on-demand
                'fsn_category' => 'N',
                'safety_stock' => null,
                'rop' => null,
                'supplier_id' => 2,
            ],
        ];

        foreach ($items as $data) {
            Item::create($data);
        }
    }
}
