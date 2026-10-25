<?php

namespace App\Http\Controllers;

use App\Services\FsnRopService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecommendationController extends Controller
{
    protected FsnRopService $fsnRopService;

    public function __construct(FsnRopService $fsnRopService)
    {
        $this->fsnRopService = $fsnRopService;
    }

    /**
     * Menampilkan daftar rekomendasi pengadaan barang.
     * Mengambil seluruh barang yang stoknya <= batas pemesanan.
     * Diurutkan prioritas: Fast Moving terlebih dahulu, lalu Slow Moving.
     */
    public function index(Request $request): View
    {
        $recommendations = $this->fsnRopService->getLowStockItems();

        return view('recommendations.index', compact('recommendations'));
    }
}
