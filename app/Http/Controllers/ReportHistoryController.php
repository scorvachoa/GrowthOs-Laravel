<?php

namespace App\Http\Controllers;

use App\Models\ReportHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ReportHistoryController extends Controller
{
    public function index()
    {
        $histories = ReportHistory::query()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'user_name' => $item->user?->name ?? '-',
                'scope' => $item->scope,
                'filename' => $item->filename,
                'filters' => $item->filters_json,
                'has_file' => $item->fileExists(),
                'created_at' => $item->created_at->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Reports/History', [
            'histories' => $histories,
        ]);
    }

    public function download(ReportHistory $reportHistory)
    {
        $filePath = 'reports/' . $reportHistory->filename;

        if (!Storage::disk('public')->exists($filePath)) {
            return redirect()
                ->route('report-history.index')
                ->with('error', 'El archivo PDF ya no está disponible. Genera un nuevo reporte.');
        }

        return Storage::disk('public')->download($filePath, $reportHistory->filename);
    }

    public function destroy(ReportHistory $reportHistory)
    {
        $filePath = 'reports/' . $reportHistory->filename;
        if (Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }

        $reportHistory->delete();

        return redirect()
            ->route('report-history.index')
            ->with('error', 'Reporte eliminado correctamente.');
    }
}
