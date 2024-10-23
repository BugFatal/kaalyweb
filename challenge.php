<?php
session_start();
require_once 'config.php';  // S'assurer que la configuration est chargée
require_once 'badges-config.php';
// Configuration du jeu
$GAME_DURATION = 10; // 2 minutes en secondes
$POINTS_BASE = 100;   // Points de base pour une bonne réponse
$TIME_BONUS = 10;     // Points bonus par seconde restante
$STREAK_BONUS = 50;   // Points bonus par réponse consécutive correcte

// Récupération de l'identifiant de l'utilisateur
$user_id = $_COOKIE['SSOwAuthUser'] ?? 'anonymous';
if (empty($user_id) || $user_id === null) {
    error_log("SSOwAuthUser non trouvé dans les cookies de session");
    $user_id = 'anonymous_' . uniqid();
}


// Fonction pour vérifier si le temps de jeu est dépassé
// Fonction pour vérifier si le temps de jeu est dépassé
function isGameTimeExceeded() {
    global $GAME_DURATION;
    
    if (!isset($_SESSION['game_state']) || !isset($_SESSION['game_state']['start_time'])) {
        error_log("Session ou temps de démarrage non définis - Initialisation d'une nouvelle session");
        initGameSession();  // Initialiser une nouvelle session si nécessaire
        return false;  // Permettre le début du jeu
    }
    
    $elapsed_time = time() - $_SESSION['game_state']['start_time'];
    error_log("Temps écoulé: $elapsed_time secondes");
    return $elapsed_time > $GAME_DURATION;
}
// Initialiser la session de jeu
initGameSession();

// Désactiver l'affichage des erreurs pour les requêtes AJAX
if (isset($_POST['action'])) {
    error_reporting(0);
    ini_set('display_errors', 0);
}



// Traitement des requêtes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_once 'config.php';
    require_once 'badges-config.php';
    
    header('Content-Type: application/json');
    
    try {
        $response = [
            'success' => false,
            'error' => null,
            'message' => null
        ];
        
        switch ($_POST['action']) {
            case 'submit_answer':
                $response = handleAnswer($_POST);
                break;
                
            case 'end_game':
                $response = endGame();
                break;
                
            default:
                $response['error'] = 'invalid_action';
                $response['message'] = 'Action non valide';
        }
        
        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'server_error',
            'message' => $e->getMessage()
        ]);
        exit;
    }
}


// Le reste du code PHP pour l'affichage de la page...
require_once 'config.php';
require_once 'badges-config.php';




// Initialisation de la session de jeu si nouvelle partie
function initGameSession() {
    if (!isset($_SESSION['game_state']) || isset($_GET['new_game'])) {
        $_SESSION['game_state'] = [
            'active' => true,
            'start_time' => time(),
            'score' => 0,
            'questions_answered' => 0,
            'correct_answers' => 0,
            'current_streak' => 0,
            'best_streak' => 0,
            'difficulty' => $_GET['difficulty'] ?? 'easy',
            'initialized' => true // Nouveau flag
        ];
        
        error_log("Nouvelle session initialisée: " . json_encode($_SESSION['game_state']));
        return true;
    }
    return false;
}
    
    // Si c'est une requête AJAX pour une nouvelle partie
    if (isset($_GET['new_game']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Session réinitialisée'
        ]);
        exit;
    }

// Récupération du défi quotidien
$stmt = $pdo->prepare("
    SELECT * FROM daily_challenges 
    WHERE challenge_date = CURDATE()
");
$stmt->execute();
$dailyChallenge = $stmt->fetch();

if (!$dailyChallenge) {
    // Création d'un nouveau défi quotidien si aucun n'existe
    $difficulties = ['easy', 'medium', 'hard'];
    $randomDifficulty = $difficulties[array_rand($difficulties)];
    $stmt = $pdo->prepare("
        INSERT INTO daily_challenges 
        (challenge_date, target_score, difficulty, time_limit, description)
        VALUES (CURDATE(), ?, ?, ?, ?)
    ");
    $stmt->execute([
        rand(1000, 3000), // Score cible
        $randomDifficulty,
        120, // Temps limite
        "Défi du jour : Obtenez le meilleur score possible en 2 minutes!"
    ]);
    
    $stmt = $pdo->prepare("SELECT * FROM daily_challenges WHERE challenge_date = CURDATE()");
    $stmt->execute();
    $dailyChallenge = $stmt->fetch();
}

function verifyGameSession() {
    if (!isset($_SESSION['game_state']) || 
        !isset($_SESSION['game_state']['initialized']) || 
        !$_SESSION['game_state']['initialized']) {
            
        error_log("Session invalide ou non initialisée");
        return false;
    }
    return true;
}

/**
 * Vérifie l'état du jeu et initialise une nouvelle session si nécessaire
 * @return bool true si la session est valide, false sinon
 */
function checkGameState() {
    if (!isset($_SESSION['game_state'])) {
        error_log("Session game_state non définie - Initialisation d'une nouvelle session");
        initGameSession();
        return false;
    }
    
    if (!isset($_SESSION['game_state']['initialized']) || 
        !$_SESSION['game_state']['initialized']) {
        error_log("Session non initialisée correctement");
        return false;
    }
    
    if (!isset($_SESSION['game_state']['start_time'])) {
        error_log("Temps de démarrage non défini");
        return false;
    }
    
    if (!isset($_SESSION['game_state']['active']) || 
        !$_SESSION['game_state']['active']) {
        error_log("Session inactive");
        return false;
    }
    
    // Vérifier que toutes les propriétés nécessaires sont présentes
    $requiredProperties = [
        'score',
        'questions_answered',
        'correct_answers',
        'current_streak',
        'best_streak',
        'difficulty'
    ];
    
    foreach ($requiredProperties as $prop) {
        if (!isset($_SESSION['game_state'][$prop])) {
            error_log("Propriété manquante: $prop");
            return false;
        }
    }
    
    return true;
}




// Fonction pour gérer les réponses
function handleAnswer($data) {
    // Vérification de l'état du jeu
    if (!checkGameState()) {
        return [
            'success' => false,
            'error' => 'invalid_session',
            'message' => 'Session invalide'
        ];
    }

    // Déclarer l'accès aux variables globales
    global $POINTS_BASE, $TIME_BONUS, $STREAK_BONUS, $GAME_DURATION;
   
    error_log("Début handleAnswer - État de la session: " . json_encode($_SESSION['game_state']));
   
    // Vérification du temps écoulé
    if (isGameTimeExceeded()) {
        error_log("Temps dépassé - start_time: " . $_SESSION['game_state']['start_time'] .
                  ", current_time: " . time());
        return [
            'success' => false,
            'error' => 'time_exceeded',
            'message' => 'Le temps de jeu est écoulé'
        ];
    }
   
    // Validation et conversion des données
    if (!isset($data['answer']) || !isset($data['number']) || !isset($data['multiplier']) || !isset($data['timeSpent'])) {
        return [
            'success' => false,
            'error' => 'invalid_data',
            'message' => 'Données manquantes'
        ];
    }

    $userAnswer = (int) $data['answer'];
    $correctAnswer = (int) $data['number'] * (int) $data['multiplier'];
    $timeSpent = (int) $data['timeSpent'];
    $isCorrect = ($userAnswer === $correctAnswer);
   
    // Calcul des points
    $points = 0;
    if ($isCorrect) {
        try {
            $points = $POINTS_BASE;
            // Bonus de temps (plus rapide = plus de points)
            $points += max(0, $TIME_BONUS * (5 - $timeSpent));
            // Bonus de série
            if ($_SESSION['game_state']['current_streak'] > 0) {
                $points += $STREAK_BONUS * $_SESSION['game_state']['current_streak'];
            }
            $_SESSION['game_state']['current_streak']++;
            $_SESSION['game_state']['best_streak'] = max(
                $_SESSION['game_state']['best_streak'],
                $_SESSION['game_state']['current_streak']
            );
            $_SESSION['game_state']['correct_answers']++;
        } catch (Exception $e) {
            error_log("Erreur lors du calcul des points: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'calculation_error',
                'message' => 'Erreur lors du calcul des points'
            ];
        }
    } else {
        $_SESSION['game_state']['current_streak'] = 0;
    }
   
    // Mise à jour du score
    try {
        $_SESSION['game_state']['score'] += $points;
        $_SESSION['game_state']['questions_answered']++;
    } catch (Exception $e) {
        error_log("Erreur lors de la mise à jour du score: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'update_error',
            'message' => 'Erreur lors de la mise à jour du score'
        ];
    }
   
    // Générer la prochaine question
    try {
        $nextQuestion = generateQuestion($_SESSION['game_state']['difficulty']);
    } catch (Exception $e) {
        error_log("Erreur lors de la génération de la question: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'question_error',
            'message' => 'Erreur lors de la génération de la question'
        ];
    }
   
    // Logger l'état final pour le débogage
    error_log("Fin handleAnswer - Nouvel état: " . json_encode([
        'score' => $_SESSION['game_state']['score'],
        'streak' => $_SESSION['game_state']['current_streak'],
        'questions' => $_SESSION['game_state']['questions_answered']
    ]));

    return [
        'success' => true,
        'isCorrect' => $isCorrect,
        'points' => $points,
        'totalScore' => $_SESSION['game_state']['score'],
        'streak' => $_SESSION['game_state']['current_streak'],
        'correctAnswer' => $correctAnswer,
        'nextQuestion' => $nextQuestion
    ];
}

// Fonction pour générer une question
function generateQuestion($difficulty) {
    $maxMultiplier = [
        'easy' => 10,
        'medium' => 20,
        'hard' => 30
    ][$difficulty] ?? 10;

    return [
        'number' => rand(1, 10),
        'multiplier' => rand(1, $maxMultiplier)
    ];
}


function endGame() {
    global $pdo, $GAME_DURATION;
    
    try {
        // Vérifier que $pdo existe et est une instance de PDO
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            error_log("PDO n'est pas initialisé correctement");
            throw new Exception("Erreur de connexion à la base de données");
        }
 
        // Récupération sécurisée du user_id depuis le cookie SSOwAuthUser
        $user_id = $_COOKIE['SSOwAuthUser'] ?? null;
        if (empty($user_id)) {
            error_log("SSOwAuthUser non trouvé dans les cookies de session pendant endGame");
            $user_id = 'anonymous_' . uniqid();
        }
 
        // Vérifier l'état de la session
        if (!isset($_SESSION['game_state'])) {
            throw new Exception('Aucune partie en cours');
        }
        
        // Récupérer le défi quotidien
        $stmt = $pdo->prepare("SELECT * FROM daily_challenges WHERE challenge_date = CURDATE()");
        $stmt->execute();
        $dailyChallenge = $stmt->fetch();
        
        if (!$dailyChallenge) {
            throw new Exception("Défi quotidien non trouvé");
        }
 
        $gameState = $_SESSION['game_state'];
        
        // Vérifier que le temps de jeu est valide
        $total_time = time() - $gameState['start_time'];
        if ($total_time > $GAME_DURATION + 5) {
            $ratio = $GAME_DURATION / $total_time;
            $gameState['score'] = (int)($gameState['score'] * $ratio);
        }
        
        // Calcul des étoiles
        $stars = 1;
        if ($gameState['score'] >= 2000) $stars = 2;
        if ($gameState['score'] >= 3500) $stars = 3;
        
        try {
            // Début de la transaction
            $pdo->beginTransaction();
            
            // Enregistrement de la tentative du défi quotidien
            $challenge_completed = $gameState['score'] >= $dailyChallenge['target_score'];
            $stmt = $pdo->prepare("
                INSERT INTO daily_challenge_attempts 
                (user_id, challenge_id, score, completed, attempt_date)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            if (!$stmt->execute([
                $user_id,
                $dailyChallenge['id'],
                $gameState['score'],
                $challenge_completed ? 1 : 0
            ])) {
                throw new Exception("Erreur lors de l'insertion de la tentative");
            }
            
            // Préparer les stats pour les badges
            $stats = [
                'total_games' => 1,
                'questions_answered' => $gameState['questions_answered'],
                'correct_answers' => $gameState['correct_answers'],
                'total_time' => min($total_time, $GAME_DURATION),
                'best_streak' => $gameState['best_streak'],
                'total_score' => $gameState['score'],
                'is_daily_best' => false,
                'badges_earned' => 0
            ];
 
            // Vérifier si c'est le meilleur score du jour
            $stmt = $pdo->prepare("
                SELECT MAX(score) as max_score 
                FROM challenge_scores 
                WHERE user_id = ? AND DATE(date_played) = CURDATE()
            ");
            $stmt->execute([$user_id]);
            $maxScore = $stmt->fetchColumn();
            $stats['is_daily_best'] = ($gameState['score'] > ($maxScore ?? 0));
            
            // Récupérer le classement du jour pour ce défi
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as rank_position
                FROM daily_challenge_attempts
                WHERE challenge_id = ? AND score > ?
            ");
            $stmt->execute([$dailyChallenge['id'], $gameState['score']]);
            $rank = $stmt->fetchColumn() + 1;
            
            // Enregistrement du score général
            $stmt = $pdo->prepare("
                INSERT INTO challenge_scores 
                (user_id, score, questions_answered, correct_answers, time_taken, difficulty, date_played, stars)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            
            if (!$stmt->execute([
                $user_id,
                $gameState['score'],
                $gameState['questions_answered'],
                $gameState['correct_answers'],
                min($total_time, $GAME_DURATION),
                $gameState['difficulty'],
                $stars
            ])) {
                throw new Exception("Erreur lors de l'insertion du score");
            }
            
            // Vérification et attribution des badges
            $newBadges = checkAndAwardBadges($user_id, $stats);
            
            // Récupération des meilleurs scores
            $stmt = $pdo->prepare("
                SELECT user_id, score, date_played 
                FROM challenge_scores 
                ORDER BY score DESC 
                LIMIT 10
            ");
            $stmt->execute();
            $highScores = $stmt->fetchAll();
            
            // Validation de la transaction
            $pdo->commit();
            
            // Nettoyage de la session
            unset($_SESSION['game_state']);
            
            return [
                'success' => true,
                'finalScore' => $gameState['score'],
                'stars' => $stars,
                'questionsAnswered' => $gameState['questions_answered'],
                'correctAnswers' => $gameState['correct_answers'],
                'bestStreak' => $gameState['best_streak'],
                'highScores' => $highScores ?? [],
                'earnedBadges' => $newBadges ?? [],
                'dailyChallenge' => [
                    'completed' => $challenge_completed ?? false,
                    'targetScore' => $dailyChallenge['target_score'] ?? 0,
                    'rank' => $rank ?? 1,
                    'difficulty' => $dailyChallenge['difficulty'] ?? 'easy'
                ]
            ];
            
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erreur SQL dans endGame: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Erreur dans endGame: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
        
        // Assurez-vous que toute transaction ouverte est annulée
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        return [
            'success' => false,
            'error' => 'game_error',
            'message' => "Une erreur est survenue lors de la finalisation du jeu",
            'debug_info' => [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ];
    }
 }

$pageTitle = 'Challenge - Tables de Multiplication';
include 'header.php';
?>

<!-- Suite du fichier challenge.php -->

<style>
/* Styles pour le jeu */
.game-container {
    max-width: 800px;
    margin: 0 auto;
    position: relative;
}

.game-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.timer {
    font-size: 2rem;
    font-weight: bold;
    color: #dc3545;
}

.timer.warning {
    animation: pulse 1s infinite;
}

.score-display {
    font-size: 1.5rem;
    color: #28a745;
}

.streak-counter {
    font-size: 1.2rem;
    color: #007bff;
}

.question-container {
    text-align: center;
    padding: 2rem;
    background: white;
    border-radius: 15px;
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
}

.question {
    font-size: 3rem;
    margin-bottom: 1.5rem;
    color: #343a40;
}

.answer-input {
    font-size: 2rem;
    width: 200px;
    text-align: center;
    margin-bottom: 1rem;
    padding: 0.5rem;
    border: 3px solid #007bff;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.answer-input:focus {
    border-color: #0056b3;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.points-popup {
    position: absolute;
    font-size: 1.5rem;
    font-weight: bold;
    opacity: 0;
    pointer-events: none;
}

.game-dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.dashboard-card {
    background: white;
    padding: 1rem;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.stars-display {
    font-size: 2rem;
    color: #ffc107;
    margin-bottom: 1rem;
}

/* Animations */
@keyframes slideUp {
    0% { transform: translateY(0); opacity: 1; }
    100% { transform: translateY(-50px); opacity: 0; }
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

/* Modal de fin de partie */
.game-over-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    z-index: 1000;
}

.modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 2rem;
    border-radius: 15px;
    width: 90%;
    max-width: 600px;
    text-align: center;
}

.results-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin: 1.5rem 0;
}

.result-item {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
}

/* Tableau des scores */
.highscores-table {
    width: 100%;
    margin-top: 1.5rem;
    border-collapse: collapse;
}

.highscores-table th,
.highscores-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #dee2e6;
}

.badge-earned {
    animation: fadeInScale 0.5s ease-out;
}

@keyframes fadeInScale {
    from {
        opacity: 0;
        transform: scale(0.5);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Daily Challenge Banner */
.daily-challenge {
    background: linear-gradient(45deg, #ff6b6b, #cc2e5d);
    color: white;
    padding: 1rem;
    border-radius: 10px;
    margin-bottom: 2rem;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.badge-earned {
    display: inline-block;
    margin: 10px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    text-align: center;
}

.badge-earned i {
    font-size: 2rem;
    margin-bottom: 10px;
}

.badge-earned p {
    margin: 5px 0;
    font-weight: bold;
}

.badge-earned small {
    display: block;
    color: #6c757d;
}

.badges-container {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 15px;
    margin: 20px 0;
}

</style>

<div class="container">
    <div class="game-container">
        <!-- Défi quotidien -->
        <div class="daily-challenge">
            <h3>Défi du Jour</h3>
            <p><?php echo htmlspecialchars($dailyChallenge['description']); ?></p>
            <p>Objectif : <?php echo number_format($dailyChallenge['target_score']); ?> points</p>
        </div>

        <!-- En-tête du jeu -->
        <div class="game-header">
            <div class="timer" id="timer">2:00</div>
            <div class="score-display">Score: <span id="score">0</span></div>
            <div class="streak-counter">Série: <span id="streak">0</span></div>
        </div>

        <!-- Question -->
        <div class="question-container">
            <div class="question" id="question">
                <span id="number"></span> × <span id="multiplier"></span> = ?
            </div>
            <input type="number" id="answer" class="answer-input" autofocus>
        </div>

        <!-- Tableau de bord -->
        <div class="game-dashboard">
            <div class="dashboard-card">
                <h4>Questions</h4>
                <p id="questions-count">0</p>
            </div>
            <div class="dashboard-card">
                <h4>Précision</h4>
                <p id="accuracy">0%</p>
            </div>
            <div class="dashboard-card">
                <h4>Meilleure série</h4>
                <p id="best-streak">0</p>
            </div>
        </div>

        <!-- Modal de fin de partie -->
        <div class="game-over-modal" id="gameOverModal">
            <div class="modal-content">
                <h2>Partie terminée!</h2>
                <div class="stars-display" id="starsEarned"></div>
                <div class="results-grid">
                    <div class="result-item">
                        <h4>Score final</h4>
                        <p id="finalScore">0</p>
                    </div>
                    <div class="result-item">
                        <h4>Questions</h4>
                        <p id="finalQuestions">0</p>
                    </div>
                    <div class="result-item">
                        <h4>Précision</h4>
                        <p id="finalAccuracy">0%</p>
                    </div>
                </div>
                <div id="earnedBadges" class="earned-badges"></div>
                <h3>Meilleurs scores</h3>
                <table class="highscores-table">
                    <thead>
                        <tr>
                            <th>Rang</th>
                            <th>Joueur</th>
                            <th>Score</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody id="highscoresList"></tbody>
                </table>
                <button class="btn btn-primary mt-3" onclick="startNewGame()">Rejouer</button>
                <a href="index.php" class="btn btn-secondary mt-3">Retour à l'accueil</a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/howler/2.2.3/howler.min.js"></script>
<script>
// Configuration du jeu
const GAME_DURATION = <?php echo $GAME_DURATION; ?>;
const SOUNDS = {
    correct: new Howl({ src: ['sounds/correct.mp3'], volume: 0.5 }),
    wrong: new Howl({ src: ['sounds/wrong.mp3'], volume: 0.5 }),
    countdown: new Howl({ src: ['sounds/countdown.mp3'], volume: 0.3 }),
    gameOver: new Howl({ src: ['sounds/gameover.mp3'], volume: 0.5 })
};

// État du jeu
let gameState = {
    timeLeft: GAME_DURATION,
    score: 0,
    streak: 0,
    bestStreak: 0,
    questionsAnswered: 0,
    correctAnswers: 0,
    currentQuestion: null,
    timer: null,
    questionStartTime: null
};

// Éléments du DOM
const elements = {
    timer: document.getElementById('timer'),
    score: document.getElementById('score'),
    streak: document.getElementById('streak'),
    question: document.getElementById('question'),
    number: document.getElementById('number'),
    multiplier: document.getElementById('multiplier'),
    answer: document.getElementById('answer'),
    questionsCount: document.getElementById('questions-count'),
    accuracy: document.getElementById('accuracy'),
    bestStreak: document.getElementById('best-streak'),
    gameOverModal: document.getElementById('gameOverModal')
};

// Initialisation du jeu
// Dans initGame
async function initGame() {
    try {
        const response = await fetch('challenge.php?new_game=1', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'Erreur d\'initialisation');
        }
        
        // Réinitialiser l'état du jeu avec timeLeft explicitement défini
        gameState = {
            timeLeft: GAME_DURATION,  // S'assure que timeLeft est défini
            score: 0,
            streak: 0,
            bestStreak: 0,
            questionsAnswered: 0,
            correctAnswers: 0,
            currentQuestion: null,
            timer: null,
            questionStartTime: null
        };
        
        updateTimer();
        generateNewQuestion();
        startTimer();
        
        elements.answer.addEventListener('keyup', handleAnswer);
    } catch (error) {
        console.error('Error initializing game:', error);
        alert('Erreur lors de l\'initialisation du jeu. Veuillez rafraîchir la page.');
    }
}


// Gestion du chronomètre
function startTimer() {
    gameState.timer = setInterval(() => {
        gameState.timeLeft--;
        updateTimer();

        if (gameState.timeLeft <= 10) {
            elements.timer.classList.add('warning');
            SOUNDS.countdown.play();
        }

        if (gameState.timeLeft <= 0) {  // Vérification directe au lieu d'utiliser isGameTimeExceededClient()
            endGame();
        }
    }, 1000);
}

function updateTimer() {
    const minutes = Math.floor(gameState.timeLeft / 60);
    const seconds = gameState.timeLeft % 60;
    elements.timer.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
}



// Génération des questions
async function generateNewQuestion() {
    gameState.questionStartTime = Date.now();
    
    // Si une question est déjà définie, l'utiliser
    if (gameState.currentQuestion) {
        elements.number.textContent = gameState.currentQuestion.number;
        elements.multiplier.textContent = gameState.currentQuestion.multiplier;
    } else {
        // Sinon, en générer une nouvelle
        gameState.currentQuestion = {
            number: Math.floor(Math.random() * 10) + 1,
            multiplier: Math.floor(Math.random() * 10) + 1
        };
        elements.number.textContent = gameState.currentQuestion.number;
        elements.multiplier.textContent = gameState.currentQuestion.multiplier;
    }
    
    elements.answer.value = '';
    elements.answer.focus();
}

// Gestion des réponses
async function handleAnswer(e) {
    if (e.key !== 'Enter') return;
    
    const answerInput = elements.answer.value.trim();
    if (!answerInput) return;
    
    const userAnswer = parseInt(answerInput);
    const correctAnswer = gameState.currentQuestion.number * gameState.currentQuestion.multiplier;
    const timeSpent = (Date.now() - gameState.questionStartTime) / 1000;

    const response = await submitAnswer({
        answer: userAnswer,
        number: gameState.currentQuestion.number,
        multiplier: gameState.currentQuestion.multiplier,
        timeSpent: timeSpent
    });

    if (response) {
        handleAnswerResponse(response, correctAnswer);
    }
}

async function submitAnswer(answerData) {
    try {
        const formData = new FormData();
        formData.append('action', 'submit_answer');
        Object.keys(answerData).forEach(key => {
            formData.append(key, answerData[key]);
        });

        const response = await fetch('challenge.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Réponse du serveur:', data); // Pour débugger
        
        if (data.error) {
            throw new Error(data.message || 'Erreur serveur');
        }
        
        return data;
    } catch (error) {
        console.error('Error submitting answer:', error);
        return null;
    }
}

async function submitFinalScore() {
    try {
        const formData = new FormData();
        formData.append('action', 'end_game');
        
        const response = await fetch('challenge.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error('Error submitting final score:', error);
        return null;
    }
}

function handleAnswerResponse(response, correctAnswer) {
    if (!response || response.error) {
        if (response?.error === 'time_exceeded') {
            endGame();
            return;
        }
        console.log('Réponse invalide ou erreur:', response);
        return;
    }

    gameState.questionsAnswered++;
    console.log('isCorrect:', response.isCorrect);
    
    if (response.isCorrect) {
        gameState.correctAnswers++;
        gameState.streak++;
        gameState.score += response.points;
        showPointsAnimation(response.points);
        SOUNDS.correct.play();
    } else {
        gameState.streak = 0;
        SOUNDS.wrong.play();
        showWrongAnswerAnimation(correctAnswer);
    }

    updateUI();

    // Utiliser la vérification directe
    if (gameState.timeLeft > 0) {
        if (response.nextQuestion) {
            gameState.currentQuestion = response.nextQuestion;
            elements.number.textContent = response.nextQuestion.number;
            elements.multiplier.textContent = response.nextQuestion.multiplier;
            elements.answer.value = '';
            elements.answer.focus();
            gameState.questionStartTime = Date.now();
        } else {
            generateNewQuestion();
        }
    }
}

// Animations et effets visuels
function showPointsAnimation(points) {
    const pointsElement = document.createElement('div');
    pointsElement.className = 'points-popup';
    pointsElement.textContent = `+${points}`;
    pointsElement.style.color = '#28a745';
    
    const answerRect = elements.answer.getBoundingClientRect();
    pointsElement.style.left = `${answerRect.left}px`;
    pointsElement.style.top = `${answerRect.top}px`;
    
    document.body.appendChild(pointsElement);
    
    requestAnimationFrame(() => {
        pointsElement.style.animation = 'slideUp 1s ease-out';
        setTimeout(() => pointsElement.remove(), 1000);
    });
}

function showWrongAnswerAnimation(correctAnswer) {
    elements.answer.classList.add('shake');
    setTimeout(() => elements.answer.classList.remove('shake'), 500);
    
    const correctAnswerElement = document.createElement('div');
    correctAnswerElement.className = 'correct-answer-popup';
    correctAnswerElement.textContent = `${correctAnswer}`;
    // Ajouter l'animation pour afficher la bonne réponse
}

// Mise à jour de l'interface
function updateUI() {
    elements.score.textContent = gameState.score;
    elements.streak.textContent = gameState.streak;
    elements.questionsCount.textContent = gameState.questionsAnswered;
    elements.accuracy.textContent = `${Math.round((gameState.correctAnswers / gameState.questionsAnswered) * 100)}%`;
    elements.bestStreak.textContent = Math.max(gameState.streak, gameState.bestStreak);
}

// Fin de partie
async function endGame() {
    clearInterval(gameState.timer);
    elements.answer.disabled = true;
    elements.answer.removeEventListener('keyup', handleAnswer);
    
    try {
        const formData = new FormData();
        formData.append('action', 'end_game');
        
        const response = await fetch('challenge.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const finalData = await response.json();
        console.log('Réponse finale du serveur:', finalData); // Pour débugger
        
        if (finalData.error) {
            console.error('Erreur détaillée:', finalData.debug_info); // Log les détails de l'erreur
            throw new Error(finalData.message || 'Erreur serveur');
        }
        
        showGameOverModal(finalData);
        
        if (finalData.dailyChallenge?.completed) {
            new Audio('sounds/success.mp3').play();
        } else {
            SOUNDS.gameOver.play();
        }
    } catch (error) {
        console.error('Erreur complète de fin de partie:', error);
        console.error('Stack trace:', error.stack);
        
        alert('Une erreur est survenue lors de la sauvegarde des résultats. Vos scores seront affichés localement.');
        
        showGameOverModal({
            success: true,
            finalScore: gameState.score,
            stars: Math.max(1, Math.floor(gameState.score / 2000) + 1),
            questionsAnswered: gameState.questionsAnswered,
            correctAnswers: gameState.correctAnswers,
            bestStreak: gameState.bestStreak,
            highScores: [],
            earnedBadges: [],
            dailyChallenge: {
                completed: false,
                targetScore: 0,
                rank: 1,
                difficulty: 'easy'
            }
        });
    }
}


function showGameOverModal(data) {
    const starsDisplay = document.getElementById('starsEarned');
    starsDisplay.innerHTML = '⭐'.repeat(data.stars);
    
    document.getElementById('finalScore').textContent = data.finalScore;
    document.getElementById('finalQuestions').textContent = data.questionsAnswered;
    document.getElementById('finalAccuracy').textContent = 
        `${Math.round((data.correctAnswers / data.questionsAnswered) * 100)}%`;

    // Afficher les résultats du défi quotidien
    const dailyResultsHTML = `
        <div class="daily-challenge-results mt-4">
            <h4>Résultats du Défi du Jour</h4>
            <div class="alert ${data.dailyChallenge.completed ? 'alert-success' : 'alert-info'}">
                ${data.dailyChallenge.completed ? 
                    '<i class="fas fa-trophy"></i> Défi réussi !' : 
                    'Continuez vos efforts !'}
            </div>
            <div class="result-details">
                <p>Votre score: ${data.finalScore} / Objectif: ${data.dailyChallenge.targetScore}</p>
                <p>Classement actuel: ${data.dailyChallenge.rank}${getOrdinalSuffix(data.dailyChallenge.rank)}</p>
            </div>
        </div>
    `;
    
    // Insérer les résultats du défi avant les badges
    const badgesContainer = document.getElementById('earnedBadges');
    badgesContainer.insertAdjacentHTML('beforebegin', dailyResultsHTML);

    // Afficher les badges et scores comme avant
    badgesContainer.innerHTML = '';
    data.earnedBadges?.forEach(badge => {
        const badgeElement = document.createElement('div');
        badgeElement.className = 'badge-earned';
        badgeElement.innerHTML = `
            <i class="${badge.icon}"></i>
            <p>${badge.name}</p>
            <small>${badge.description}</small>
        `;
        badgesContainer.appendChild(badgeElement);
    });

    // Meilleurs scores
    const highscoresList = document.getElementById('highscoresList');
    highscoresList.innerHTML = data.highScores.map((score, index) => `
        <tr>
            <td>${index + 1}</td>
            <td>${score.user_id}</td>
            <td>${score.score}</td>
            <td>${new Date(score.date_played).toLocaleDateString()}</td>
        </tr>
    `).join('');

    // Afficher le modal
    elements.gameOverModal.style.display = 'block';
    
    // Jouer le son approprié selon le résultat
    if (data.dailyChallenge.completed) {
        new Audio('sounds/success.mp3').play();
    } else {
        SOUNDS.gameOver.play();
    }
}

// Fonction utilitaire pour les suffixes ordinaux
function getOrdinalSuffix(n) {
    if (n === 1) return 'er';
    return 'ème';
}

// Modifier la fonction startNewGame
async function startNewGame() {
    try {
        // Faire une requête au serveur pour réinitialiser la session
        const response = await fetch('challenge.php?new_game=1', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) {
            throw new Error('Erreur lors de la réinitialisation de la session');
        }
        
        // Réinitialiser l'interface
        elements.gameOverModal.style.display = 'none';
        elements.answer.disabled = false;
        
        // Réinitialiser l'état du jeu
        gameState = {
            timeLeft: GAME_DURATION,  // S'assure que timeLeft est défini
            score: 0,
            streak: 0,
            bestStreak: 0,
            questionsAnswered: 0,
            correctAnswers: 0,
            currentQuestion: null,
            timer: null,
            questionStartTime: null
        };
        
        // Supprimer l'ancien écouteur d'événements avant d'en ajouter un nouveau
        elements.answer.removeEventListener('keyup', handleAnswer);
        elements.answer.addEventListener('keyup', handleAnswer);
        
        // Mettre à jour l'interface et démarrer une nouvelle partie
        updateUI();
        initGame();
    } catch (error) {
        console.error('Erreur lors du redémarrage:', error);
        alert('Erreur lors du redémarrage du jeu. Veuillez rafraîchir la page.');
    }
}

// Démarrer le jeu au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    initGame();
    elements.answer.addEventListener('keyup', handleAnswer);
});
</script>
</body>
</html>