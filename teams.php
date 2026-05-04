<?php
define('APP_RUNNING', true);
require_once 'common.php';
require_once 'TeamBuilder.php';
require_once __DIR__ . '/fpdf/fpdf.php';

// When the form is submitted: save present selection to DB, then redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['voetballer_id'])) {
    $selected = array_values(array_filter(array_map('intval', $_POST['voetballer_id'])));
    $pdo->exec('UPDATE voetballers SET present = 0');
    if (!empty($selected)) {
        $placeholders = implode(',', array_fill(0, count($selected), '?'));
        $stmt = $pdo->prepare("UPDATE voetballers SET present = 1 WHERE id IN ($placeholders)");
        $stmt->execute($selected);
    }
    header('Location: teams.php');
    exit;
}

// Read present players from DB
$ids = $pdo->query('SELECT id FROM voetballers WHERE present = 1')->fetchAll(PDO::FETCH_COLUMN);

if (count($ids) < 2) {
    die('Selecteer minimaal 2 voetballers.');
}

$builder     = new TeamBuilder($pdo, $ids);
$teams       = $builder->build();
$aantalTeams = $builder->getAantalTeams();

// --- PDF output ---
if (isset($_GET['pdf'])) {
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Teams van vandaag', 0, 1);
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(0, 5, 'Datum: ' . date('d-m-Y'), 0, 1);
    $pdf->Ln(4);

    $colW = 180 / $aantalTeams;
    // Team headers
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(220, 220, 220);
    foreach ($teams as $nummer => $leden) {
        $pdf->Cell($colW, 7, 'Team ' . $nummer . ' (' . count($leden) . ' spelers)', 1, 0, 'C', true);
    }
    $pdf->Ln();

    // Player rows — find max team size
    $maxSize = max(array_map('count', $teams));
    $pdf->SetFont('Arial', '', 9);
    for ($i = 0; $i < $maxSize; $i++) {
        foreach ($teams as $leden) {
            $naam = isset($leden[$i])
                ? $leden[$i]['voetballer'] . ' [' . $leden[$i]['team_groep'] . ']'
                : '';
            $pdf->Cell($colW, 6, $naam, 1, 0, 'L');
        }
        $pdf->Ln();
    }

    $pdf->Output('I', 'teams-' . date('Ymd') . '.pdf');
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WF – Teams</title>
    <style>
        body { font-family: sans-serif; max-width: 700px; margin: 2rem auto; }
        h1 { font-size: 1.4rem; }
        .teams { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .team { border: 1px solid #ccc; border-radius: 6px; padding: 1rem; }
        .team h2 { margin-top: 0; font-size: 1.1rem; }
        .team ul { list-style: none; padding: 0; margin: 0; }
        .team li { padding: 0.3rem 0; border-bottom: 1px solid #eee; font-size: 0.95rem; }
        .team li:last-child { border-bottom: none; }
        .groep { font-size: 0.75rem; color: #888; margin-left: 0.4rem; }
        .acties { margin-top: 1.5rem; display: flex; gap: 1rem; align-items: center; }
        .acties button { padding: 0.45rem 1rem; font-size: 0.95rem; cursor: pointer; }
        .acties a { text-decoration: none; }
    </style>
</head>
<body>
    <h1>Teams van vandaag <?= count($ids) < 15 ? '(2 teams)' : '(4 teams)' ?></h1>
    <div class="teams" style="grid-template-columns: <?= $aantalTeams === 2 ? '1fr 1fr' : '1fr 1fr' ?>">
        <?php foreach ($teams as $nummer => $leden): ?>
        <div class="team">
            <h2>Team <?= $nummer ?> <small>(<?= count($leden) ?> spelers)</small></h2>
            <ul>
                <?php foreach ($leden as $speler): ?>
                <li>
                    <?= htmlspecialchars($speler['voetballer']) ?>
                    <span class="groep">[<?= htmlspecialchars($speler['team_groep']) ?>]</span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="acties">
        <!-- Vernieuwen: reload page to get new random teams (reads present from DB) -->
        <a href="teams.php"><button type="button">&#x21bb; Husselen</button></a>
        <!-- Print: open PDF in new tab -->
        <a href="teams.php?pdf=1" target="_blank"><button type="button">&#x1F5B6; Print PDF</button></a>
        <a href="index.php" style="margin-left:auto"><button type="button">&larr; Terug</button></a>
    </div>
</body>
</html>
