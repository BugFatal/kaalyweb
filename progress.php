<?php
// public/progress.php
session_start();
require_once 'config.php';

// Récupération de l'identifiant de l'utilisateur depuis Yunohost
$user_id = $_SERVER['REMOTE_USER'] ?? 'anonymous';

// Récupération des données de progression (toutes les sessions)
$stmt = $pdo->prepare("SELECT * FROM training_results WHERE user_id = ? ORDER BY training_date DESC");
$stmt->execute([$user_id]);
$results = $stmt->fetchAll();

// Récupération des statistiques globales réelles
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_sessions,
        SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as total_correct,
        COUNT(*) as total_questions
    FROM training_results 
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$globalStats = $stmt->fetch();

// Calcul des statistiques globales
$totalSessions = $globalStats['total_sessions'] ?? 0;
$totalCorrect = $globalStats['total_correct'] ?? 0;
$totalQuestions = $globalStats['total_questions'] ?? 0;
$averageScore = $totalQuestions > 0 ? round(($totalCorrect / $totalQuestions) * 100, 2) : 0;

// Récupération des statistiques de difficulté
$stmt = $pdo->prepare("
    SELECT 
        table_number, 
        multiplier, 
        total_attempts,
        correct_attempts,
        (total_attempts - correct_attempts) as incorrect_attempts,
        ROUND((correct_attempts / total_attempts) * 100, 1) as success_rate
    FROM difficulty_stats 
    WHERE user_id = ? 
        AND total_attempts > 0
    ORDER BY (correct_attempts / total_attempts) ASC, total_attempts DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$difficultyStats = $stmt->fetchAll();

// Récupération des statistiques par table
$stmt = $pdo->prepare("
    SELECT 
        table_number, 
        SUM(total_attempts) as total_attempts,
        SUM(correct_attempts) as correct_attempts,
        SUM(total_attempts - correct_attempts) as incorrect_attempts
    FROM difficulty_stats 
    WHERE user_id = ? 
    GROUP BY table_number 
    ORDER BY table_number
");
$stmt->execute([$user_id]);
$tableStats = $stmt->fetchAll();

$pageTitle = 'Ma Progression - Tables de Multiplication';

// Fonction pour déterminer la classe de couleur selon le taux de réussite
function getProgressBarClass($rate) {
    $rate = floatval($rate);
    if ($rate < 33) {
        return 'bg-danger progress-bar'; // Ajout de progress-bar
    } elseif ($rate < 66) {
        return 'bg-warning progress-bar';
    } else {
        return 'bg-success progress-bar';
    }
}

include 'header.php';
?>

<div class="container">
    <h2 class="text-center mb-4">Ma Progression</h2>

    <!-- Statistiques en cartes -->
    <div class="row mb-4">
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">Score Moyen</h5>
                    <p class="display-4"><?php echo $averageScore; ?>%</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Questions</h5>
                    <p class="display-4"><?php echo $totalQuestions; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">Réponses Correctes</h5>
                    <p class="display-4"><?php echo $totalCorrect; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">Réponses Incorrectes</h5>
                    <p class="display-4"><?php echo $totalQuestions - $totalCorrect; ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($totalQuestions > 0): ?>
        <!-- Historique des réponses -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Historique des réponses</h5>
                <canvas id="progressChart" style="width:100%; height:300px;"></canvas>
            </div>
        </div>

        <!-- Les 10 multiplications les plus difficiles -->
        <div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">Vos 10 multiplications les plus difficiles</h5>
        <div class="row">
            <?php 
            $halfCount = ceil(count($difficultyStats) / 2);
            $firstColumn = array_slice($difficultyStats, 0, $halfCount);
            $secondColumn = array_slice($difficultyStats, $halfCount);
            ?>
            
              <!-- Première colonne -->
              <div class="col-md-6 mb-3">
                <ul class="list-group">
                    <?php foreach ($firstColumn as $stat): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong><?php echo $stat['table_number'] . ' × ' . $stat['multiplier']; ?></strong>
                                <span class="badge bg-primary rounded-pill">
                                    <?php echo $stat['correct_attempts'] . ' / ' . $stat['total_attempts']; ?>
                                </span>
                            </div>
                            <div class="progress mt-1" style="height: 5px;">
    <div class="progress-bar <?php echo getProgressBarClass($stat['success_rate']); ?>" 
        role="progressbar" 
        style="width: <?php echo max(3, $stat['success_rate']); ?>%"
        aria-valuenow="<?php echo $stat['success_rate']; ?>" 
        aria-valuemin="0" 
        aria-valuemax="100">
    </div>
</div>
                            <small class="text-muted">
                                Taux de réussite : <?php echo $stat['success_rate']; ?>%
                                (<?php echo $stat['incorrect_attempts']; ?> erreurs)
                            </small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Deuxième colonne -->
            <div class="col-md-6">
                <ul class="list-group">
                    <?php foreach ($secondColumn as $stat): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong><?php echo $stat['table_number'] . ' × ' . $stat['multiplier']; ?></strong>
                                <span class="badge bg-primary rounded-pill">
                                    <?php echo $stat['correct_attempts'] . ' / ' . $stat['total_attempts']; ?>
                                </span>
                            </div>
                            <div class="progress mt-1" style="height: 5px;">
    <div class="progress-bar <?php echo getProgressBarClass($stat['success_rate']); ?>" 
        role="progressbar" 
        style="width: <?php echo max(3, $stat['success_rate']); ?>%"
        aria-valuenow="<?php echo $stat['success_rate']; ?>" 
        aria-valuemin="0" 
        aria-valuemax="100">
    </div>
</div>
                            <small class="text-muted">
                                Taux de réussite : <?php echo $stat['success_rate']; ?>%
                                (<?php echo $stat['incorrect_attempts']; ?> erreurs)
                            </small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

        <!-- Progression par table -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Progression par table</h5>
                <canvas id="tableChart" style="width:100%; height:300px;"></canvas>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center">
            Vous n'avez pas encore effectué de sessions d'entraînement. Commencez à vous entraîner pour voir votre progression !
        </div>
    <?php endif; ?>

    <div class="text-center mt-4 mb-4">
        <a href="practice.php" class="btn btn-primary btn-lg">Retour à l'entraînement</a>
        <a href="reset.php" class="btn btn-warning">Réinitialiser mes résultats</a>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($totalQuestions > 0): ?>
    // Graphique de progression
    var ctx = document.getElementById('progressChart').getContext('2d');
    var progressChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_map(function($r) { 
                return date('d/m H:i', strtotime($r['training_date'])); 
            }, array_reverse($results))); ?>,
            datasets: [{
                label: 'Réponse',
                data: <?php echo json_encode(array_map(function($r) { 
                    return $r['is_correct'] ? 100 : 0;
                }, array_reverse($results))); ?>,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                pointRadius: 5,
                pointBackgroundColor: function(context) {
                    var value = context.dataset.data[context.dataIndex];
                    return value === 100 ? 'rgb(75, 192, 192)' : 'rgb(255, 99, 132)';
                },
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value === 100 ? 'Correct' : value === 0 ? 'Incorrect' : '';
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date et heure'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y === 100 ? 'Réponse correcte' : 'Réponse incorrecte';
                        }
                    }
                }
            }
        }
    });

    // Graphique par table
    var tableCtx = document.getElementById('tableChart').getContext('2d');
    var tableChart = new Chart(tableCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($tableStats, 'table_number')); ?>,
            datasets: [{
                label: 'Réponses correctes',
                data: <?php echo json_encode(array_column($tableStats, 'correct_attempts')); ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.6)',
                borderColor: 'rgb(75, 192, 192)',
                borderWidth: 1
            },
            {
                label: 'Réponses incorrectes',
                data: <?php echo json_encode(array_column($tableStats, 'incorrect_attempts')); ?>,
                backgroundColor: 'rgba(255, 99, 132, 0.6)',
                borderColor: 'rgb(255, 99, 132)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Nombre de réponses'
                    },
                    stacked: true
                },
                x: {
                    title: {
                        display: true,
                        text: 'Table de multiplication'
                    },
                    stacked: true
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>
<?php include 'footer.php'; ?>