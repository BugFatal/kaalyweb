<?php
// public/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

$pageTitle = 'Accueil - Tables de Multiplication';
include 'header.php';
?>

<h1 class="text-center mb-4">Tables de Multiplication</h1>

<!-- Tableau de toutes les tables -->
<div class="card mb-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered multiplication-table">
                <thead class="table-primary">
                    <tr>
                        <th class="text-center">×</th>
                        <?php for($i = 1; $i <= 10; $i++): ?>
                            <th class="text-center"><?php echo $i; ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php for($i = 1; $i <= 10; $i++): ?>
                        <tr>
                            <th class="text-center table-primary"><?php echo $i; ?></th>
                            <?php for($j = 1; $j <= 10; $j++): ?>
                                <td class="text-center" data-row="<?php echo $i; ?>" data-col="<?php echo $j; ?>"><?php echo $i * $j; ?></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Navigation vers les tables individuelles -->
<h3 class="text-center mb-3">Tables individuelles</h3>
<div class="row g-2">
    <?php for($i = 1; $i <= 10; $i++): ?>
        <div class="col-6 col-sm-4 col-md-3">
            <a href="table.php?number=<?php echo $i; ?>" class="btn btn-primary w-100 table-button">
                Table de <?php echo $i; ?>
            </a>
        </div>
    <?php endfor; ?>
    <div class="col-12 mt-3">
        <a href="practice.php" class="btn btn-success w-100 py-3">S'entraîner à toutes les tables</a>
    </div>
</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

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

</body>
</html>