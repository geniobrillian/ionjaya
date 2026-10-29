<?php

namespace App\Http\Controllers;

use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportController extends Controller
{
    /**
     * Halaman impor: penjelasan format + unduh template + unggah file.
     * Menyapu file temp impor yang lebih dari 24 jam.
     */
    public function index()
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('imports');
        foreach ($disk->files('imports') as $file) {
            if (time() - $disk->lastModified($file) > 86400) {
                $disk->delete($file);
            }
        }

        return view('imports.index');
    }

    /** Mengunduh template Excel impor (dibuat dari kode, selalu sesuai parser). */
    public function template(ImportService $service)
    {
        try {
            $spreadsheet = $service->buildTemplate();
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'Template-Impor-Data.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Menerima file, menjalankan validasi + dry-run, menampilkan pratinjau.
     * File disimpan sementara; bila validasi gagal, file langsung dihapus
     * (pengguna harus mengunggah ulang setelah memperbaiki file).
     */
    public function preview(Request $request, ImportService $service)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ], [
            'file.required' => 'Pilih file Excel (.xlsx) dulu.',
            'file.mimes' => 'File harus berformat Excel (.xlsx).',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ]);

        $importId = bin2hex(random_bytes(8)) . '.xlsx';
        $path = $request->file('file')->storeAs('imports', $importId);

        try {
            $result = $service->parseAndValidate(Storage::disk('local')->path($path));
        } catch (\RuntimeException $e) {
            Storage::disk('local')->delete($path);

            return back()->with('error', $e->getMessage());
        }

        if ($result['errors'] !== []) {
            Storage::disk('local')->delete($path);
            $importId = null;
        }

        return view('imports.preview', ['importId' => $importId, 'result' => $result]);
    }

    /**
     * Konfirmasi impor: membaca ulang file temp, memvalidasi ulang,
     * lalu mengimpor atomik. File temp selalu dihapus sesudahnya.
     */
    public function confirm(Request $request, ImportService $service)
    {
        $request->validate(['import_id' => ['required', 'string']]);

        $importId = basename($request->input('import_id'));
        $path = 'imports/' . $importId;
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            return redirect()->route('imports.index')->with('error', 'File impor sudah tidak tersedia. Unggah ulang.');
        }

        try {
            $result = $service->parseAndValidate($disk->path($path));
            if ($result['errors'] !== []) {
                return back()->with('error', 'Validasi gagal: ' . implode(' ', array_slice($result['errors'], 0, 3)));
            }
            $counts = $service->import($result, Auth::id());
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Impor gagal: ' . $e->getMessage());
        } finally {
            $disk->delete($path);
        }

        return redirect()->route('imports.result')->with('import_result', $counts);
    }

    /** Halaman hasil impor (hanya bisa dicapai lewat redirect setelah impor). */
    public function result()
    {
        $counts = session('import_result');
        if (! $counts) {
            return redirect()->route('imports.index');
        }

        return view('imports.result', compact('counts'));
    }
}
