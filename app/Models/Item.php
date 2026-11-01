<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'fsn_category',
        'safety_stock',
        'rop',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'stock' => 'integer',
            'safety_stock' => 'integer',
            'rop' => 'integer',
        ];
    }

    /**
     * Pemasok-pemasok barang ini (many-to-many). Pivot hanya membawa
     * penanda pemasok_utama — tanpa harga dan tanpa lead time.
     */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'barang_pemasok', 'barang_id', 'pemasok_id')
            ->withPivot('pemasok_utama');
    }

    /**
     * Pemasok Utama barang ini (acuan lead time ROP dan tampilan),
     * atau null bila barang belum punya pemasok / belum ada yang
     * ditandai Utama. Utama = default, bukan wajib.
     */
    public function getPrimarySupplierAttribute(): ?Supplier
    {
        return $this->suppliers->first(fn ($supplier) => (bool) $supplier->pivot->pemasok_utama);
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

    // Mendapatkan batas pemesanan berdasarkan kategori FSN.
    // Fast dan Slow memakai ROP hasil hitung (Slow tanpa Safety Stock).
    public function getOrderThresholdAttribute(): int
    {
        if ($this->fsn_category === 'F' || $this->fsn_category === 'S') {
            return (int) ($this->rop ?? 0);
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

    // Status stok untuk tampilan (halaman Stok Barang).
    // Urutan penting: stok 0 selalu Habis (semua kategori), baru kemudian
    // Belum Dianalisis (belum pernah Terapkan), Non Moving berstok = Aman
    // (tidak pernah Menipis), dan Fast/Slow mengikuti is_low_stock.
    // Berbeda dengan is_low_stock (biner), status ini tidak pernah menyatakan
    // AMAN untuk barang yang belum dianalisis.
    public function getStockStatusAttribute(): string
    {
        if ($this->stock <= 0) {
            return 'habis';
        }

        if (is_null($this->fsn_category)) {
            return 'belum';
        }

        if ($this->fsn_category === 'N') {
            return 'aman';
        }

        return $this->is_low_stock ? 'menipis' : 'aman';
    }
}
