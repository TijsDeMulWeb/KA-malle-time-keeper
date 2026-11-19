<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Rapport</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #171717; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 24px 0 8px; }
        .muted { color: #737373; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; color: #737373; border-bottom: 1px solid #d4d4d4; padding: 4px 6px; }
        td { padding: 4px 6px; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
        .right { text-align: right; }
        .tiles td { border: 0; padding: 0 12px 0 0; }
        .tile { border: 1px solid #d4d4d4; padding: 8px 10px; }
        .tile .value { font-size: 16px; font-weight: bold; }
        .dot { display: inline-block; width: 8px; height: 8px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Rapport</h1>
    <div class="muted">{{ $user->name }} · {{ $report->period()->label() }}</div>

    <h2>Samenvatting</h2>
    <table class="tiles">
        <tr>
            <td class="tile"><div class="muted">Totaal</div><div class="value">{{ App\Support\Duration::format($report->totalMinutes()) }}</div><div class="muted">{{ $report->count() }} registraties</div></td>
            <td class="tile"><div class="muted">Gemiddeld per week</div><div class="value">{{ App\Support\Duration::format($report->averageMinutesPerWeek()) }}</div></td>
        </tr>
    </table>

    <h2>Per project</h2>
    <table>
        <thead><tr><th>Project</th><th class="right">Duur</th><th class="right">%</th></tr></thead>
        <tbody>
            @foreach ($report->perProject() as $row)
                <tr>
                    <td><span class="dot" style="background: {{ $row['project']->color }}"></span> {{ $row['project']->name }}</td>
                    <td class="right">{{ App\Support\Duration::format($row['minutes']) }}</td>
                    <td class="right">{{ $row['percentage'] }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Per categorie</h2>
    <table>
        <thead><tr><th>Categorie</th><th class="right">Duur</th><th class="right">%</th></tr></thead>
        <tbody>
            @foreach ($report->perCategory() as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="right">{{ App\Support\Duration::format($row['minutes']) }}</td>
                    <td class="right">{{ $row['percentage'] }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Registraties</h2>
    <table>
        <thead><tr><th>Datum</th><th>Tijd</th><th>Project</th><th>Categorie</th><th>Omschrijving</th><th class="right">Duur</th></tr></thead>
        <tbody>
            @foreach ($report->entries() as $entry)
                <tr>
                    <td>{{ $entry->localStartedAt()->format('d/m/Y') }}</td>
                    <td>{{ $entry->localStartedAt()->format('H:i') }} – {{ $entry->localEndedAt()?->format('H:i') ?? '' }}</td>
                    <td>{{ $entry->project->name }}</td>
                    <td>{{ $entry->category?->name }}</td>
                    <td>{{ $entry->description }}</td>
                    <td class="right">{{ App\Support\Duration::format($entry->durationInMinutes()) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
