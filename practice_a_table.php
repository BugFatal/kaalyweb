<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

// Vérification et récupération du numéro de table
if (!isset($_GET['table']) || !is_numeric($_GET['table']) || $_GET['table'] < 1 || $_GET['table'] > 10) {
    header('Location: index.php');
    exit();
}

$tableNumber = (int)$_GET['table'];

// Fonction pour générer une nouvelle question
function generateQuestion($difficulty, $pdo, $user_id, $tableNumber) {
    $maxMultiplier = [
        'easy' => 10,
        'medium' => 20,
        'hard' => 30
    ][$difficulty] ?? 10;

    if (rand(1, 100) <= 31) {
        try {
            $stmt = $pdo->prepare("
                SELECT multiplier 
                FROM difficulty_stats 
                WHERE user_id = ? 
                    AND table_number = ?
                    AND total_attempts > 0 
                    AND multiplier <= ?
                ORDER BY (correct_attempts / total_attempts) ASC 
                LIMIT 5
            ");
            $stmt->execute([$user_id, $tableNumber, $maxMultiplier]);
            $difficultMultipliers = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($difficultMultipliers)) {
                return [
                    'number' => $tableNumber,
                    'multiplier' => $difficultMultipliers[array_rand($difficultMultipliers)]
                ];
            }
        } catch (Exception $e) {
            // En cas d'erreur, continuer avec la génération aléatoire
        }
    }

    return [
        'number' => $tableNumber,
        'multiplier' => rand(1, $maxMultiplier)
    ];
}

function handleError($message) {
    $_SESSION['error_message'] = $message;
    header('Location: practice_a_table.php?table=' . $_GET['table']);
    exit();
}

$user_id = $_SERVER['REMOTE_USER'] ?? 'anonymous';

// Initialisation des variables de session spécifiques à la table
$sessionKey = 'table_' . $tableNumber;
if (!isset($_SESSION[$sessionKey])) {
    $_SESSION[$sessionKey] = [
        'total' => 0,
        'correct' => 0,
        'current_streak' => 0,
        'best_streak' => 0,
        'total_questions' => 0
    ];
}

// Gestion du niveau de difficulté
$_SESSION['difficulty'] = $_POST['difficulty'] ?? $_SESSION['difficulty'] ?? 'easy';

// Génération d'une nouvelle question
$question = generateQuestion($_SESSION['difficulty'], $pdo, $user_id, $tableNumber);
$multiplier = $question['multiplier'];

// Traitement de la réponse
$message = '';
$messageClass = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['answer'], $_POST['multiplier'])) {
    try {
        $userAnswer = (int) $_POST['answer'];
        $correctAnswer = $tableNumber * (int) $_POST['multiplier'];
        $isCorrect = ($userAnswer === $correctAnswer);

        if ($isCorrect) {
            $_SESSION[$sessionKey]['correct']++;
            $_SESSION[$sessionKey]['current_streak']++;
            $_SESSION[$sessionKey]['best_streak'] = max($_SESSION[$sessionKey]['current_streak'], $_SESSION[$sessionKey]['best_streak']);
            $message = "Bonne réponse ! Série actuelle : " . $_SESSION[$sessionKey]['current_streak'];
            $messageClass = "alert-success";
        } else {
            $_SESSION[$sessionKey]['current_streak'] = 0;
            $message = "Mauvaise réponse. La bonne réponse était $correctAnswer.";
            $messageClass = "alert-danger";
        }

        $_SESSION[$sessionKey]['total']++;
        $_SESSION[$sessionKey]['total_questions']++;

        // Enregistrement dans la base de données
        $stmt = $pdo->prepare("INSERT INTO training_results (user_id, correct_answers, total_questions, difficulty, training_date, table_number, multiplier, is_correct) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)");
        $stmt->execute([
            $user_id,
            (int) $_SESSION[$sessionKey]['correct'],
            (int) $_SESSION[$sessionKey]['total'],
            $_SESSION['difficulty'],
            $tableNumber,
            (int) $_POST['multiplier'],
            $isCorrect ? 1 : 0
        ]);

        // Mise à jour des statistiques de difficulté
        $stmt = $pdo->prepare("INSERT INTO difficulty_stats (user_id, table_number, multiplier, total_attempts, correct_attempts) 
                             VALUES (?, ?, ?, 1, ?) 
                             ON DUPLICATE KEY UPDATE 
                             total_attempts = total_attempts + 1, 
                             correct_attempts = correct_attempts + ?");
        $stmt->execute([
            $user_id,
            $tableNumber,
            (int) $_POST['multiplier'],
            $isCorrect ? 1 : 0,
            $isCorrect ? 1 : 0
        ]);

    } catch (Exception $e) {
        handleError("Une erreur est survenue : " . $e->getMessage());
    }
}

// Récupération des statistiques pour cette table
try {
    $stmt = $pdo->prepare("SELECT multiplier, total_attempts, correct_attempts 
                         FROM difficulty_stats 
                         WHERE user_id = ? AND table_number = ?
                         ORDER BY (correct_attempts / total_attempts) ASC 
                         LIMIT 5");
    $stmt->execute([$user_id, $tableNumber]);
    $difficultyStats = $stmt->fetchAll();
} catch (Exception $e) {
    $difficultyStats = [];
}

$pageTitle = "Table de $tableNumber - Entraînement";
include 'header.php';
?>

<div class="container">
    <h2 class="text-center mb-4">Entraînement - Table de <?php echo $tableNumber; ?></h2>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card mb-4">
                <div class="card-body">
                    <!-- Bouton pour ouvrir le modal -->
                    <div class="text-end mb-3">
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#difficultyModal">
                            <i class="fas fa-cog"></i> Difficulté: <?php 
                                $difficulties = ['easy' => 'Facile', 'medium' => 'Moyen', 'hard' => 'Difficile'];
                                echo $difficulties[$_SESSION['difficulty']] ?? 'Facile'; 
                            ?>
                        </button>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert <?php echo $messageClass; ?>"><?php echo $message; ?></div>
                    <?php endif; ?>

                    <form method="POST" class="mb-4">
                        <input type="hidden" name="multiplier" value="<?php echo $multiplier; ?>">
                        
                        <div class="mb-3">
                            <label for="answer" class="form-label display-6 text-center w-100">
                                <?php echo $tableNumber; ?> × <?php echo $multiplier; ?> = ?
                            </label>
                            <input type="number" id="answer" name="answer" class="form-control form-control-lg text-center" required autofocus>
                        </div>
                        <button type="submit" class="btn btn-success btn-lg w-100">Vérifier</button>
                    </form>

                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Progression</h5>
                                    <p class="card-text">Réponses correctes : <?php echo $_SESSION[$sessionKey]['correct']; ?> / <?php echo $_SESSION[$sessionKey]['total']; ?></p>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?php echo $_SESSION[$sessionKey]['total'] > 0 ? ($_SESSION[$sessionKey]['correct'] / $_SESSION[$sessionKey]['total'] * 100) : 0; ?>%">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Série actuelle</h5>
                                    <p class="display-4"><?php echo $_SESSION[$sessionKey]['current_streak']; ?></p>
                                    <p class="text-muted">Meilleure série : <?php echo $_SESSION[$sessionKey]['best_streak']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($difficultyStats)): ?>
                    <div class="card bg-light mt-3">
                        <div class="card-body">
                            <h5 class="card-title">Multiplications à revoir</h5>
                            <ul class="list-group">
                                <?php foreach ($difficultyStats as $stat): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo $tableNumber . ' × ' . $stat['multiplier']; ?>
                                        <span class="badge bg-primary rounded-pill">
                                            <?php echo $stat['correct_attempts'] . ' / ' . $stat['total_attempts']; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="text-center mt-3">
                        <a href="table.php?number=<?php echo $tableNumber; ?>" class="btn btn-info">Voir la table</a>
                        <a href="index.php" class="btn btn-primary">Retour à l'accueil</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de difficulté -->
<div class="modal fade" id="difficultyModal" tabindex="-1" aria-labelledby="difficultyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="difficultyModalLabel">Choisir la difficulté</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Niveau de difficulté :</label>
                        <div class="d-grid gap-2">
                            <div class="btn-group-vertical" role="group">
                                <input type="radio" class="btn-check" name="difficulty" id="easy" value="easy" 
                                    <?php echo $_SESSION['difficulty'] === 'easy' ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-primary text-start" for="easy">
                                    <i class="fas fa-star"></i> Facile
                                    <small class="d-block text-muted">Multiplicateurs de 1 à 10</small>
                                </label>

                                <input type="radio" class="btn-check" name="difficulty" id="medium" value="medium"
                                    <?php echo $_SESSION['difficulty'] === 'medium' ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-primary text-start" for="medium">
                                    <i class="fas fa-star"></i><i class="fas fa-star"></i> Moyen
                                    <small class="d-block text-muted">Multiplicateurs de 1 à 20</small>
                                </label>

                                <input type="radio" class="btn-check" name="difficulty" id="hard" value="hard"
                                    <?php echo $_SESSION['difficulty'] === 'hard' ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-primary text-start" for="hard">
                                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i> Difficile
                                    <small class="d-block text-muted">Multiplicateurs de 1 à 30</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Appliquer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Focus sur le champ de réponse
    document.getElementById('answer').focus();

    // Soumettre le formulaire avec la touche Entrée
    document.getElementById('answer').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.form.submit();
        }
    });

    // Gérer la soumission du formulaire dans le modal
    const difficultyModal = document.getElementById('difficultyModal');
    difficultyModal.addEventListener('shown.bs.modal', function () {
        const firstInput = difficultyModal.querySelector('input[type="radio"]');
        if (firstInput) firstInput.focus();
    });

    // Auto-submit quand on change de difficulté
    const difficultyInputs = document.querySelectorAll('input[name="difficulty"]');
    difficultyInputs.forEach(input => {
        input.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });
});
</script>

<?php include 'footer.php'; ?>


