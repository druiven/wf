<?php
define('APP_RUNNING', true);
require_once 'common.php';

// Vernieuwen: clear all present flags and reload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'vernieuwen') {
    $pdo->exec('UPDATE voetballers SET present = 0');
    header('Location: index.php');
    exit;
}

$spelers = $pdo->query("
    SELECT id, present,
           TRIM(CONCAT(voornaam, ' ', COALESCE(achternaam, ''))) AS voetballer
    FROM voetballers
    WHERE team_groep IN ('A', 'B', 'C', 'D')
    ORDER BY team_groep, voornaam
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WF</title>
    <style>
        body { font-family: sans-serif; max-width: 700px; margin: 2rem auto; }
        h1 { font-size: 1.4rem; }
        .speler-lijst { list-style: none; padding: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 0 1.5rem; }
        .speler-lijst li {  border-bottom: 1px solid #eee; }
        .speler-lijst label { padding: 0.4rem 0; cursor: pointer; display: block; }
        .speler-lijst label:hover { background: #f1f1f1; }
        .acties { margin-top: 1.5rem; display: flex; gap: 1rem; align-items: center; }
        .acties button { padding: 0.45rem 1rem; font-size: 0.95rem; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Wie zijn er vandaag? (team A – D)</h1>
    <form method="post" action="teams.php">
        <ul class="speler-lijst">
            <?php foreach ($spelers as $speler): ?>
            <li>
                <label>
                    <input type="checkbox" name="voetballer_id[]" value="<?= htmlspecialchars($speler['id']) ?>" <?= $speler['present'] ? 'checked' : '' ?>>
                    <?= htmlspecialchars($speler['voetballer']) ?>
                </label>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="acties">
            <button type="submit">Maak teams</button>
            <!-- Vernieuwen: clear all present flags -->
            <button type="submit" form="vernieuwen-form">&#x21bb; Vernieuwen</button>
        </div>
    </form>
    <form id="vernieuwen-form" method="post" action="index.php">
        <input type="hidden" name="action" value="vernieuwen">
    </form>
</body>
</html>
