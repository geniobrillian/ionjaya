<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'id' => 1,
                'supplier_code' => 'SUP001',
                'supplier_name' => 'PT Astra Otoparts Distribusi',
                'address' => 'Jl. Raya Mojoagung No. 45, Jombang',
                'phone' => '081234567890',
                'lead_time' => 1,
            ],
            [
                'id' => 2,
                'supplier_code' => 'SUP002',
                'supplier_name' => 'CV Sumber Makmur Motor',
                'address' => 'Jl. KH Wahid Hasyim No. 12, Jombang',
                'phone' => '081298765432',
                'lead_time' => 1,
            ],
            [
                'id' => 3,
                'supplier_code' => 'SUP003',
                'supplier_name' => 'PT Dirgaputra Ekapratama',
                'address' => 'Jl. Mastrip No. 88, Surabaya',
                'phone' => '082155443322',
                'lead_time' => 1,
            ],
            [
                'id' => 4,
                'supplier_code' => 'SUP004',
                'supplier_name' => 'Distributor Bintang Mandiri',
                'address' => 'Jl. Basuki Rahmat No. 10, Mojokerto',
                'phone' => '085711223344',
                'lead_time' => 1,
            ],
        ];

        foreach ($suppliers as $data) {
            Supplier::create($data);
        }
    }
}
