<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_code',
        'supplier_name',
        'address',
        'phone',
        'lead_time',
    ];

    protected function casts(): array
    {
        return [
            'lead_time' => 'integer',
        ];
    }

    /**
     * Menghasilkan kode pemasok berikutnya (SUP001, SUP002, ...).
     * Mencari nomor terbesar dari kode yang ada, lalu memastikan
     * kode hasil tidak bertabrakan dengan data yang sudah tersimpan.
     */
    public static function generateNextCode(): string
    {
        $codes = self::pluck('supplier_code');
        $maxNumber = 0;

        foreach ($codes as $code) {
            if (preg_match('/^SUP(\d+)$/i', $code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        $nextNumber = $maxNumber + 1;

        do {
            $code = sprintf('SUP%03d', $nextNumber);
            $nextNumber++;
        } while (self::where('supplier_code', $code)->exists());

        return $code;
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
