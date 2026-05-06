<?php
define('APP_RUNNING', true);
require_once 'common.php';

// Save proposed groups to DB
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['groepen'])) {
    $stmt = $pdo->prepare('UPDATE voetballers SET team_groep = ? WHERE id = ?');
    foreach ($_POST['groepen'] as $id => $groep) {
        if (in_array($groep, ['A','B','C','D'], true)) {
            $stmt->execute([$groep, (int)$id]);
        }
    }
    $opgeslagen = true;
}

// Fetch all active players with their scores
$spelers = $pdo->query("
    SELECT id,
           TRIM(CONCAT(voornaam, ' ', COALESCE(achternaam, ''))) AS voetballer,
           team_groep,
           COALESCE(fit, 0) AS fit,
           COALESCE(handig, 0) AS handig,
           COALESCE(fit, 0) + COALESCE(handig, 0) AS totaal
    FROM voetballers
    WHERE team_groep IN ('A','B','C','D')
    ORDER BY totaal DESC, voornaam
")->fetchAll();

$n     = count($spelers);
$size  = (int) ceil($n / 4);  // group size, last group may be smaller

// Assign proposed group based on rank
$groepen = ['A', 'B', 'C', 'D'];
foreach ($spelers as $i => &$s) {
    $s['nieuw'] = $groepen[min((int)($i / $size), 3)];
}
unset($s);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WF – Groepen indelen</title>
    <style>
        body { font-family: sans-serif; max-width: 700px; margin: 2rem auto; }
        h1 { font-size: 1.4rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: 0.4rem 0.6rem; border-bottom: 1px solid #eee; font-size: 0.95rem; }
        th { background: #f5f5f5; font-weight: bold; }
        .groep-a { color: #1a7a1a; font-weight: bold; }
        .groep-b { color: #0055cc; font-weight: bold; }
        .groep-c { color: #cc7700; font-weight: bold; }
        .groep-d { color: #aa0000; font-weight: bold; }
        .changed { background: #fffbe6; }
        .acties { margin-top: 1.5rem; display: flex; gap: 1rem; align-items: center; }
        .acties button { padding: 0.45rem 1rem; font-size: 0.95rem; cursor: pointer; }
        .success { color: green; font-weight: bold; margin-top: 1rem; }
    </style>
</head>
<body>
    <h1>Groepen indelen op basis van score (fit + handig)</h1>
    <p>Spelers gesorteerd van Fit en Handig. Komt aardig overeen met huidig A,B,C en D.</p>

    <?php if (!empty($opgeslagen)): ?>
    <p class="success">✓ Groepen opgeslagen!</p>
    <?php endif; ?>

    <!-- <form method="post"> -->
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Speler</th>
                    <th>Fit</th>
                    <th>Handig</th>
                    <th>Totaal</th>
                    <th>Huidig</th>
                    <th>Nieuw</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($spelers as $i => $s):
                    $changed = $s['nieuw'] !== $s['team_groep'];
                    $cls = 'groep-' . strtolower($s['nieuw']);
                ?>
                <tr class="<?= $changed ? 'changed' : '' ?>">
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($s['voetballer']) ?></td>
                    <td><?= $s['fit'] ?></td>
                    <td><?= $s['handig'] ?></td>
                    <td><?= $s['totaal'] ?></td>
                    <td><?= htmlspecialchars($s['team_groep']) ?></td>
                    <td class="<?= $cls ?>"><?= $s['nieuw'] ?></td>
                    <!-- <input type="hidden" name="groepen[<?= $s['id'] ?>]" value="<?= $s['nieuw'] ?>"> -->
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="acties">
            <!-- <button type="submit">Opslaan</button> -->
            <a href="index.php"><button type="button">&larr; Terug</button></a>
        </div>
    <!-- </form> -->
</body>
</html>
