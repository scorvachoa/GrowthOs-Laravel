<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 13px; line-height: 1.6; color: #333; padding-top: 10px; }
        .header { text-align: center; margin-bottom: 24px; }
        .header img { max-height: 120px; margin-bottom: 8px; }
        .header h1 { font-size: 21px; color: {{ $company['primary_color'] }}; margin: 0; }
        .day-group { margin-bottom: 16px; page-break-inside: avoid; }
        .day-title { font-size: 15px; font-weight: bold; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin-bottom: 8px; }
        .task-row { margin-left: 13px; margin-bottom: 10px; }
        .task-time { font-weight: bold; font-size: 13px; color: #1f2937; }
        .task-title { font-size: 13px; }
        .task-channel { font-size: 12px; color: {{ $company['primary_color'] }}; font-weight: bold; }
        .task-link { font-size: 12px; font-style: italic; color: {{ $company['primary_color'] }}; }
        .no-tasks { font-size: 12px; color: #9ca3af; margin-left: 12px; }
        .extra-group { margin-left: 12px; margin-top: 8px; padding: 8px 10px; background: #f9fafb; border-left: 3px solid #6b7280; border-radius: 4px; }
        .extra-group-title { color: #6b7280; font-size: 12px; font-weight: bold; margin-bottom: 6px; }
        .extra-item { margin-bottom: 4px; }
        .observation-row { margin-left: 12px; margin-top: 8px; padding: 8px 10px; background: #f9fafb; border-left: 3px solid {{ $company['primary_color'] }}; border-radius: 4px; }
        .observation-row-title { color: {{ $company['primary_color'] }}; font-size: 12px; font-weight: bold; margin-bottom: 2px; }
        .observation-row-text { font-size: 12px; color: #6b7280; font-style: italic; }
        .footer { position: fixed; bottom: 20px; left: 0; right: 0; text-align: center; font-size: 10px; color: {{ $company['primary_color'] }}; }
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
        @php
            $videoTasks = array_filter($day['tasks'], fn($t) => $t['type'] === 'video');
            $extraTasks = array_filter($day['tasks'], fn($t) => $t['type'] === 'extra');
        @endphp
        <div class="day-group">
            <div class="day-title">{{ $day['date'] }}</div>
            @if(!empty($videoTasks))
                @foreach($videoTasks as $task)
                    <div class="task-row">
                        <div class="task-time">{{ $task['time_range'] }}</div>
                        @if($task['channel_name'] ?? false)
                            <div class="task-channel">{{ $task['channel_name'] }}</div>
                        @endif
                        <div class="task-title">{{ $task['title'] }} <span style="color:#888;font-size:11px;">({{ $task['status_label'] }})</span></div>
                        @if($task['youtube_url'])
                            <div class="task-link">{{ $task['youtube_url'] }}</div>
                        @endif
                    </div>
                @endforeach
            @endif
            @if(!empty($extraTasks))
                <div class="extra-group">
                    <div class="extra-group-title">Tareas Extras</div>
                    @foreach($extraTasks as $task)
                        <div class="extra-item">
                            <div class="task-time">{{ $task['time_range'] }}</div>
                            <div class="task-title">{{ $task['title'] }} <span style="color:#888;font-size:11px;">({{ $task['status_label'] }})</span></div>
                        </div>
                    @endforeach
                </div>
            @endif
            @if(empty($videoTasks) && empty($extraTasks))
                <div class="no-tasks">Sin tareas</div>
            @endif
            @if($day['observation'])
                <div class="observation-row">
                    <div class="observation-row-title">Observaciones</div>
                    <div class="observation-row-text">{{ $day['observation'] }}</div>
                </div>
            @endif
        </div>
    @empty
        <p style="text-align:center;color:#9ca3af;margin-top:40px;">No hay tareas para el rango solicitado</p>
    @endforelse

    <div class="footer">{{ $systemName }} &middot; Generado {{ $generatedAt }}</div>
</body>
</html>
