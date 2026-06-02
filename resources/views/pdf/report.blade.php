<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; line-height: 1.6; color: #333; padding-top: 20px; }
        .header { text-align: center; margin-bottom: 24px; }
        .header img { max-height: 120px; margin-bottom: 8px; }
        .header h1 { font-size: 20px; color: {{ $company['primary_color'] }}; margin: 0; }
        .day-group { margin-bottom: 16px; page-break-inside: avoid; }
        .day-title { font-size: 14px; font-weight: bold; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin-bottom: 8px; }
        .task-row { margin-left: 13px; margin-bottom: 8px; }
        .task-time { font-weight: bold; font-size: 12px; color: #1f2937; }
        .task-title { font-size: 12px; }
        .task-link { font-size: 11px; font-style: italic; color: {{ $company['primary_color'] }}; }
        .no-tasks { font-size: 11px; color: #9ca3af; margin-left: 12px; }
        .footer { position: fixed; bottom: 20px; left: 0; right: 0; text-align: center; font-size: 9px; color: {{ $company['primary_color'] }}; }
    </style>
</head>
<body>
    <div class="header">
        @if($company['logo_base64'])
            <img src="{{ $company['logo_base64'] }}" alt="{{ $company['name'] }}" />
        @endif
        <h1>{{ $title }}</h1>
    </div>

    @forelse($days as $day)
        <div class="day-group">
            <div class="day-title">{{ $day['date'] }}</div>
            @if(!empty($day['tasks']))
                @foreach($day['tasks'] as $task)
                    <div class="task-row">
                        <div class="task-time">{{ $task['time_range'] }}</div>
                        <div class="task-title">{{ $task['title'] }} <span style="color:#888;font-size:10px;">({{ $task['status_label'] }})</span></div>
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

    <div class="footer">{{ $systemName }} &middot; Generado {{ $generatedAt }}</div>
</body>
</html>
