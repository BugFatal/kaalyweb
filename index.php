<?php
// public/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'challenge-manager.php';

$dailyChallengeManager = new DailyChallenge($pdo);
$dailyChallenge = $dailyChallengeManager->getDailyChallenge();

$pageTitle = 'Vindigni Tables de Multiplication';
include 'header.php';

// Récupération du défi quotidien
$stmt = $pdo->prepare("
    SELECT * FROM daily_challenges 
    WHERE challenge_date = CURDATE()
");
$stmt->execute();
$dailyChallenge = $stmt->fetch();

// Récupération des meilleurs scores du jour
$stmt = $pdo->prepare("
    SELECT user_id, score, date_played 
    FROM challenge_scores 
    WHERE DATE(date_played) = CURDATE()
    ORDER BY score DESC 
    LIMIT 20
");
$stmt->execute();
$dailyTopScores = $stmt->fetchAll();
?>



<!-- Navigation vers les tables individuelles -->

<h3 class="text-center mb-3">Tables individuelles</h3>
<div class="row g-2">
    <?php for($i = 1; $i <= 12; $i++): ?>
        <div class="col-4 col-sm-3 col-md-2 col-lg-1">
            <a href="table.php?number=<?php echo $i; ?>" class="btn btn-primary w-100 table-button">
                x <?php echo $i; ?>
            </a>
        </div>
    <?php endfor; ?>
    <div class="col-12 mt-3">
        <a href="practice.php" class="btn btn-success w-100 py-3">S'entraîner à toutes les tables</a>
    </div>
</div>

<!-- Section Challenge du jour -->
<div class="row mt-4 mb-4">
    <div class="col-md-6 mb-3">
        <div class="card h-100 challenge-card">
            <div class="card-body">
                <h4 class="card-title">
                    <i class="fas fa-trophy text-warning"></i> 
                    Challenge du Jour
                </h4>
                <div class="challenge-info">
                    <p class="challenge-description">
                        <?php echo htmlspecialchars($dailyChallenge['description']); ?>
                    </p>
                    <div class="challenge-details">
                        <div class="detail-item">
                            <i class="fas fa-star"></i>
                            <span>Objectif: <?php echo number_format($dailyChallenge['target_score']); ?> points</span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-clock"></i>
                            <span>Temps: <?php echo $dailyChallenge['time_limit']; ?> secondes</span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-signal"></i>
                            <span>Difficulté: 
                                <?php 
                                    $difficulties = ['easy' => 'Facile', 'medium' => 'Moyen', 'hard' => 'Difficile'];
                                    echo $difficulties[$dailyChallenge['difficulty']] ?? 'Normal';
                                ?>
                            </span>
                        </div>
                    </div>
                    <a href="challenge2.php" class="btn btn-primary btn-lg mt-3 w-100">
                        <i class="fas fa-play-circle"></i> Relever le défi !
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-3">
        <div class="card h-100 leaderboard-card">
            <div class="card-body">
                <h4 class="card-title">
                    <i class="fas fa-crown text-warning"></i> 
                    Meilleurs scores du jour
                </h4>
                <?php if (empty($dailyTopScores)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-hourglass-start fa-2x mb-3 text-muted"></i>
                        <p class="lead">Soyez le premier à réaliser un score aujourd'hui !</p>
                    </div>
                <?php else: ?>
                    <div class="leaderboard-list">
                        <?php foreach ($dailyTopScores as $index => $score): ?>
                            <div class="leaderboard-item">
                                <div class="rank">
                                    <?php 
                                        $medals = ['🥇', '🥈', '🥉'];
                                        echo $index <= 2 ? $medals[$index] : ($index + 1);
                                    ?>
                                </div>
                                <div class="player">
                                    <?php echo htmlspecialchars($score['user_id']); ?>
                                </div>
                                <div class="score">
                                    <?php echo number_format($score['score']); ?> pts
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tableau de toutes les tables -->
<div class="card mb-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered multiplication-table">
                <thead class="table-primary">
                    <tr>
                        <th class="text-center">×</th>
                        <?php for($i = 1; $i <= 12; $i++): ?>
                            <th class="text-center"><?php echo $i; ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php for($i = 1; $i <= 12; $i++): ?>
                        <tr>
                            <th class="text-center table-primary"><?php echo $i; ?></th>
                            <?php for($j = 1; $j <= 12; $j++): ?>
                                <td class="text-center" data-row="<?php echo $i; ?>" data-col="<?php echo $j; ?>"><?php echo $i * $j; ?></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>






<!-- Script pour mettre en surbrillance les lignes et colonnes -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = document.querySelector('.multiplication-table');
    const cells = table.getElementsByTagName('td');

    // Fonction pour mettre en surbrillance les lignes et colonnes
    function highlightCells(e) {
        // Réinitialiser toutes les surbrillances
        clearHighlights();

        if (e.target.tagName === 'TD') {
            const row = e.target.parentNode;
            const rowIndex = e.target.dataset.row;
            const colIndex = e.target.dataset.col;

            // Mettre en surbrillance la ligne
            row.classList.add('highlight-row');

            // Mettre en surbrillance la colonne
            const allCells = table.querySelectorAll(`td[data-col="${colIndex}"]`);
            allCells.forEach(cell => cell.classList.add('highlight-col'));

            // Mettre en surbrillance la cellule survolée
            e.target.classList.add('highlight-cell');
        }
    }

    // Fonction pour effacer toutes les surbrillances
    function clearHighlights() {
        table.querySelectorAll('.highlight-row').forEach(el => el.classList.remove('highlight-row'));
        table.querySelectorAll('.highlight-col').forEach(el => el.classList.remove('highlight-col'));
        table.querySelectorAll('.highlight-cell').forEach(el => el.classList.remove('highlight-cell'));
    }

    // Ajouter les écouteurs d'événements pour la table
    table.addEventListener('mouseover', highlightCells);
    table.addEventListener('mouseleave', clearHighlights);
});
</script>

<?php include 'footer.php'; ?>