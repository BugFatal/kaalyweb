<?php
// badges-config.php

// Configuration des badges
$BADGES_CONFIG = [
    // Badges de progression
    'beginner' => [
        'name' => 'Débutant',
        'icon' => 'fas fa-star',
        'color' => '#FFA500',
        'description' => 'Complétez votre première partie',
        'requirement_type' => 'games',
        'requirement_value' => 1
    ],
    'amateur' => [
        'name' => 'Amateur',
        'icon' => 'fas fa-star-half-alt',
        'color' => '#FFB6C1',
        'description' => 'Jouez 10 parties',
        'requirement_type' => 'games',
        'requirement_value' => 10
    ],
    'veteran' => [
        'name' => 'Vétéran',
        'icon' => 'fas fa-medal',
        'color' => '#C0C0C0',
        'description' => 'Jouez 50 parties',
        'requirement_type' => 'games',
        'requirement_value' => 50
    ],

    // Badges de vitesse
    'speed_demon' => [
        'name' => 'Expert en vitesse',
        'icon' => 'fas fa-bolt',
        'color' => '#FFD700',
        'description' => '10 réponses en moins de 30 secondes',
        'requirement_type' => 'speed',
        'requirement_value' => 30
    ],
    'lightning' => [
        'name' => 'Éclair',
        'icon' => 'fas fa-tachometer-alt',
        'color' => '#00FFFF',
        'description' => '20 réponses en moins de 45 secondes',
        'requirement_type' => 'speed',
        'requirement_value' => 45
    ],

    // Badges de précision
    'perfectionist' => [
        'name' => 'Perfectionniste',
        'icon' => 'fas fa-bullseye',
        'color' => '#4169E1',
        'description' => '100% de précision sur 20 questions',
        'requirement_type' => 'accuracy',
        'requirement_value' => 100
    ],
    'sharpshooter' => [
        'name' => 'Tireur d\'élite',
        'icon' => 'fas fa-crosshairs',
        'color' => '#32CD32',
        'description' => '95% de précision sur 50 questions',
        'requirement_type' => 'accuracy',
        'requirement_value' => 95
    ],

    // Badges de séries
    'streak_master' => [
        'name' => 'Maître des séries',
        'icon' => 'fas fa-fire',
        'color' => '#FF4500',
        'description' => 'Série de 15 bonnes réponses',
        'requirement_type' => 'streak',
        'requirement_value' => 15
    ],
    'unstoppable' => [
        'name' => 'Inarrêtable',
        'icon' => 'fas fa-fire-alt',
        'color' => '#FF0000',
        'description' => 'Série de 25 bonnes réponses',
        'requirement_type' => 'streak',
        'requirement_value' => 25
    ],

    // Badges de score
    'bronze_score' => [
        'name' => 'Score Bronze',
        'icon' => 'fas fa-trophy',
        'color' => '#CD7F32',
        'description' => 'Score total > 1000 points',
        'requirement_type' => 'score',
        'requirement_value' => 1000
    ],
    'silver_score' => [
        'name' => 'Score Argent',
        'icon' => 'fas fa-trophy',
        'color' => '#C0C0C0',
        'description' => 'Score total > 2500 points',
        'requirement_type' => 'score',
        'requirement_value' => 2500
    ],
    'gold_score' => [
        'name' => 'Score Or',
        'icon' => 'fas fa-trophy',
        'color' => '#FFD700',
        'description' => 'Score total > 5000 points',
        'requirement_type' => 'score',
        'requirement_value' => 5000
    ],

    // Badges spéciaux
    'daily_champion' => [
        'name' => 'Champion du jour',
        'icon' => 'fas fa-crown',
        'color' => '#FFD700',
        'description' => 'Meilleur score du jour',
        'requirement_type' => 'daily_best',
        'requirement_value' => 1
    ],
    'math_wizard' => [
        'name' => 'Sorcier des maths',
        'icon' => 'fas fa-hat-wizard',
        'color' => '#9400D3',
        'description' => 'Obtenez tous les autres badges',
        'requirement_type' => 'badges_count',
        'requirement_value' => 14
    ],
    'night_owl' => [
        'name' => 'Hibou de nuit',
        'icon' => 'fas fa-moon',
        'color' => '#483D8B',
        'description' => 'Jouez après 22h',
        'requirement_type' => 'time_of_day',
        'requirement_value' => 22
    ]
];

// Fonction pour vérifier si un badge peut être débloqué
function checkBadgeRequirement($badge, $stats) {
    switch ($badge['requirement_type']) {
        case 'games':
            return $stats['total_games'] >= $badge['requirement_value'];
        
        case 'speed':
            return $stats['questions_answered'] >= 10 && 
                   ($stats['total_time'] / $stats['questions_answered']) <= $badge['requirement_value'];
        
        case 'accuracy':
            return $stats['questions_answered'] >= 20 && 
                   ($stats['correct_answers'] / $stats['questions_answered'] * 100) >= $badge['requirement_value'];
        
        case 'streak':
            return $stats['best_streak'] >= $badge['requirement_value'];
        
        case 'score':
            return $stats['total_score'] >= $badge['requirement_value'];
        
        case 'daily_best':
            return $stats['is_daily_best'];
        
        case 'badges_count':
            return $stats['badges_earned'] >= $badge['requirement_value'];
        
        case 'time_of_day':
            $currentHour = (int)date('H');
            return $currentHour >= $badge['requirement_value'] || $currentHour < 4;
        
        default:
            return false;
    }
}

// Fonction pour obtenir les badges débloqués
function getUnlockedBadges($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT badge_id FROM user_badges WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Fonction pour ajouter un nouveau badge
function awardBadge($user_id, $badge_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO user_badges (user_id, badge_id, date_earned) 
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE date_earned = NOW()
    ");
    return $stmt->execute([$user_id, $badge_id]);
}

// Fonction pour vérifier et attribuer de nouveaux badges
function checkAndAwardBadges($user_id, $stats) {
    global $BADGES_CONFIG;
    $unlockedBadges = getUnlockedBadges($user_id);
    $newBadges = [];

    foreach ($BADGES_CONFIG as $badge_id => $badge) {
        if (!in_array($badge_id, $unlockedBadges) && checkBadgeRequirement($badge, $stats)) {
            if (awardBadge($user_id, $badge_id)) {
                $newBadges[] = $badge;
            }
        }
    }

    return $newBadges;
}
?>