<?php
define('APP_RUNNING', true);
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/fpdf/fpdf.php';

$verzonden = false;
$fout      = false;

// Fetch all players with team_groep A-D
$spelers = $pdo->query("
    SELECT id, speler_nr, voornaam, achternaam, team_groep, positie, handig, fit, niet_samen
    FROM voetballers
    WHERE team_groep IN ('A','B','C','D')
    ORDER BY team_groep, voornaam
")->fetchAll();

// Handle form submit — send email only, no DB save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // $positieLabels = ['0' => 'back', '1' => 'mid', '2' => 'front'];
    $positieLabels = ['0' => 'achter', '1' => 'middenveld', '2' => 'aanval'];

    $body  = "Criteria invulling\n";
    $body .= str_repeat('=', 40) . "\n\n";

    foreach ($spelers as $s) {
        $id   = $s['id'];
        $naam = trim($s['voornaam'] . ' ' . $s['achternaam']);
        $data = $_POST['speler'][$id] ?? [];

        $positie    = $data['positie']    !== '' ? ($positieLabels[$data['positie']] ?? $data['positie']) : '–';
        $handig     = $data['handig']     !== '' ? $data['handig']     : '–';
        $fit        = $data['fit']        !== '' ? $data['fit']        : '–';
        $nsNrs     = !empty($data['niet_samen']) ? array_map('intval', (array)$data['niet_samen']) : [];
        $nsNamen   = [];
        foreach ($nsNrs as $nsNr) {
            foreach ($spelers as $opt) {
                if ((int)$opt['speler_nr'] === $nsNr) {
                    $nsNamen[] = trim($opt['voornaam'] . ' ' . $opt['achternaam']);
                    break;
                }
            }
        }
        $nietsamen = $nsNamen ? implode(', ', $nsNamen) : '–';

        $body .= sprintf("%-25s [%s]  positie: %-6s  handig: %-4s  fit: %-4s  niet samen: %s\n",
            $naam, $s['team_groep'], $positie, $handig, $fit, $nietsamen);
    }

    $ingevuld = trim(strip_tags($_POST['ingevuld_door'] ?? ''));
    if ($ingevuld === '') {
        $fout = 'naam';
    } else {

    // --- Build PDF with FPDF ---
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Criteria invulling - ' . $ingevuld, 0, 1);
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(0, 6, 'Datum: ' . date('d-m-Y'), 0, 1);
    $pdf->Ln(3);

    // Table header
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell(50, 7, 'Naam',        1, 0, 'L', true);
    $pdf->Cell(12, 7, 'Groep',       1, 0, 'C', true);
    $pdf->Cell(28, 7, 'Positie',     1, 0, 'C', true);
    $pdf->Cell(20, 7, 'Handig',      1, 0, 'C', true);
    $pdf->Cell(16, 7, 'Fit',         1, 0, 'C', true);
    $pdf->Cell(60, 7, 'Niet samen',  1, 1, 'L', true);

    // Table rows
    $pdf->SetFont('Arial', '', 9);
    foreach ($spelers as $s) {
        $id   = $s['id'];
        $naam = trim($s['voornaam'] . ' ' . $s['achternaam']);
        $data = $_POST['speler'][$id] ?? [];

        $positie   = $data['positie']    !== '' ? ($positieLabels[$data['positie']] ?? $data['positie']) : '-';
        $handig    = $data['handig']     !== '' ? $data['handig']    : '-';
        $fit       = $data['fit']        !== '' ? $data['fit']       : '-';
        $nsNrs   = !empty($data['niet_samen']) ? array_map('intval', (array)$data['niet_samen']) : [];
        $pdNamen = [];
        foreach ($nsNrs as $nsNr) {
            foreach ($spelers as $opt) {
                if ((int)$opt['speler_nr'] === $nsNr) {
                    $pdNamen[] = trim($opt['voornaam'] . ' ' . $opt['achternaam']);
                    break;
                }
            }
        }
        $nsNaam = $pdNamen ? implode(', ', $pdNamen) : '-';

        $pdf->Cell(50, 6, $naam,              1, 0, 'L');
        $pdf->Cell(12, 6, $s['team_groep'],   1, 0, 'C');
        $pdf->Cell(28, 6, $positie,           1, 0, 'C');
        $pdf->Cell(20, 6, $handig,            1, 0, 'C');
        $pdf->Cell(16, 6, $fit,               1, 0, 'C');
        $pdf->Cell(60, 6, $nsNaam,            1, 1, 'L');
    }

    $pdfString = $pdf->Output('S'); // get as string

    // --- Build multipart email with PDF attachment ---
    $boundary = md5(uniqid());
    $to      = 'info@gerarddruiven.nl';
    $subject = 'criteria ingevuld door: ' . $ingevuld;
    $headers  = "From: wf@gerarddruiven.nl\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

    $message  = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= $body . "\r\n";
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: application/pdf\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "Content-Disposition: attachment; filename=\"criteria-{$ingevuld}.pdf\"\r\n\r\n";
    $message .= chunk_split(base64_encode($pdfString)) . "\r\n";
    $message .= "--$boundary--";

    if (mail($to, $subject, $message, $headers)) {
        $verzonden = true;
    } else {
        $fout = 'mail';
    }

    } // end naam check
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WF – Criteria invullen</title>
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
    <h1>Criteria invullen voor Gerard</h1>
    <p>Vul voor elke speler je inschatting in en klik op <strong>Verstuur</strong>. De gegevens worden per e-mail naar Gerard gestuurd.</p>
<p>Het werkt ook het beste als de 0 tot 10 ook een beetje gebruikt wordrt. ik zat zelf aldoor tussen 5 en 10 te geven, maar  spreiden is veel beter voor samenstellen. Geldt
     ook voor fit. Beetje overdrijven dus</p>
    <?php if ($verzonden): ?>
        <p class="ok">Verstuurd! Bedankt voor je input.</p>
    <?php elseif ($fout === 'naam'): ?>
        <p class="fout">Vul eerst je naam in.</p>
    <?php elseif ($fout === 'mail'): ?>
        <p class="fout">Verzenden mislukt. Probeer het later opnieuw.</p>
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
                            <option value="0" <?= (string)$s['positie'] === '0' ? 'selected' : '' ?>>achter</option>
                            <option value="1" <?= (string)$s['positie'] === '1' ? 'selected' : '' ?>>middenveld</option>
                            <option value="2" <?= (string)$s['positie'] === '2' ? 'selected' : '' ?>>aanval</option>
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
        <p>
            <label>Jouw naam (verplicht) <strong style="color:red">*</strong>:
                <input type="text" name="ingevuld_door" maxlength="60" required
                       style="margin-left:0.5rem; width:200px;"
                       value="<?= htmlspecialchars($_POST['ingevuld_door'] ?? '') ?>">
            </label>
            </label>
        </p>
        <button type="submit">Verstuur naar Gerard</button>
    </form>
</body>
</html>

