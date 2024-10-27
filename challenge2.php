<?php
session_start();
require_once 'config.php';
require_once 'badges-config.php';
require_once 'challenge-manager.php';

// Définir la classe spécifique pour cette page
$pageSpecificClass = 'challenge-page';

// Initialisation du gestionnaire de défis
$challengeManager = new DailyChallenge($pdo);

// Récupération des paramètres de jeu depuis le défi du jour
$gameParams = $challengeManager->getGameParameters();
$GAME_DURATION = $gameParams['GAME_DURATION'];
$POINTS_BASE = $gameParams['POINTS_BASE'];
$TIME_BONUS = $gameParams['TIME_BONUS'];
$STREAK_BONUS = $gameParams['STREAK_BONUS'];

// Définir un état "isEnding" dans la session pour le contrôle côté serveur
if (!isset($_SESSION['isEnding'])) {
    $_SESSION['isEnding'] = false;
}

// Récupération de l'identifiant utilisateur
$user_id = $_COOKIE['SSOwAuthUser'] ?? 'anonymous_' . uniqid();
if (empty($user_id)) {
    $user_id = 'anonymous_' . uniqid();
}

// Initialisation de la session de jeu
function initGameSession() {
    $_SESSION['game_state'] = [
        'start_time' => time(),
        'score' => 0,
        'questions_answered' => 0,
        'correct_answers' => 0,
        'current_streak' => 0,
        'best_streak' => 0,
        'game_ended' => false
    ];
}

// Vérifier si le temps de jeu est dépassé
function isGameTimeExceeded() {
    global $GAME_DURATION;
    $elapsed_time = time() - $_SESSION['game_state']['start_time'];
    return $elapsed_time > $GAME_DURATION;
}

// Gérer les réponses des joueurs
function handleAnswer($data) {
    global $POINTS_BASE, $TIME_BONUS, $STREAK_BONUS;

    if ($_SESSION['game_state']['game_ended']) {
        return ['success' => false, 'error' => 'Game already ended'];
    }

    if (isGameTimeExceeded()) {
        return endGame();
    }

    $userAnswer = (int)$data['answer'];
    $correctAnswer = (int)$data['number'] * (int)$data['multiplier'];
    $isCorrect = $userAnswer === $correctAnswer;

    // Calculer les points
    $points = 0;
    if ($isCorrect) {
        $points = $POINTS_BASE;
        $points += max(0, $TIME_BONUS * (5 - (int)$data['timeSpent']));
        if ($_SESSION['game_state']['current_streak'] > 0) {
            $points += $STREAK_BONUS * $_SESSION['game_state']['current_streak'];
        }
        $_SESSION['game_state']['correct_answers']++;
        $_SESSION['game_state']['current_streak']++;
        $_SESSION['game_state']['best_streak'] = max(
            $_SESSION['game_state']['best_streak'],
            $_SESSION['game_state']['current_streak']
        );
    } else {
        $_SESSION['game_state']['current_streak'] = 0;
    }

    $_SESSION['game_state']['score'] += $points;
    $_SESSION['game_state']['questions_answered']++;

    return [
        'success' => true,
        'isCorrect' => $isCorrect,
        'points' => $points,
        'totalScore' => $_SESSION['game_state']['score']
    ];
}

// Traitement des requêtes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_POST['action']) {
            case 'submit_answer':
                $response = handleAnswer($_POST);
                break;
            case 'end_game':
                $response = endGame();
                break;
            default:
                throw new Exception("Action non valide");
        }
        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Récupération du défi quotidien avec le nouveau gestionnaire
$dailyChallenge = $challengeManager->getDailyChallenge();

// Inclusion du header après avoir défini toutes les variables nécessaires
$pageTitle = 'Challenge du Jour';
include 'header.php';

?>

<div class="challenge-page">
    <div class="container challenge-container">
        <div class="game-container">
            <!-- Écran d'introduction -->
            <div id="intro-screen" class="intro-screen">
                <div class="intro-content">
                    <div class="intro-header">
                        <h1 class="title">Challenge des Tables</h1>
                        <div class="subtitle">Testez votre rapidité en calcul mental !</div>
                    </div>

                    <div class="rules-container">
                        <div class="rules-section">
                            <div class="section-title">
                                <i class="fas fa-gamepad"></i>
                                <h3>Comment jouer ?</h3>
                            </div>
                            <ul class="fancy-list">
                                <li>
                                    <i class="fas fa-clock"></i> 
                                    Résolvez des multiplications en <?php echo $dailyChallenge['time_limit']; ?> secondes
                                </li>
                                <li>
                                    <i class="fas fa-trophy"></i> 
                                    Objectif du jour : <?php echo number_format($dailyChallenge['target_score']); ?> points
                                </li>
                                <li>
                                    <i class="fas fa-star"></i> 
                                    Difficulté : <?php 
                                        $difficultyLabels = [
                                            'easy' => 'Facile',
                                            'medium' => 'Moyen',
                                            'hard' => 'Difficile'
                                        ];
                                        echo $difficultyLabels[$dailyChallenge['difficulty']] ?? 'Normal'; 
                                    ?>
                                </li>
                                <li>
                                    <i class="fas fa-bullseye"></i> 
                                    <?php echo htmlspecialchars($dailyChallenge['description']); ?>
                                </li>
                            </ul>
                        </div>

                        <div class="points-section">
                            <div class="section-title">
                                <i class="fas fa-star"></i>
                                <h3>Système de points</h3>
                            </div>
                            <div class="points-grid">
                                <div class="point-card">
                                    <i class="fas fa-check-circle"></i>
                                    <h4>Base</h4>
                                    <p><?php echo number_format($dailyChallenge['points_base']); ?> points</p>
                                    <small>Par bonne réponse</small>
                                </div>
                                <div class="point-card">
                                    <i class="fas fa-bolt"></i>
                                    <h4>Vitesse</h4>
                                    <p>+<?php echo number_format($dailyChallenge['time_bonus']); ?> points</p>
                                    <small>Bonus rapidité</small>
                                </div>
                                <div class="point-card">
                                    <i class="fas fa-fire-alt"></i>
                                    <h4>Série</h4>
                                    <p>+<?php echo number_format($dailyChallenge['streak_bonus']); ?> points</p>
                                    <small>Par réponse consécutive</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="difficulty-indicator">
                        <div class="difficulty-label">
                            Niveau de difficulté : 
                            <span class="badge bg-<?php 
                                echo $dailyChallenge['difficulty'] === 'easy' ? 'success' : 
                                    ($dailyChallenge['difficulty'] === 'medium' ? 'warning' : 'danger'); 
                            ?>">
                                <?php echo $difficultyLabels[$dailyChallenge['difficulty']] ?? 'Normal'; ?>
                            </span>
                        </div>
                        <?php if ($dailyChallenge['difficulty'] === 'hard'): ?>
                        <small class="text-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Ce défi est particulièrement difficile aujourd'hui !
                        </small>
                        <?php endif; ?>
                    </div>

                    <button id="start-game" class="start-button">
                        <i class="fas fa-play"></i> Je suis prêt !
                    </button>
                </div>
            </div>

         <!-- Contenu du jeu -->
         <div class="game-content" style="display: none;">
                <!-- Question et pavé numérique -->
                <div class="question-container">
                    <div class="question" id="question">
                        <span id="number"></span> × <span id="multiplier"></span> = ?
                    </div>
                    <input type="number" id="answer" class="answer-input" readonly>
                    <div id="numpad" class="numpad-container">
                        <button class="num-btn" data-value="1">1</button>
                        <button class="num-btn" data-value="2">2</button>
                        <button class="num-btn" data-value="3">3</button>
                        <button class="num-btn" data-value="4">4</button>
                        <button class="num-btn" data-value="5">5</button>
                        <button class="num-btn" data-value="6">6</button>
                        <button class="num-btn" data-value="7">7</button>
                        <button class="num-btn" data-value="8">8</button>
                        <button class="num-btn" data-value="9">9</button>
                        <button class="num-btn" data-value="0">0</button>
                        <button class="control-btn correct-btn" id="correct">
                            <i class="fas fa-backspace"></i>
                        </button>
                        <button class="control-btn validate-btn" id="validate">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                </div>

                <!-- Stats sur mobile -->
                <div class="game-stats-mobile">
                    <div class="timer" id="timer">
                        <i class="fas fa-clock"></i> 2:00
                    </div>
                    <div class="score-display">
                        Score: <span id="score">0</span>
                    </div>
                    <div class="streak-counter">
                        <i class="fas fa-fire"></i> Série: <span id="streak">0</span>
                    </div>
                </div>

                <!-- Dashboard (desktop seulement) -->
                <div class="game-dashboard desktop-only">
                    <div class="dashboard-card">
                        <i class="fas fa-question-circle"></i>
                        <h4>Questions</h4>
                        <p id="questions-count">0</p>
                    </div>
                    <div class="dashboard-card">
                        <i class="fas fa-bullseye"></i>
                        <h4>Précision</h4>
                        <p id="accuracy">0%</p>
                    </div>
                    <div class="dashboard-card">
                        <i class="fas fa-trophy"></i>
                        <h4>Meilleure série</h4>
                        <p id="best-streak">0</p>
                    </div>
                </div>
            </div>

            <!-- Défi quotidien -->
            <div class="daily-challenge">
                <h3>Défi du Jour</h3>
                <p><?php echo htmlspecialchars($dailyChallenge['description']); ?></p>
                <p>Objectif : <?php echo number_format($dailyChallenge['target_score']); ?> points</p>
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
        
        <div class="modal-actions">
            <button class="btn btn-primary" onclick="startNewGame()">Rejouer</button>
            <a href="index.php" class="btn btn-secondary">Retour à l'accueil</a>
        </div>
        
        <div id="earnedBadges" class="earned-badges"></div>
        
        <div class="highscores-section">
            <h3>Meilleurs scores</h3>
            <div class="highscores-table-wrapper">
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
    questionStartTime: null,
    gameEnded: false // Nouveau drapeau pour indiquer que le jeu est terminé
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

        // Réinitialiser l'état du jeu
        gameState = {
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
    if (gameState.timer) {
        clearInterval(gameState.timer);
    }
    gameState.timer = setInterval(() => {
        gameState.timeLeft--;
        updateTimer();
        if (gameState.timeLeft <= 10 && !gameState.timerExpired) {
            elements.timer.classList.add('warning');
            SOUNDS.countdown.play();
        }
        if (gameState.timeLeft <= 0 && !gameState.gameEnded) {
            gameState.timerExpired = true;
            endGame();
        }
    }, 1000);
}

let isEnding = false;

function updateTimer() {
    const minutes = Math.floor(gameState.timeLeft / 60);
    const seconds = gameState.timeLeft % 60;
    elements.timer.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

// Génération des questions
async function generateNewQuestion() {
    gameState.questionStartTime = Date.now();
    if (gameState.currentQuestion) {
        elements.number.textContent = gameState.currentQuestion.number;
        elements.multiplier.textContent = gameState.currentQuestion.multiplier;
    } else {
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
    writeLog(`Entering handleAnswerResponse() with response: ${JSON.stringify(response)}, correctAnswer: ${correctAnswer}`);
    if (!response || response.error) {
        writeLog('Response is invalid or has an error');
        if (response?.error === 'time_exceeded') {
            if (!gameState.gameEnded) {
                writeLog('Time has been exceeded, calling endGame()');
                gameState.gameEnded = true;
                endGame();
            } else {
                writeLog('Game has already ended, not calling endGame() again');
            }
            return;
        }
        writeLog(`Invalid response or error: ${JSON.stringify(response)}`);
        return;
    }

    writeLog(`Question answered, questions answered: ${gameState.questionsAnswered + 1}`);
    gameState.questionsAnswered++;

    writeLog(`isCorrect: ${response.isCorrect}`);

    if (response.isCorrect) {
        writeLog('Answer is correct');
        gameState.correctAnswers++;
        gameState.streak++;
        gameState.score += response.points;
        showPointsAnimation(response.points);
        SOUNDS.correct.play();
    } else {
        writeLog('Answer is incorrect');
        gameState.streak = 0;
        SOUNDS.wrong.play();
        showWrongAnswerAnimation(correctAnswer);
    }

    writeLog(`Current score: ${gameState.score}, streak: ${gameState.streak}, best streak: ${gameState.bestStreak}`);
    updateUI();

    // Utiliser la vérification directe
    if (gameState.timeLeft > 0) {
        writeLog(`Time left: ${gameState.timeLeft} seconds`);
        if (response.nextQuestion) {
            writeLog('Received next question, updating state');
            gameState.currentQuestion = response.nextQuestion;
            elements.number.textContent = response.nextQuestion.number;
            elements.multiplier.textContent = response.nextQuestion.multiplier;
            elements.answer.value = '';
            elements.answer.focus();
            gameState.questionStartTime = Date.now();
        } else {
            writeLog('Generating new question');
            generateNewQuestion();
        }
    }

    writeLog('Exiting handleAnswerResponse()');
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

// Fonction pour enregistrer les logs
function writeLog(message) {
    console.log(message);
    // Écrire les logs dans un fichier sur le serveur
    const logFile = 'logout.log';
    const logMessage = `[${new Date().toISOString()}] ${message}`;
    fetch('write_log.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ message: logMessage })
    })
    .catch(error => {
        console.error('Error writing log:', error);
    });
}

// Fin de partie
// Fin de partie
// Fin de partie
// Fin de partie

async function endGame() {
    // Vérifier si le jeu est déjà en cours de terminaison
    if (isEnding || gameState.gameEnded) {
        return;
    }
    
    try {
        isEnding = true;
        
        // Arrêter immédiatement le timer
        if (gameState.timer) {
            clearInterval(gameState.timer);
            gameState.timer = null;
        }
        
        // Marquer le jeu comme terminé
        gameState.gameEnded = true;
        
        // Désactiver l'input
        elements.answer.disabled = true;
        elements.answer.removeEventListener('keyup', handleAnswer);
        
        writeLog('Submitting final score');
        const response = await submitFinalScore();
        
        if (!response) {
            throw new Error('No response from submitFinalScore()');
        }

        if (response.error) {
            throw new Error(response.message || 'Error submitting final score');
        }

        // Afficher les résultats
        showGameOverModal(response);

        // Jouer le son approprié
        if (response.dailyChallenge?.completed && !gameState.dailyChallengeCompleted) {
            new Audio('sounds/success.mp3').play();
            gameState.dailyChallengeCompleted = true;
        } else if (gameState.timerExpired && !gameState.gameOverSoundPlayed) {
            SOUNDS.gameOver.play();
            gameState.gameOverSoundPlayed = true;
        }

    } catch (error) {
        console.error('Error in endGame():', error);
        writeLog(`Error in endGame(): ${error.message}`);
        
        // Afficher quand même le modal avec les données locales
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
        
        alert('Une erreur est survenue lors de la sauvegarde des résultats. Vos scores seront affichés localement.');
    } finally {
        isEnding = false;
    }
}




async function submitFinalScore() {
    try {
        writeLog('Entering submitFinalScore()');
        writeLog('Submitting final score to server');

        const formData = new FormData();
        formData.append('action', 'end_game');

        writeLog('Sending POST request to challenge.php');
        const response = await fetch('challenge.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });

        if (!response.ok) {
            writeLog(`HTTP error submitting final score: ${response.status}`);
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        writeLog('Final score submitted successfully');
        writeLog('Parsing response JSON');
        const responseData = await response.json();
        writeLog(`Returning response data: ${JSON.stringify(responseData)}`);
        writeLog('Exiting submitFinalScore()');
        return responseData;
    } catch (error) {
        writeLog(`Error submitting final score: ${error.message}`);
        console.error('Error submitting final score:', error);
        writeLog('Exiting submitFinalScore() with error');
        return null;
    }
}

function showGameOverModal(data) {
        // Assurez-vous que le modal est visible et bien positionné
        elements.gameOverModal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    document.body.classList.add('modal-open'); // Ajouter une classe pour gérer l'état
    const starsDisplay = document.getElementById('starsEarned');
    starsDisplay.innerHTML = '⭐'.repeat(data.stars);
    
    document.getElementById('finalScore').textContent = data.finalScore;
    document.getElementById('finalQuestions').textContent = data.questionsAnswered;
    document.getElementById('finalAccuracy').textContent = 
        `${Math.round((data.correctAnswers / data.questionsAnswered) * 100)}%`;

        // Nettoyer d'abord les anciens résultats
        const badgesContainer = document.getElementById('earnedBadges');
    const existingResults = document.querySelectorAll('.daily-challenge-results');
    existingResults.forEach(element => element.remove());

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

      // Pour le tableau des meilleurs scores, ajout de style et formatage
      const highscoresList = document.getElementById('highscoresList');
    const highScoresLimited = data.highScores.slice(0, 10); // Limiter à 10 scores
    highscoresList.innerHTML = highScoresLimited.map((score, index) => `
        <tr class="${index === 0 ? 'table-warning' : ''}">
            <td class="text-center">${index + 1}</td>
            <td>${score.user_id}</td>
            <td class="text-end">${score.score.toLocaleString()}</td>
            <td class="text-center">${new Date(score.date_played).toLocaleDateString()}</td>
        </tr>
    `).join('');

    // Afficher le modal

    
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
    document.body.style.overflow = 'auto';
    document.body.classList.remove('modal-open');
    elements.gameOverModal.style.display = 'none';
    try {
        // Réinitialiser tous les états
        if (gameState.timer) {
            clearInterval(gameState.timer);
            gameState.timer = null;
        }
        
        gameState = {
            timeLeft: GAME_DURATION,
            score: 0,
            streak: 0,
            bestStreak: 0,
            questionsAnswered: 0,
            correctAnswers: 0,
            currentQuestion: null,
            timer: null,
            questionStartTime: null,
            gameEnded: false,
            timerExpired: false,
            gameOverSoundPlayed: false,
            dailyChallengeCompleted: false
        };
        
        // Réinitialiser l'interface
        elements.gameOverModal.style.display = 'none';
        elements.answer.disabled = false;
        elements.answer.value = '';
        
        // Réinitialiser les écouteurs d'événements
        elements.answer.removeEventListener('keyup', handleAnswer);
        elements.answer.addEventListener('keyup', handleAnswer);
        
        // Mettre à jour l'interface et démarrer une nouvelle partie
        updateUI();
        await initGame();
        
    } catch (error) {
        console.error('Erreur lors du redémarrage:', error);
        alert('Erreur lors du redémarrage du jeu. Veuillez rafraîchir la page.');
    }
}


// Gestionnaire du pavé numérique
// Gestionnaire du pavé numérique
// Gestionnaire du pavé numérique
const initNumpad = () => {
    const numpad = document.getElementById('numpad');
    const answer = document.getElementById('answer');
    const gameContainer = document.querySelector('.game-container');
    
    if (!numpad || !answer) {
        console.error('Elements numpad or answer not found');
        return;
    }

    let touchStartTime = 0;
    let isTouchActive = false;
    const tapThreshold = 300; // ms pour distinguer un tap d'un scroll

    // Fonction pour gérer l'entrée numérique
    const handleNumericInput = (value) => {
        const currentValue = answer.value;
        if (currentValue.length < 3) {
            answer.value = currentValue + value;
        }
    };

    // Fonction pour gérer la correction
    const handleCorrection = () => {
        answer.value = answer.value.slice(0, -1);
    };

    // Fonction pour gérer la validation
    const handleValidation = () => {
        if (answer.value.trim() === '') return;
        
        const event = new KeyboardEvent('keyup', {
            key: 'Enter',
            code: 'Enter',
            keyCode: 13,
            which: 13,
            bubbles: true,
            cancelable: true
        });
        answer.dispatchEvent(event);
    };

    // Gestionnaire du feedback visuel
    const addButtonFeedback = (button) => {
        button.classList.add('active');
        requestAnimationFrame(() => {
            button.classList.add('button-press');
        });
    };

    const removeButtonFeedback = (button) => {
        button.classList.remove('active', 'button-press');
    };

    // Gestionnaire de début de toucher
    const handleTouchStart = (e) => {
        const target = e.target.closest('button');
        if (!target) return;

        e.preventDefault();
        touchStartTime = Date.now();
        isTouchActive = true;

        addButtonFeedback(target);
    };

    // Gestionnaire de fin de toucher
    const handleTouchEnd = (e) => {
        const target = e.target.closest('button');
        if (!target || !isTouchActive) return;

        e.preventDefault();
        isTouchActive = false;

        const touchDuration = Date.now() - touchStartTime;
        removeButtonFeedback(target);

        // Ne traiter que les taps courts
        if (touchDuration < tapThreshold) {
            if (window.gameState?.gameEnded) return;

            // Traiter l'action du bouton avec un petit délai pour l'animation
            setTimeout(() => {
                if (target.classList.contains('num-btn')) {
                    handleNumericInput(target.dataset.value);
                } else if (target.id === 'correct' || target.classList.contains('correct-btn')) {
                    handleCorrection();
                } else if (target.id === 'validate' || target.classList.contains('validate-btn')) {
                    handleValidation();
                }
            }, 50);
        }
    };

    // Gestionnaire de mouvement
    const handleTouchMove = (e) => {
        if (!isTouchActive) return;
        
        const target = e.target.closest('button');
        if (target) {
            removeButtonFeedback(target);
        }
    };

    // Gestionnaire de clic (pour desktop)
    const handleClick = (e) => {
        const target = e.target.closest('button');
        if (!target) return;

        e.preventDefault();
        if (window.gameState?.gameEnded) return;

        addButtonFeedback(target);
        
        // Traiter l'action du bouton
        setTimeout(() => {
            if (target.classList.contains('num-btn')) {
                handleNumericInput(target.dataset.value);
            } else if (target.id === 'correct' || target.classList.contains('correct-btn')) {
                handleCorrection();
            } else if (target.id === 'validate' || target.classList.contains('validate-btn')) {
                handleValidation();
            }
            removeButtonFeedback(target);
        }, 50);
    };

    // Empêcher le scroll sur le container en mode jeu
    const preventScroll = (e) => {
        if (gameContainer.contains(e.target)) {
            e.preventDefault();
        }
    };

    // Nettoyer les listeners existants
    const clearExistingListeners = () => {
        const clone = numpad.cloneNode(true);
        numpad.parentNode.replaceChild(clone, numpad);
        return clone;
    };

    // Initialiser le nouveau pavé
    const newNumpad = clearExistingListeners();

    // Ajouter les écouteurs d'événements
    newNumpad.addEventListener('touchstart', handleTouchStart, { passive: false });
    newNumpad.addEventListener('touchend', handleTouchEnd, { passive: false });
    newNumpad.addEventListener('touchmove', handleTouchMove, { passive: true });
    newNumpad.addEventListener('click', handleClick);

    // Empêcher le scroll pendant le jeu sur mobile
    if (window.innerWidth <= 768) {
        gameContainer.addEventListener('touchmove', preventScroll, { passive: false });
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
    }

    // Gérer la perte de focus
    const handleVisibilityChange = () => {
        if (document.visibilityState === 'visible') {
            newNumpad.querySelectorAll('button').forEach(button => {
                removeButtonFeedback(button);
            });
        }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);

    // Retourner la fonction de nettoyage
    return () => {
        newNumpad.removeEventListener('touchstart', handleTouchStart);
        newNumpad.removeEventListener('touchend', handleTouchEnd);
        newNumpad.removeEventListener('touchmove', handleTouchMove);
        newNumpad.removeEventListener('click', handleClick);
        document.removeEventListener('visibilitychange', handleVisibilityChange);
        if (window.innerWidth <= 768) {
            gameContainer.removeEventListener('touchmove', preventScroll);
            document.body.style.position = '';
            document.body.style.width = '';
        }
    };
};

// Modifier la fonction handleDeviceInput pour être plus stricte sur mobile
const handleDeviceInput = () => {
    const answer = document.getElementById('answer');
    const isMobile = window.innerWidth <= 768;
    
    if (answer) {
        if (isMobile) {
            answer.removeEventListener('keyup', handleAnswer);
            answer.readOnly = true;
            
            // Désactiver complètement l'input sur mobile
            answer.addEventListener('touchstart', (e) => e.preventDefault(), { passive: false });
            answer.addEventListener('focus', (e) => {
                e.preventDefault();
                answer.blur();
            });
        } else {
            answer.removeEventListener('touchstart', (e) => e.preventDefault());
            answer.removeEventListener('focus', (e) => e.preventDefault());
            answer.addEventListener('keyup', handleAnswer);
            answer.readOnly = false;
        }
    }
};

// Initialisation au chargement du DOM
document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('intro-visible');
    let cleanup;
    
    const startButton = document.getElementById('start-game');
    if (startButton) {
        startButton.addEventListener('click', () => {
            const introScreen = document.getElementById('intro-screen');
            if (introScreen) {
                introScreen.style.display = 'none';
            }
            
            document.body.classList.remove('intro-visible');
            
            document.querySelectorAll('.game-content').forEach(el => {
                el.style.display = 'block';
            });
            
            handleDeviceInput();
            cleanup = initNumpad();
            initGame();
        });
    }

    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            handleDeviceInput();
        }, 250);
    });

    window.addEventListener('gameRestart', () => {
        if (typeof cleanup === 'function') {
            cleanup();
        }
        cleanup = initNumpad();
        handleDeviceInput();
    });
});


</script>

</div> <!-- Fermeture du container principal -->
</div> <!-- Fermeture du container principal -->
</div> <!-- Fermeture du container principal -->

<?php include 'footer.php'; ?>