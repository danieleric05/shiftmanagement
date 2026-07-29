<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rapport de remplissage des Shifts</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        p.meta { color: #6b7280; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 10px; text-align: left; }
        th { background-color: #f3f4f6; }
        .complet { color: #15803d; }
        .vacant { color: #b45309; }
    </style>
</head>
<body>
    <h1>Rapport de remplissage des Shifts</h1>
    <p class="meta">Généré le {{ $genereLe }}</p>

    <table>
        <thead>
            <tr>
                <th>Shift</th>
                <th>Jour</th>
                <th>Postes</th>
                <th>Postes vacants</th>
                <th>Taux de remplissage</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($shifts as $shift)
                <tr>
                    <td>{{ $shift['nom'] }}</td>
                    <td>{{ ucfirst($shift['jour']) }}</td>
                    <td>{{ $shift['postes_total'] }}</td>
                    <td>{{ $shift['postes_vacants'] }}</td>
                    <td class="{{ $shift['postes_vacants'] === 0 ? 'complet' : 'vacant' }}">
                        {{ $shift['taux_remplissage'] !== null ? $shift['taux_remplissage'].'%' : '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Aucun Shift pour le moment.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
