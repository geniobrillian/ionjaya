<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Impor data dari file Excel (.xlsx) berformat sama dengan file simulasi:
 * sheet Data Barang, Stok Opname, Pembelian, dan Penjualan.
 *
 * Prinsip yang dijaga (sama dengan operasi harian):
 * - Harga transaksi SELALU dari harga master di sheet Data Barang.
 *   Kolom harga di sheet Pembelian/Penjualan diabaikan.
 * - Stok hanya berubah lewat transaksi: barang dibuat dengan stok opname
 *   (stok awal periode), lalu Pembelian menambah dan Penjualan mengurangi.
 *   Tidak ada edit stok manual di sini.
 * - Satu file diimpor dalam satu transaksi database: gagal satu baris
 *   berarti gagal semua (tidak ada impor setengah-setengah).
 * - Pratinjau (dry-run) memakai validasi yang sama dengan impor, jadi
 *   yang tampil di pratinjau persis yang akan masuk database.
 */
class ImportService
{
    /** Batas jumlah pesan error yang dikumpulkan (file besar bisa berisik). */
    private const MAX_ERRORS = 200;

    // ------------------------------------------------------------------
    // Template & library
    // ------------------------------------------------------------------

    private function ensureLibrary(): void
    {
        if (! class_exists(Spreadsheet::class)) {
            throw new \RuntimeException(
                'Library PhpSpreadsheet belum terpasang. Jalankan di laptop: ' .
                'composer require phpoffice/phpspreadsheet'
            );
        }
    }

    /**
     * Membangun file template impor (headers + 1 baris contoh per sheet).
     * Baris contoh WAJIB dihapus pengguna sebelum impor.
     */
    public function buildTemplate(): Spreadsheet
    {
        $this->ensureLibrary();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $petunjuk = $spreadsheet->createSheet();
        $petunjuk->setTitle('Petunjuk');
        $petunjuk->setCellValue('A1', 'TEMPLATE IMPOR DATA — Sistem Persediaan UD Ion Jaya Motor');
        $petunjuk->setCellValue('A2', '1. Isi sheet Data Barang, Stok Opname, Pembelian, dan Penjualan. Sheet Rekap tidak diimpor.');
        $petunjuk->setCellValue('A3', '2. HAPUS baris contoh di tiap sheet sebelum impor; baris contoh yang tertinggal ikut terimpor sebagai data.');
        $petunjuk->setCellValue('A4', '3. Kode barang dan kode pemasok harus unik dan konsisten antar sheet (huruf besar/kecil tidak dipermasalahkan).');
        $petunjuk->setCellValue('A5', '4. Harga di sheet Pembelian/Penjualan DIABAIKAN: transaksi selalu memakai harga master dari sheet Data Barang (1 pintu harga).');
        $petunjuk->setCellValue('A6', '5. Stok Awal = stok saat periode dimulai. Barang dibuat dengan stok ini, lalu transaksi mengubahnya (Pembelian +, Penjualan -).');
        $petunjuk->setCellValue('A7', '6. Stok Akhir (opsional): bila diisi, hasil impor diverifikasi — stok akhir hitung harus sama, kalau tidak impor ditolak.');
        $petunjuk->setCellValue('A8', '7. Penjualan yang membuat stok negatif MENGGAGALKAN seluruh impor (ditampilkan di pratinjau).');
        $petunjuk->setCellValue('A9', '8. Pemasok yang kodenya belum ada dibuat otomatis dari file (lead time dari Data Barang, atau 1 hari bila hanya muncul di Pembelian).');
        $petunjuk->setCellValue('A10', '9. Barang yang kodenya sudah ada di database DITOLAK (tidak ditimpa). Gunakan database kosong (migrate fresh tanpa seed) untuk impor awal.');
        $petunjuk->getColumnDimension('A')->setWidth(160);

        $this->templateSheet($spreadsheet, 'Data Barang',
            ['Kode Barang', 'Nama Barang', 'Kode Pemasok', 'Nama Pemasok', 'Lead Time (hari)', 'Harga Beli (Rp)', 'Harga Jual (Rp)'],
            ['BRG001', 'Oli Contoh (hapus baris ini)', 'SUP001', 'Pemasok Contoh', 1, 50000, 65000]);

        $this->templateSheet($spreadsheet, 'Stok Opname',
            ['Kode Barang', 'Nama Barang', 'Stok Awal', 'Stok Akhir'],
            ['BRG001', 'Oli Contoh (hapus baris ini)', 10, 12]);

        $this->templateSheet($spreadsheet, 'Pembelian',
            ['No. Pembelian', 'Tanggal', 'Waktu', 'Kode Barang', 'Nama Barang', 'Jumlah', 'Harga Beli (Rp)', 'Kode Pemasok', 'Nama Pemasok'],
            ['PBL-CONTOH', '2026-01-05', '09:00', 'BRG001', 'Oli Contoh (hapus baris ini)', 10, 50000, 'SUP001', 'Pemasok Contoh']);

        $this->templateSheet($spreadsheet, 'Penjualan',
            ['No. Transaksi', 'Tanggal', 'Waktu', 'Kode Barang', 'Nama Barang', 'Jumlah', 'Harga Jual (Rp)'],
            ['TRJ-CONTOH', '2026-01-05', '10:30', 'BRG001', 'Oli Contoh (hapus baris ini)', 2, 65000]);

        $spreadsheet->setActiveSheetIndexByName('Petunjuk');

        return $spreadsheet;
    }

    private function templateSheet(Spreadsheet $spreadsheet, string $title, array $headers, array $example): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);
        $sheet->fromArray([$headers, $example], null, 'A1');
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    // ------------------------------------------------------------------
    // Membaca file
    // ------------------------------------------------------------------

    /**
     * Membaca file .xlsx menjadi baris-baris mentah per sheet.
     * Mengembalikan ['sheets' => [...], 'errors' => [...]].
     */
    public function parseFile(string $path): array
    {
        $this->ensureLibrary();

        $errors = [];
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        } catch (\Throwable $e) {
            return ['sheets' => [], 'errors' => ['File tidak bisa dibaca sebagai Excel (.xlsx): ' . $e->getMessage()]];
        }

        $sheets = [];
        foreach ($spreadsheet->getSheetNames() as $name) {
            $key = mb_strtolower(trim($name));
            $rows = $spreadsheet->getSheetByName($name)->toArray(null, true, false);
            $sheets[$key] = $rows;
        }

        return ['sheets' => $sheets, 'errors' => $errors];
    }

    private function findSheet(array $sheets, string $wanted): ?array
    {
        $wanted = mb_strtolower(trim($wanted));

        return $sheets[$wanted] ?? null;
    }

    /** Normalisasi teks header: huruf kecil, spasi dirapikan. */
    private function normHeader(mixed $value): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim((string) $value)));
    }

    /**
     * Memetakan header sheet ke kolom. $required berisi kunci awalan
     * (mis. 'stok awal' cocok dengan 'Stok Awal (1 Jan 2026)' maupun 'Stok Awal').
     * Mengembalikan [kunci => indeks kolom] atau null bila ada yang hilang.
     */
    private function mapHeaders(array $headerRow, array $required, string $sheetLabel, array &$errors): ?array
    {
        $normalized = array_map(fn ($h) => $this->normHeader($h), $headerRow);
        $map = [];

        foreach ($required as $key) {
            $found = null;
            foreach ($normalized as $idx => $header) {
                if ($header !== '' && str_starts_with($header, $key)) {
                    $found = $idx;
                    break;
                }
            }
            if ($found === null) {
                $this->addError($errors, "Sheet {$sheetLabel}: kolom '{$key}' tidak ditemukan di baris header.");
            } else {
                $map[$key] = $found;
            }
        }

        return count($map) === count($required) ? $map : null;
    }

    private function addError(array &$errors, string $message): void
    {
        if (count($errors) < self::MAX_ERRORS) {
            $errors[] = $message;
        }
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_numeric($value) && (float) $value >= 1) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
        if (is_string($value) && trim($value) !== '') {
            $text = trim($value);
            // Format garis miring dibaca sebagai d/m/Y (konvensi Indonesia),
            // karena strtotime akan salah membacanya sebagai m/d/Y.
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $text, $m)) {
                return checkdate((int) $m[2], (int) $m[1], (int) $m[3])
                    ? sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1])
                    : null;
            }
            $t = strtotime($text);

            return $t === false ? null : date('Y-m-d', $t);
        }

        return null;
    }

    private function parseTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }
        if (is_numeric($value) && (float) $value >= 0 && (float) $value < 1) {
            $seconds = (int) round((float) $value * 86400);

            return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
        }
        if (is_string($value) && preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($value), $m)) {
            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], isset($m[3]) ? (int) $m[3] : 0);
        }

        return null;
    }

    private function parseQty(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }
        $float = (float) $value;

        return $float > 0 && $float == (int) $float ? (int) $float : null;
    }

    private function parseMoney(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value >= 0 ? (float) $value : null;
    }

    private function normCode(mixed $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    // ------------------------------------------------------------------
    // Validasi + dry-run (dipakai pratinjau DAN impor ulang saat konfirmasi)
    // ------------------------------------------------------------------

    /**
     * Memvalidasi file yang sudah dibaca. Mengembalikan array:
     * suppliers, items, opening, transactions (terurut kronologis),
     * errors, infos, summary.
     */
    public function parseAndValidate(string $path): array
    {
        $parsed = $this->parseFile($path);
        $errors = $parsed['errors'];
        $infos = [];
        $sheets = $parsed['sheets'];

        $suppliers = [];
        $items = [];
        $opening = [];
        $transactions = [];

        // ---- Sheet Data Barang -> pemasok + barang ----
        $itemsSheet = $this->findSheet($sheets, 'Data Barang');
        if ($itemsSheet === null) {
            $this->addError($errors, "Sheet 'Data Barang' tidak ditemukan.");
        } else {
            $headerRow = $itemsSheet[0] ?? [];
            $map = $this->mapHeaders($headerRow, ['kode barang', 'nama barang', 'kode pemasok', 'nama pemasok', 'lead time', 'harga beli', 'harga jual'], 'Data Barang', $errors);
            if ($map !== null) {
                foreach (array_slice($itemsSheet, 1) as $i => $row) {
                    if ($this->isEmptyRow($row)) {
                        continue;
                    }
                    $excelRow = $i + 2;
                    $code = $this->normCode($row[$map['kode barang']] ?? '');
                    $name = trim((string) ($row[$map['nama barang']] ?? ''));
                    $supCode = $this->normCode($row[$map['kode pemasok']] ?? '');
                    $supName = trim((string) ($row[$map['nama pemasok']] ?? ''));
                    $leadTime = $this->parseQty($row[$map['lead time']] ?? null);
                    $buy = $this->parseMoney($row[$map['harga beli']] ?? null);
                    $sell = $this->parseMoney($row[$map['harga jual']] ?? null);

                    if ($code === '') {
                        $this->addError($errors, "Data Barang baris {$excelRow}: kode barang kosong.");
                        continue;
                    }
                    if (isset($items[$code])) {
                        $this->addError($errors, "Data Barang baris {$excelRow}: kode barang {$code} duplikat di file.");
                        continue;
                    }
                    if ($name === '') {
                        $this->addError($errors, "Data Barang baris {$excelRow} ({$code}): nama barang kosong.");
                        continue;
                    }
                    if ($supCode === '') {
                        $this->addError($errors, "Data Barang baris {$excelRow} ({$code}): kode pemasok kosong.");
                        continue;
                    }
                    if ($leadTime === null) {
                        $this->addError($errors, "Data Barang baris {$excelRow} ({$code}): lead time harus bilangan bulat >= 1.");
                        continue;
                    }
                    if ($buy === null || $sell === null) {
                        $this->addError($errors, "Data Barang baris {$excelRow} ({$code}): harga beli/jual harus angka >= 0.");
                        continue;
                    }

                    $items[$code] = [
                        'item_code' => $code,
                        'item_name' => $name,
                        'supplier_code' => $supCode,
                        'purchase_price' => $buy,
                        'selling_price' => $sell,
                    ];
                    if (! isset($suppliers[$supCode])) {
                        $suppliers[$supCode] = ['supplier_code' => $supCode, 'supplier_name' => $supName, 'lead_time' => $leadTime];
                    } elseif ($suppliers[$supCode]['lead_time'] !== $leadTime) {
                        $this->addError($errors, "Data Barang: pemasok {$supCode} punya lead time berbeda-beda ({$suppliers[$supCode]['lead_time']} vs {$leadTime}).");
                    }
                }
            }
        }

        // ---- Sheet Stok Opname ----
        $openingSheet = $this->findSheet($sheets, 'Stok Opname');
        if ($openingSheet === null) {
            $this->addError($errors, "Sheet 'Stok Opname' tidak ditemukan.");
        } else {
            $headerRow = $openingSheet[0] ?? [];
            $map = $this->mapHeaders($headerRow, ['kode barang', 'stok awal'], 'Stok Opname', $errors);
            $closingIdx = null;
            if ($map !== null) {
                foreach ($headerRow as $idx => $header) {
                    if (str_starts_with($this->normHeader($header), 'stok akhir')) {
                        $closingIdx = $idx;
                        break;
                    }
                }
                foreach (array_slice($openingSheet, 1) as $i => $row) {
                    if ($this->isEmptyRow($row)) {
                        continue;
                    }
                    $excelRow = $i + 2;
                    $code = $this->normCode($row[$map['kode barang']] ?? '');
                    $start = $this->parseQty($row[$map['stok awal']] ?? null);
                    // Stok awal 0 sah (barang baru); parseQty menolak 0, jadi tangani khusus.
                    if ($start === null && is_numeric($row[$map['stok awal']] ?? null) && (float) $row[$map['stok awal']] == 0) {
                        $start = 0;
                    }
                    if ($code === '') {
                        $this->addError($errors, "Stok Opname baris {$excelRow}: kode barang kosong.");
                        continue;
                    }
                    if (! isset($items[$code])) {
                        $this->addError($errors, "Stok Opname baris {$excelRow}: kode {$code} tidak ada di sheet Data Barang.");
                        continue;
                    }
                    if ($start === null) {
                        $this->addError($errors, "Stok Opname baris {$excelRow} ({$code}): stok awal harus bilangan bulat >= 0.");
                        continue;
                    }
                    $closing = null;
                    if ($closingIdx !== null && is_numeric($row[$closingIdx] ?? null)) {
                        $closing = (int) $row[$closingIdx];
                    }
                    $opening[$code] = ['opening_stock' => $start, 'closing_stock' => $closing];
                }
            }
        }

        // ---- Sheet Pembelian + Penjualan -> grup transaksi ----
        $transactions = array_merge(
            $this->parseTransactionSheet($sheets, 'Pembelian', 'pembelian', $items, $errors, $suppliers),
            $this->parseTransactionSheet($sheets, 'Penjualan', 'penjualan', $items, $errors, $suppliers),
        );

        // Urutan kronologis: tanggal, jam (kosong = 00:00:00), pembelian
        // didahulukan atas penjualan pada stempel waktu yang sama, lalu
        // urutan kemunculan di file.
        usort($transactions, function ($a, $b) {
            $cmp = strcmp($a['date'], $b['date']);
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcmp($a['time'] ?? '00:00:00', $b['time'] ?? '00:00:00');
            if ($cmp !== 0) {
                return $cmp;
            }
            $order = ['pembelian' => 0, 'penjualan' => 1];
            $cmp = $order[$a['type']] <=> $order[$b['type']];
            if ($cmp !== 0) {
                return $cmp;
            }

            return $a['seq'] <=> $b['seq'];
        });

        // ---- Cek terhadap database ----
        if ($items !== []) {
            $existingItems = Item::whereIn('item_code', array_keys($items))->pluck('item_code')->all();
            foreach ($existingItems as $code) {
                $this->addError($errors, "Barang {$code} sudah ada di database (tidak ditimpa). Hapus dulu atau pakai database kosong.");
            }
            $existingSuppliers = Supplier::whereIn('supplier_code', array_keys($suppliers))->pluck('supplier_code')->all();
            foreach ($existingSuppliers as $code) {
                $infos[] = "Pemasok {$code} sudah ada di database, dipakai yang sudah ada (nama/lead time file diabaikan).";
            }
        }

        // ---- Simulasi stok (hanya bila sejauh ini bersih) ----
        if ($errors === []) {
            $simErrors = $this->simulate($items, $opening, $transactions);
            foreach ($simErrors as $e) {
                $this->addError($errors, $e);
            }
        }

        foreach (array_keys($items) as $code) {
            if (! isset($opening[$code])) {
                $infos[] = "Barang {$code} tidak punya baris Stok Opname, dimulai dari stok 0.";
            }
        }

        $summary = [
            'suppliers_new' => count(array_filter(array_keys($suppliers), fn ($c) => ! Supplier::where('supplier_code', $c)->exists())),
            'suppliers_total' => count($suppliers),
            'items' => count($items),
            'opening_rows' => count($opening),
            'purchase_groups' => count(array_filter($transactions, fn ($t) => $t['type'] === 'pembelian')),
            'purchase_lines' => array_sum(array_map(fn ($t) => $t['type'] === 'pembelian' ? count($t['lines']) : 0, $transactions)),
            'sale_groups' => count(array_filter($transactions, fn ($t) => $t['type'] === 'penjualan')),
            'sale_lines' => array_sum(array_map(fn ($t) => $t['type'] === 'penjualan' ? count($t['lines']) : 0, $transactions)),
        ];

        return [
            'suppliers' => $suppliers,
            'items' => $items,
            'opening' => $opening,
            'transactions' => $transactions,
            'errors' => $errors,
            'infos' => $infos,
            'summary' => $summary,
        ];
    }

    /**
     * Membaca satu sheet transaksi menjadi grup-grup transaksi.
     * Pembelian dikelompokkan per No. Pembelian (atau per tanggal+pemasok
     * bila nomor kosong); Penjualan per No. Transaksi (atau per baris
     * bila nomor kosong, agar transaksi pelanggan tidak salah gabung).
     */
    private function parseTransactionSheet(array $sheets, string $sheetName, string $type, array $items, array &$errors, array &$suppliers): array
    {
        $label = $sheetName;
        $sheet = $this->findSheet($sheets, $sheetName);
        if ($sheet === null) {
            $this->addError($errors, "Sheet '{$label}' tidak ditemukan.");

            return [];
        }

        $headerRow = $sheet[0] ?? [];
        $numberKey = $type === 'pembelian' ? 'no. pembelian' : 'no. transaksi';
        $map = $this->mapHeaders($headerRow, ['tanggal', 'kode barang', 'jumlah'], $label, $errors);
        if ($map === null) {
            return [];
        }
        $numberIdx = null;
        $timeIdx = null;
        $supCodeIdx = null;
        $supNameIdx = null;
        foreach ($headerRow as $idx => $header) {
            $norm = $this->normHeader($header);
            if ($numberIdx === null && str_starts_with($norm, $numberKey)) {
                $numberIdx = $idx;
            }
            if ($timeIdx === null && $norm === 'waktu') {
                $timeIdx = $idx;
            }
            if ($supCodeIdx === null && $norm === 'kode pemasok') {
                $supCodeIdx = $idx;
            }
            if ($supNameIdx === null && $norm === 'nama pemasok') {
                $supNameIdx = $idx;
            }
        }

        $groups = [];
        $order = [];
        $seq = 0;
        foreach (array_slice($sheet, 1) as $i => $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }
            $excelRow = $i + 2;
            $code = $this->normCode($row[$map['kode barang']] ?? '');
            $qty = $this->parseQty($row[$map['jumlah']] ?? null);
            $date = $this->parseDate($row[$map['tanggal']] ?? null);
            $number = $numberIdx !== null ? trim((string) ($row[$numberIdx] ?? '')) : '';
            $time = $timeIdx !== null ? $this->parseTime($row[$timeIdx] ?? null) : null;

            if ($code === '') {
                $this->addError($errors, "{$label} baris {$excelRow}: kode barang kosong.");
                continue;
            }
            if (! isset($items[$code])) {
                $this->addError($errors, "{$label} baris {$excelRow}: kode {$code} tidak ada di sheet Data Barang.");
                continue;
            }
            if ($qty === null) {
                $this->addError($errors, "{$label} baris {$excelRow} ({$code}): jumlah harus bilangan bulat > 0.");
                continue;
            }
            if ($date === null) {
                $this->addError($errors, "{$label} baris {$excelRow} ({$code}): tanggal tidak bisa dibaca.");
                continue;
            }

            // Pemasok dari sheet Pembelian ikut didaftarkan (lead time 1
            // bila pemasok itu tidak muncul di Data Barang).
            if ($type === 'pembelian' && $supCodeIdx !== null) {
                $supCode = $this->normCode($row[$supCodeIdx] ?? '');
                if ($supCode !== '' && ! isset($suppliers[$supCode])) {
                    $suppliers[$supCode] = [
                        'supplier_code' => $supCode,
                        'supplier_name' => trim((string) ($row[$supNameIdx] ?? '')),
                        'lead_time' => 1,
                    ];
                }
            }

            if ($number !== '') {
                $key = $type . '|' . $number;
            } elseif ($type === 'pembelian' && $supCodeIdx !== null && $this->normCode($row[$supCodeIdx] ?? '') !== '') {
                $key = $type . '|' . $date . '|' . $this->normCode($row[$supCodeIdx] ?? '');
            } else {
                $key = $type . '|row-' . $excelRow;
            }

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'number' => $number !== '' ? $number : null,
                    'type' => $type,
                    'date' => $date,
                    'time' => $time,
                    'lines' => [],
                    'seq' => $seq++,
                ];
                $order[] = $key;
            }
            $groups[$key]['lines'][] = ['item_code' => $code, 'quantity' => $qty, 'excel_row' => $excelRow];
        }

        $result = [];
        foreach ($order as $key) {
            $result[] = $groups[$key];
        }

        return $result;
    }

    /**
     * Simulasi stok di memori: mulai dari stok opname (default 0),
     * terapkan transaksi terurut. Mengembalikan daftar error
     * (stok negatif / stok akhir tidak cocok).
     */
    private function simulate(array $items, array $opening, array $transactions): array
    {
        $errors = [];
        $stocks = [];
        foreach (array_keys($items) as $code) {
            $stocks[$code] = $opening[$code]['opening_stock'] ?? 0;
        }

        foreach ($transactions as $tx) {
            foreach ($tx['lines'] as $line) {
                $code = $line['item_code'];
                if ($tx['type'] === 'pembelian') {
                    $stocks[$code] += $line['quantity'];
                } else {
                    $stocks[$code] -= $line['quantity'];
                    if ($stocks[$code] < 0) {
                        $num = $tx['number'] ?? '(tanpa nomor)';
                        $errors[] = "Stok {$code} negatif pada penjualan {$num} tanggal {$tx['date']} (butuh {$line['quantity']}, tersedia " . ($stocks[$code] + $line['quantity']) . ').';
                    }
                }
            }
        }

        foreach ($opening as $code => $op) {
            if ($op['closing_stock'] !== null && isset($stocks[$code]) && $stocks[$code] !== $op['closing_stock']) {
                $errors[] = "Stok akhir {$code} tidak cocok: hitung {$stocks[$code]}, tertulis di Stok Opname {$op['closing_stock']}.";
            }
        }

        return $errors;
    }

    // ------------------------------------------------------------------
    // Impor atomik
    // ------------------------------------------------------------------

    /**
     * Menulis data tervalidasi ke database dalam satu transaksi.
     * Mengembalikan hitungan yang berhasil diimpor.
     * Melempar RuntimeException bila ada pelanggaran (rollback penuh).
     */
    public function import(array $validated, int $userId): array
    {
        return DB::transaction(function () use ($validated, $userId) {
            // Pemeriksaan ulang di dalam transaksi (data bisa berubah
            // antara pratinjau dan konfirmasi).
            $existing = Item::whereIn('item_code', array_keys($validated['items']))->pluck('item_code')->all();
            if ($existing !== []) {
                throw new \RuntimeException('Barang sudah ada di database: ' . implode(', ', $existing));
            }
            $simErrors = $this->simulate($validated['items'], $validated['opening'], $validated['transactions']);
            if ($simErrors !== []) {
                throw new \RuntimeException(implode(' ', array_slice($simErrors, 0, 3)));
            }

            // Pemasok: yang sudah ada dipakai, yang baru dibuat.
            $supplierIds = [];
            $newSupplierCount = 0;
            foreach ($validated['suppliers'] as $code => $sup) {
                $supplier = Supplier::firstOrCreate(
                    ['supplier_code' => $code],
                    ['supplier_name' => $sup['supplier_name'] !== '' ? $sup['supplier_name'] : $code, 'lead_time' => $sup['lead_time']]
                );
                $supplierIds[$code] = $supplier->id;
                if ($supplier->wasRecentlyCreated) {
                    $newSupplierCount++;
                }
            }

            // Barang dengan stok opname (default 0 bila tanpa baris opname).
            $itemModels = [];
            foreach ($validated['items'] as $code => $item) {
                $itemModels[$code] = Item::create([
                    'item_code' => $code,
                    'item_name' => $item['item_name'],
                    'supplier_id' => $supplierIds[$item['supplier_code']],
                    'purchase_price' => $item['purchase_price'],
                    'selling_price' => $item['selling_price'],
                    'stock' => $validated['opening'][$code]['opening_stock'] ?? 0,
                    'fsn_category' => null,
                    'safety_stock' => null,
                    'rop' => null,
                ]);
            }

            // Transaksi kronologis, harga dari master, stok ikut aturan
            // yang sama dengan operasi harian.
            $purchaseCount = 0;
            $saleCount = 0;
            foreach ($validated['transactions'] as $tx) {
                $total = 0;
                $details = [];
                foreach ($tx['lines'] as $line) {
                    $model = $itemModels[$line['item_code']];
                    $price = $tx['type'] === 'pembelian' ? (float) $model->purchase_price : (float) $model->selling_price;
                    $subtotal = $line['quantity'] * $price;
                    $total += $subtotal;
                    $details[] = ['item' => $model, 'quantity' => $line['quantity'], 'unit_price' => $price, 'subtotal' => $subtotal];
                }

                $transaction = Transaction::create([
                    'date' => $tx['date'],
                    'time' => $tx['time'],
                    'transaction_type' => $tx['type'],
                    'total' => $total,
                    'user_id' => $userId,
                ]);

                foreach ($details as $detail) {
                    TransactionDetail::create([
                        'transaction_id' => $transaction->id,
                        'item_id' => $detail['item']->id,
                        'quantity' => $detail['quantity'],
                        'unit_price' => $detail['unit_price'],
                        'subtotal' => $detail['subtotal'],
                    ]);
                    if ($tx['type'] === 'pembelian') {
                        $detail['item']->increment('stock', $detail['quantity']);
                    } else {
                        $detail['item']->decrement('stock', $detail['quantity']);
                    }
                }

                if ($tx['type'] === 'pembelian') {
                    $purchaseCount++;
                } else {
                    $saleCount++;
                }
            }

            // Verifikasi akhir (bila Stok Akhir diisi di file).
            foreach ($validated['opening'] as $code => $op) {
                if ($op['closing_stock'] !== null) {
                    $actual = (int) $itemModels[$code]->fresh()->stock;
                    if ($actual !== $op['closing_stock']) {
                        throw new \RuntimeException("Stok akhir {$code} tidak cocok: hitung {$actual}, tertulis {$op['closing_stock']}.");
                    }
                }
            }

            return [
                'suppliers' => count($validated['suppliers']),
                'suppliers_new' => $newSupplierCount,
                'items' => count($validated['items']),
                'purchases' => $purchaseCount,
                'sales' => $saleCount,
            ];
        });
    }
}
