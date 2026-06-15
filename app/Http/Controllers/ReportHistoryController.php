<?php

namespace App\Http\Controllers;

use App\Models\ReportHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ReportHistoryController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 10), 5), 100);

        $histories = ReportHistory::query()
            ->with('user')
            ->when(
                $request->search,
                fn ($q, $search) => $q->where(function ($q) use ($search) {
                    $q->where('filename', 'like', "%{$search}%")
                      ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                })
            )
            ->when(
                $request->scope,
                fn ($q, $scope) => $q->where('scope', $scope)
            )
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($item) => [
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
            'filters' => $request->only(['search', 'scope', 'per_page']),
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
