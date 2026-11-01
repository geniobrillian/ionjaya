<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    /**
     * Barang-barang yang dipasok pemasok ini (many-to-many).
     * Dipakai juga oleh guard hapus pemasok: exists() tetap jalan
     * untuk relasi pivot, jadi pemasok ber-relasi tetap tidak bisa
     * dihapus.
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'barang_pemasok', 'pemasok_id', 'barang_id')
            ->withPivot('pemasok_utama');
    }
}
