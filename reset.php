<?php
session_start();
require_once 'config.php';
$user_id = $_SERVER['REMOTE_USER'] ?? 'anonymous';
$pageTitle = 'Réinitialisation des résultats';
include 'header.php';

$message = '';
$messageClass = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_reset'])) {
    if (isset($_POST['confirmation_text']) && strtolower(trim($_POST['confirmation_text'])) === 'supprimer') {
        try {
            // Suppression des données de la base de données
            $pdo->beginTransaction();
           
            $stmt = $pdo->prepare("DELETE FROM training_results WHERE user_id = ?");
            $stmt->execute([$user_id]);
           
            $stmt = $pdo->prepare("DELETE FROM difficulty_stats WHERE user_id = ?");
            $stmt->execute([$user_id]);
           
            $pdo->commit();
            // Réinitialisation des variables de session
            $_SESSION['total'] = 0;
            $_SESSION['correct'] = 0;
            $_SESSION['current_streak'] = 0;
            $_SESSION['best_streak'] = 0;
            $_SESSION['total_sessions'] = 0;
            $_SESSION['total_questions'] = 0;
            $_SESSION['is_new_session'] = true;
            $message = "Tous vos résultats ont été réinitialisés avec succès.";
            $messageClass = "alert-success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Une erreur est survenue lors de la réinitialisation : " . $e->getMessage();
            $messageClass = "alert-danger";
        }
    } else {
        $message = "Veuillez écrire 'supprimer' pour confirmer la réinitialisation.";
        $messageClass = "alert-warning";
    }
}
?>

<div class="container mt-4">
    <h2 class="text-center mb-4">Réinitialisation des résultats</h2>
    
    <?php if ($message): ?>
        <div class="alert <?php echo $messageClass; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if ($messageClass !== "alert-success"): ?>
        <div class="card">
            <div class="card-body">
                <p class="card-text">Êtes-vous sûr de vouloir réinitialiser tous vos résultats ? Cette action est irréversible.</p>
                <form method="POST">
                    <div class="mb-3">
                        <label for="confirmation_text" class="form-label">Pour confirmer, écrivez "supprimer" ci-dessous :</label>
                        <input type="text" class="form-control" id="confirmation_text" name="confirmation_text" required>
                    </div>
                    <button type="submit" name="confirm_reset" class="btn btn-danger">Confirmer la réinitialisation</button>
                    <a href="practice.php" class="btn btn-secondary">Annuler</a>
                </form>
            </div>
        </div>
    <?php else: ?>
        <a href="practice.php" class="btn btn-primary">Retour à l'entraînement</a>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>