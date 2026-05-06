<?php
define('APP_RUNNING', true);
require_once __DIR__ . '/common.php';

$opgeslagen = false;

// Fetch all players with team_groep A-D
$spelers = $pdo->query("
    SELECT id, speler_nr, voornaam, achternaam, team_groep, positie, handig, fit, niet_samen
    FROM voetballers
    WHERE team_groep IN ('A','B','C','D')
    ORDER BY team_groep, voornaam
")->fetchAll();

// Handle form submit — save to database
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("
        UPDATE voetballers
        SET positie    = :positie,
            handig     = :handig,
            fit        = :fit,
            niet_samen = :niet_samen
        WHERE id = :id
    ");

    foreach ($spelers as $s) {
        $id   = $s['id'];
        $data = $_POST['speler'][$id] ?? [];

        $stmt->execute([
            ':id'         => $id,
            ':positie'    => isset($data['positie'])    && $data['positie']    !== '' ? (int)$data['positie']    : null,
            ':handig'     => isset($data['handig'])     && $data['handig']     !== '' ? (int)$data['handig']     : null,
            ':fit'        => isset($data['fit'])        && $data['fit']        !== '' ? (int)$data['fit']        : null,
            ':niet_samen' => !empty($data['niet_samen'])
                ? implode(',', array_map('intval', (array)$data['niet_samen']))
                : null,
        ]);
    }

    $opgeslagen = true;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WF – Criteria beheer</title>
    <style>
        body { font-family: sans-serif; max-width: 780px; margin: 2rem auto; }
        h1 { font-size: 1.4rem; }
        .ok   { color: green; margin-bottom: 1rem; }
        .fout { color: red;   margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 0.4rem 0.6rem; border-bottom: 1px solid #eee; font-size: 0.9rem; }
        th { background: #f5f5f5; }
        td select, td input[type=number], td input[type=text] { width: 100%; }
        .groep { font-weight: bold; color: #555; }
        .huidig { color: #aaa; font-size: 0.8rem; }
        button { margin-top: 1rem; padding: 0.5rem 1.2rem; font-size: 1rem; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Criteria beheren</h1>
    <p>Pas de waarden aan en klik op <strong>Opslaan</strong>. De gegevens worden direct in de database opgeslagen.</p>

    <?php if ($opgeslagen): ?>
        <p class="ok">Opgeslagen!</p>
    <?php endif; ?>

    <form method="post">
        <table>
            <thead>
                <tr>
                    <th>Naam</th>
                    <th>Groep</th>
                    <th>Positie</th>
                    <th>Handig<br><small>0–10 balvaardigheid</small></th>
                    <th>Fit<br><small>0–10</small></th>
                    <th>Niet samen met</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($spelers as $s): ?>
                <tr>
                    <td><?= htmlspecialchars(trim($s['voornaam'] . ' ' . $s['achternaam'])) ?></td>
                    <td class="groep"><?= htmlspecialchars($s['team_groep']) ?></td>
                    <td>
                        <select name="speler[<?= $s['id'] ?>][positie]">
                            <option value="">–</option>
                            <option value="0" <?= (string)$s['positie'] === '0' ? 'selected' : '' ?>>back</option>
                            <option value="1" <?= (string)$s['positie'] === '1' ? 'selected' : '' ?>>mid</option>
                            <option value="2" <?= (string)$s['positie'] === '2' ? 'selected' : '' ?>>front</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" name="speler[<?= $s['id'] ?>][handig]"
                               min="0" max="10"
                               value="<?= htmlspecialchars((string)($s['handig'] ?? '')) ?>">
                    </td>
                    <td>
                        <input type="number" name="speler[<?= $s['id'] ?>][fit]"
                               min="0" max="10"
                               value="<?= htmlspecialchars((string)($s['fit'] ?? '')) ?>">
                    </td>
                    <td>
                        <?php $nsSelected = array_map('intval', array_filter(explode(',', (string)$s['niet_samen']))); ?>
                        <select name="speler[<?= $s['id'] ?>][niet_samen][]" multiple size="3">
                            <?php foreach ($spelers as $opt): ?>
                                <?php if ($opt['id'] === $s['id']) continue; ?>
                                <option value="<?= $opt['speler_nr'] ?>"
                                    <?= in_array((int)$opt['speler_nr'], $nsSelected) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(trim($opt['voornaam'] . ' ' . $opt['achternaam'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit">Opslaan</button>
    </form>
</body>
</html>

