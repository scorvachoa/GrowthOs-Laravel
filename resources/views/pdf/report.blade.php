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
        .observation-row, .extra-row { margin-left: 12px; margin-top: 6px; padding: 6px 8px; background: #f9fafb; border-radius: 4px; }
        .observation-row { border-left: 3px solid {{ $company['primary_color'] }}; }
        .extra-row { border-left: 3px solid #6b7280; }
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
                    @if($task['type'] === 'extra')
                        <div class="extra-row">
                            <div class="task-time">{{ $task['time_range'] }}</div>
                            <div class="task-title">{{ $task['title'] }} <span style="color:#888;font-size:10px;">({{ $task['status_label'] }})</span></div>
                        </div>
                    @else
                        <div class="task-row">
                            <div class="task-time">{{ $task['time_range'] }}</div>
                            <div class="task-title">{{ $task['title'] }} <span style="color:#888;font-size:10px;">({{ $task['status_label'] }})</span></div>
                            @if($task['youtube_url'])
                                <div class="task-link">{{ $task['youtube_url'] }}</div>
                            @endif
                        </div>
                    @endif
                @endforeach
            @else
                <div class="no-tasks">Sin tareas</div>
            @endif
            @if($day['observation'])
                <div class="observation-row">
                    <div style="color:{{ $company['primary_color'] }};font-size:11px;font-weight:bold;margin-bottom:2px;">Observaciones</div>
                    <div style="font-size:11px;color:#6b7280;font-style:italic;">{{ $day['observation'] }}</div>
                </div>
            @endif
        </div>
    @empty
        <p style="text-align:center;color:#9ca3af;margin-top:40px;">No hay tareas para el rango solicitado</p>
    @endforelse

    <div class="footer">{{ $systemName }} &middot; Generado {{ $generatedAt }}</div>
</body>
</html>
