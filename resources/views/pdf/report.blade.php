<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #333; }
        h1 { font-size: 18px; color: #4f46e5; margin-bottom: 4px; }
        .subtitle { font-size: 10px; color: #888; margin-bottom: 20px; }
        .day-group { margin-bottom: 16px; page-break-inside: avoid; }
        .day-title { font-size: 13px; font-weight: bold; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin-bottom: 8px; }
        .task-row { margin-left: 12px; margin-bottom: 6px; }
        .task-time { font-weight: bold; font-size: 10px; color: #4f46e5; }
        .task-title { font-size: 11px; }
        .task-link { font-size: 9px; color: #14b8a6; }
        .no-tasks { font-size: 10px; color: #9ca3af; margin-left: 12px; }
        .footer { position: fixed; bottom: 20px; left: 0; right: 0; text-align: center; font-size: 8px; color: #14b8a6; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="subtitle">Generado {{ $generatedAt }}</p>

    @forelse($days as $day)
        <div class="day-group">
            <div class="day-title">{{ $day['date'] }}</div>
            @if(!empty($day['tasks']))
                @foreach($day['tasks'] as $task)
                    <div class="task-row">
                        <div class="task-time">{{ $task['time_range'] }}</div>
                        <div class="task-title">{{ $task['title'] }} <span style="color:#888;font-size:9px;">({{ $task['status_label'] }})</span></div>
                        @if($task['youtube_url'])
                            <div class="task-link">{{ $task['youtube_url'] }}</div>
                        @endif
                    </div>
                @endforeach
            @else
                <div class="no-tasks">Sin tareas</div>
            @endif
        </div>
    @empty
        <p style="text-align:center;color:#9ca3af;margin-top:40px;">No hay tareas para el rango solicitado</p>
    @endforelse

    <div class="footer">GrowthOS &middot; Reporte generado {{ $generatedAt }}</div>
</body>
</html>
