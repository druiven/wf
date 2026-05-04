<?php
defined('APP_RUNNING') or die('Direct access not permitted');

class TeamBuilder
{
    private PDO   $pdo;
    private array $ids;
    private int   $aantalTeams;
    private array $conflictMap = [];

    public function __construct(PDO $pdo, array $ids)
    {
        $this->pdo         = $pdo;
        $this->ids         = array_values(array_filter(array_map('intval', $ids)));
        $this->aantalTeams = count($this->ids) >= 15 ? 4 : 2;
    }

    public function getAantalTeams(): int
    {
        return $this->aantalTeams;
    }

    public function build(): array
    {
        $players = $this->fetchPlayers();
        $this->buildConflictMap($players);
        $queue = $this->buildQueue($players);
        return $this->assign($queue);
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function fetchPlayers(): array
    {
        $placeholders = implode(',', array_fill(0, count($this->ids), '?'));
        $stmt = $this->pdo->prepare("
            SELECT id, speler_nr,
                   TRIM(CONCAT(voornaam, ' ', COALESCE(achternaam, ''))) AS voetballer,
                   team_groep,
                   niet_samen,
                   positie,
                   handig,
                   fit
            FROM voetballers
            WHERE id IN ($placeholders)
            ORDER BY team_groep, voornaam
        ");
        $stmt->execute($this->ids);
        return $stmt->fetchAll();
    }

    private function buildConflictMap(array $players): void
    {
        foreach ($players as $p) {
            if (!empty($p['niet_samen'])) {
                $this->conflictMap[$p['speler_nr']] =
                    array_map('intval', explode(',', $p['niet_samen']));
            }
        }
    }

    private function buildQueue(array $players): array
    {
        // Group by team_groep and shuffle within each group
        $byGroup = [];
        foreach ($players as $p) {
            $byGroup[$p['team_groep']][] = $p;
        }
        foreach ($byGroup as &$g) {
            shuffle($g);
        }
        unset($g);

        // Interleave: A1, B1, C1, D1, A2, B2, …
        $queue    = [];
        $maxCount = max(array_map('count', $byGroup));
        for ($i = 0; $i < $maxCount; $i++) {
            foreach (array_keys($byGroup) as $group) {
                if (isset($byGroup[$group][$i])) {
                    $queue[] = $byGroup[$group][$i];
                }
            }
        }
        return $queue;
    }

    private function assign(array $queue): array
    {
        $teamNummers = range(1, $this->aantalTeams);
        $teams       = array_fill_keys($teamNummers, []);

        foreach ($queue as $player) {
            $bestTeam  = null;
            $bestScore = PHP_INT_MAX;

            foreach ($teamNummers as $t) {
                $score = $this->score($player, $t, $teams);
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestTeam  = $t;
                }
            }

            $teams[$bestTeam][] = $player;
        }

        return $teams;
    }

    private function hasConflict(array $player, array $teamleden): bool
    {
        $nr = $player['speler_nr'] ?? null;
        if ($nr === null) return false;

        $verboden = $this->conflictMap[$nr] ?? [];
        foreach ($teamleden as $lid) {
            $lidNr = $lid['speler_nr'] ?? null;
            if ($lidNr !== null && (
                in_array($lidNr, $verboden, true) ||
                in_array($nr, $this->conflictMap[$lidNr] ?? [], true)
            )) {
                return true;
            }
        }
        return false;
    }

    private function score(array $player, int $t, array $teams): int
    {
        $score = 0;
        $leden = $teams[$t];

        // 1. niet_samen — hard block
        if ($this->hasConflict($player, $leden)) {
            $score += 10000;
        }

        // 2. team_groep duplicate
        foreach ($leden as $lid) {
            if ($lid['team_groep'] === $player['team_groep']) {
                $score += 1000;
            }
        }

        // 3. fit balance (0–10 scale)
        if ($player['fit'] !== null) {
            $score += array_sum(array_column($leden, 'fit')) * 20;
        }

        // 4. handig balance (0–10 ball skill scale)
        if ($player['handig'] !== null) {
            $score += array_sum(array_column($leden, 'handig')) * 20;
        }

        // 5. positie duplicates (soft)
        if ($player['positie'] !== null) {
            foreach ($leden as $lid) {
                if ($lid['positie'] !== null && $lid['positie'] === $player['positie']) {
                    $score += 50;
                }
            }
        }

        // 6. team size — keep teams even
        $score += count($leden) * 300;

        // 7. Size-strength compensation:
        //    If this team is smaller than the largest team,
        //    give a bonus for placing a stronger player here,
        //    so teams that end up with fewer players get the better players.
        $sizes   = array_map('count', $teams);
        $maxSize = max($sizes);
        if (count($leden) < $maxSize) {
            $playerStrength = ($player['fit'] ?? 0) + ($player['handig'] ?? 0);
            $score -= $playerStrength * 15;
        }

        return $score;
    }
}
