<?php
session_start();
require_once 'config.php';
require_once 'challenge-manager.php';

// Vérification des droits d'administration (à adapter selon votre système)
if (!isset($_COOKIE['SSOwAuthUser']) || $_COOKIE['SSOwAuthUser'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$challengeManager = new DailyChallenge($pdo);
$message = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
            case 'update':
                try {
                    $data = [
                        'challenge_date' => $_POST['challenge_date'],
                        'target_score' => (int)$_POST['target_score'],
                        'difficulty' => $_POST['difficulty'],
                        'time_limit' => (int)$_POST['time_limit'],
                        'description' => $_POST['description'],
                        'points_base' => (int)$_POST['points_base'],
                        'time_bonus' => (int)$_POST['time_bonus'],
                        'streak_bonus' => (int)$_POST['streak_bonus'],
                        'is_published' => isset($_POST['is_published']) ? 1 : 0,
                        'created_by' => $_COOKIE['SSOwAuthUser']
                    ];
                    
                    if ($_POST['action'] === 'create') {
                        $challengeManager->createCustomChallenge($data);
                        $message = "Défi créé avec succès !";
                    } else {
                        $challengeManager->updateChallenge((int)$_POST['id'], $data);
                        $message = "Défi mis à jour avec succès !";
                    }
                } catch (Exception $e) {
                    $message = "Erreur : " . $e->getMessage();
                }
                break;
            
            case 'delete':
                try {
                    $challengeManager->deleteChallenge((int)$_POST['id']);
                    $message = "Défi supprimé avec succès !";
                } catch (Exception $e) {
                    $message = "Erreur lors de la suppression : " . $e->getMessage();
                }
                break;
        }
    }
}

// Récupération des défis
$challenges = $challengeManager->getAllChallenges();

// Configuration du titre de la page
$pageTitle = 'Administration des Défis';
include 'header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h1>Administration des Défis</h1>
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- Formulaire de création/modification -->
<div class="card mb-4">
    <div class="card-header">
        <h2 class="card-title h5 mb-0">Créer un nouveau défi</h2>
    </div>
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="action" value="create">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="challenge_date" class="form-label">Date du défi</label>
                    <input type="date" class="form-control" id="challenge_date" name="challenge_date" 
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="difficulty" class="form-label">Difficulté</label>
                    <select class="form-select" id="difficulty" name="difficulty" required>
                        <option value="easy">Facile</option>
                        <option value="medium" selected>Moyen</option>
                        <option value="hard">Difficile</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="target_score" class="form-label">Score cible</label>
                    <input type="number" class="form-control" id="target_score" name="target_score" 
                           value="1500" required>
                </div>
                <div class="col-md-6">
                    <label for="time_limit" class="form-label">Temps limite (secondes)</label>
                    <input type="number" class="form-control" id="time_limit" name="time_limit" 
                           value="120" required>
                </div>
                <div class="col-md-4">
                    <label for="points_base" class="form-label">Points de base</label>
                    <input type="number" class="form-control" id="points_base" name="points_base" 
                           value="100" required>
                </div>
                <div class="col-md-4">
                    <label for="time_bonus" class="form-label">Bonus de temps</label>
                    <input type="number" class="form-control" id="time_bonus" name="time_bonus" 
                           value="50" required>
                </div>
                <div class="col-md-4">
                    <label for="streak_bonus" class="form-label">Bonus de série</label>
                    <input type="number" class="form-control" id="streak_bonus" name="streak_bonus" 
                           value="50" required>
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3" 
                              required>Défi du jour : Obtenez le meilleur score possible en 2 minutes!</textarea>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_published" 
                               name="is_published" checked>
                        <label class="form-check-label" for="is_published">
                            Publier ce défi
                        </label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Créer le défi</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Liste des défis -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title h5 mb-0">Liste des défis</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Difficulté</th>
                        <th>Score cible</th>
                        <th>Temps</th>
                        <th>Points base</th>
                        <th>Bonus temps</th>
                        <th>Bonus série</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($challenges as $challenge): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($challenge['challenge_date']); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $challenge['difficulty'] === 'easy' ? 'success' : 
                                    ($challenge['difficulty'] === 'medium' ? 'warning' : 'danger'); 
                            ?>">
                                <?php echo ucfirst($challenge['difficulty']); ?>
                            </span>
                        </td>
                        <td><?php echo number_format($challenge['target_score']); ?></td>
                        <td><?php echo $challenge['time_limit']; ?>s</td>
                        <td><?php echo $challenge['points_base']; ?></td>
                        <td><?php echo $challenge['time_bonus']; ?></td>
                        <td><?php echo $challenge['streak_bonus']; ?></td>
                        <td>
                            <?php if ($challenge['is_published']): ?>
                                <span class="badge bg-success">Publié</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Brouillon</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                        onclick="editChallenge(<?php echo htmlspecialchars(json_encode($challenge)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" class="d-inline" 
                                      onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce défi ?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $challenge['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function editChallenge(challenge) {
    // Remplir le formulaire avec les données du défi
    document.querySelector('input[name="action"]').value = 'update';
    document.querySelector('input[name="id"]').value = challenge.id;
    document.querySelector('#challenge_date').value = challenge.challenge_date;
    document.querySelector('#target_score').value = challenge.target_score;
    document.querySelector('#difficulty').value = challenge.difficulty;
    document.querySelector('#time_limit').value = challenge.time_limit;
    document.querySelector('#points_base').value = challenge.points_base;
    document.querySelector('#time_bonus').value = challenge.time_bonus;
    document.querySelector('#streak_bonus').value = challenge.streak_bonus;
    document.querySelector('#description').value = challenge.description;
    document.querySelector('#is_published').checked = challenge.is_published;
    
    // Faire défiler jusqu'au formulaire
    document.querySelector('.card').scrollIntoView({ behavior: 'smooth' });
}
</script>

<?php include 'footer.php'; ?>