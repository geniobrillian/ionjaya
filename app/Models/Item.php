<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_code',
        'item_name',
        'purchase_price',
        'selling_price',
        'stock',
        'unit',
        'fsn_category',
        'safety_stock',
        'rop',
        'minimum_stock',
        'supplier_id',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'stock' => 'integer',
            'safety_stock' => 'integer',
            'rop' => 'integer',
            'minimum_stock' => 'integer',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function transactionDetails(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    /**
     * Menghasilkan kode barang berikutnya secara otomatis (auto-increment).
     * Contoh: BRG001 -> BRG002 -> BRG021, dst.
     */
    public static function generateNextCode(): string
    {
        $codes = self::pluck('item_code');
        $maxNumber = 0;

        foreach ($codes as $code) {
            if (preg_match('/^BRG(\d+)$/i', $code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        $nextNumber = $maxNumber + 1;

        do {
            $code = sprintf('BRG%03d', $nextNumber);
            $nextNumber++;
        } while (self::where('item_code', $code)->exists());

        return $code;
    }

    // Mendapatkan batas pemesanan berdasarkan kategori FSN
    public function getOrderThresholdAttribute(): int
    {
        if ($this->fsn_category === 'F') {
            return (int) ($this->rop ?? 0);
        }

        if ($this->fsn_category === 'S') {
            return (int) ($this->minimum_stock ?? 0);
        }

        return 0; // Kategori N (on-demand, tidak ada batas pemesanan)
    }

    // Mengecek apakah stok barang sedang menipis
    public function getIsLowStockAttribute(): bool
    {
        // Kategori Non-Moving tidak memiliki indikator menipis (on-demand)
        if ($this->fsn_category === 'N') {
            return false;
        }

        $threshold = $this->order_threshold;
        return $threshold > 0 && $this->stock <= $threshold;
    }
}
