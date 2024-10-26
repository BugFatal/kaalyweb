<?php
// public/table.php
require_once 'config.php';

if (isset($_GET['number']) && is_numeric($_GET['number'])) {
    $number = (int) $_GET['number'];
    if ($number < 1 || $number > 10) {
        header('Location: index.php');
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}

$pageTitle = "Table de $number - Tables de Multiplication";
include 'header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg">
                <div class="card-header bg-primary text-white py-3">
                    <h2 class="text-center mb-0 display-5">Table de <?php echo $number; ?></h2>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <?php for($i = 1; $i <= 10; $i++): ?>
                            <div class="col-md-6 mb-3">
                                <div class="multiplication-card" onclick="revealResult(this)">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="operation">
                                                    <span class="number1"><?php echo $number; ?></span>
                                                    <span class="operator">×</span>
                                                    <span class="number2"><?php echo $i; ?></span>
                                                    <span class="equals">=</span>
                                                </div>
                                                <div class="result">
                                                    <span class="result-number"><?php echo $number * $i; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="card-footer text-center py-3">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <a href="practice_a_table.php?table=<?php echo $number; ?>" class="btn btn-success btn-lg">
                            <i class="fas fa-graduation-cap me-2"></i>S'entraîner sur la table de <?php echo $number; ?>
                        </a>
                    </div>
                    <div class="mt-3">
                        <a href="index.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-home me-1"></i>Accueil
                        </a>
                        <a href="practice.php" class="btn btn-outline-info">
                            <i class="fas fa-random me-1"></i>Toutes les tables
                        </a>
                    </div>
                </div>
            </div>

            <!-- Navigation entre les tables -->
            <div class="d-flex justify-content-between mt-4">
                <?php if ($number > 1): ?>
                    <a href="table.php?number=<?php echo $number - 1; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-chevron-left"></i> Table de <?php echo $number - 1; ?>
                    </a>
                <?php else: ?>
                    <div></div>
                <?php endif; ?>

                <?php if ($number < 10): ?>
                    <a href="table.php?number=<?php echo $number + 1; ?>" class="btn btn-outline-primary">
                        Table de <?php echo $number + 1; ?> <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <div></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function revealResult(element) {
    // Ajoute une classe pour l'animation de pulse
    element.querySelector('.result').style.animation = 'none';
    element.querySelector('.result').offsetHeight; // Trigger reflow
    element.querySelector('.result').style.animation = 'pulse 0.5s ease';

    // Change la couleur de fond temporairement
    const card = element.querySelector('.card');
    const originalBg = card.style.backgroundColor;
    card.style.backgroundColor = '#e8f5e9';
    setTimeout(() => {
        card.style.backgroundColor = originalBg;
    }, 500);
}

// Animation aléatoire toutes les 3 secondes
setInterval(() => {
    const cards = document.querySelectorAll('.multiplication-card');
    const randomCard = cards[Math.floor(Math.random() * cards.length)];
    revealResult(randomCard);
}, 3000);
</script>

<!-- Ajout de Font Awesome pour les icônes -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<?php include 'footer.php'; ?>
