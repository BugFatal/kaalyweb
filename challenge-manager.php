<?php
// challenge-manager.php
class DailyChallenge {
    private $pdo;
    
    private const DIFFICULTIES = ['easy', 'medium', 'hard'];
    private const SCORE_RANGES = [
        'easy' => ['min' => 1000, 'max' => 1500],
        'medium' => ['min' => 1500, 'max' => 2000],
        'hard' => ['min' => 2000, 'max' => 3000]
    ];
    private const DEFAULT_TIME_LIMIT = 120;
    private const DEFAULT_POINTS_BASE = 100;
    private const DEFAULT_TIME_BONUS = 50;
    private const DEFAULT_STREAK_BONUS = 50;
    private const DEFAULT_DESCRIPTIONS = [
        "Défi du jour : Montrez votre rapidité en calcul mental !",
        "Défi du jour : Battez le record d'aujourd'hui !",
        "Défi du jour : Obtenez le meilleur score possible en 2 minutes !",
        "Défi du jour : Testez vos compétences en multiplication !"
    ];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getDailyChallenge() {
        $stmt = $this->pdo->prepare("
            SELECT * FROM daily_challenges 
            WHERE challenge_date = CURDATE() 
            AND is_published = 1
        ");
        $stmt->execute();
        $challenge = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$challenge) {
            $challenge = $this->createDailyChallenge();
        }

        return $challenge;
    }

    public function getAllChallenges() {
        $stmt = $this->pdo->prepare("
            SELECT * FROM daily_challenges 
            ORDER BY challenge_date DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createCustomChallenge($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO daily_challenges (
                challenge_date, target_score, difficulty, time_limit, description,
                points_base, time_bonus, streak_bonus, is_published, created_by
            ) VALUES (
                :challenge_date, :target_score, :difficulty, :time_limit, :description,
                :points_base, :time_bonus, :streak_bonus, :is_published, :created_by
            )
        ");
        return $stmt->execute($data);
    }

    public function updateChallenge($id, $data) {
        $sql = "UPDATE daily_challenges SET 
                challenge_date = :challenge_date,
                target_score = :target_score,
                difficulty = :difficulty,
                time_limit = :time_limit,
                description = :description,
                points_base = :points_base,
                time_bonus = :time_bonus,
                streak_bonus = :streak_bonus,
                is_published = :is_published,
                created_by = :created_by
                WHERE id = :id";
                
        $data['id'] = $id;
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }

    public function deleteChallenge($id) {
        $stmt = $this->pdo->prepare("DELETE FROM daily_challenges WHERE id = ?");
        return $stmt->execute([$id]);
    }

    private function createDailyChallenge() {
        $difficulty = self::DIFFICULTIES[array_rand(self::DIFFICULTIES)];
        $targetScore = rand(
            self::SCORE_RANGES[$difficulty]['min'],
            self::SCORE_RANGES[$difficulty]['max']
        );
        $description = self::DEFAULT_DESCRIPTIONS[array_rand(self::DEFAULT_DESCRIPTIONS)];

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO daily_challenges (
                    challenge_date,
                    target_score,
                    difficulty,
                    time_limit,
                    description,
                    points_base,
                    time_bonus,
                    streak_bonus,
                    is_published,
                    created_by
                ) VALUES (
                    CURDATE(),
                    :target_score,
                    :difficulty,
                    :time_limit,
                    :description,
                    :points_base,
                    :time_bonus,
                    :streak_bonus,
                    1,
                    'system'
                )
            ");

            $stmt->execute([
                'target_score' => $targetScore,
                'difficulty' => $difficulty,
                'time_limit' => self::DEFAULT_TIME_LIMIT,
                'description' => $description,
                'points_base' => self::DEFAULT_POINTS_BASE,
                'time_bonus' => self::DEFAULT_TIME_BONUS,
                'streak_bonus' => self::DEFAULT_STREAK_BONUS
            ]);

            $stmt = $this->pdo->prepare("SELECT * FROM daily_challenges WHERE challenge_date = CURDATE()");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            // En cas d'erreur, retourner un défi par défaut
            return [
                'challenge_date' => date('Y-m-d'),
                'target_score' => 1500,
                'difficulty' => 'medium',
                'time_limit' => self::DEFAULT_TIME_LIMIT,
                'description' => "Défi du jour : Obtenez le meilleur score possible en 2 minutes !",
                'points_base' => self::DEFAULT_POINTS_BASE,
                'time_bonus' => self::DEFAULT_TIME_BONUS,
                'streak_bonus' => self::DEFAULT_STREAK_BONUS,
                'is_published' => 1,
                'created_by' => 'system'
            ];
        }
    }

    // Méthode pour récupérer les paramètres de jeu
    public function getGameParameters() {
        $challenge = $this->getDailyChallenge();
        return [
            'GAME_DURATION' => $challenge['time_limit'],
            'POINTS_BASE' => $challenge['points_base'],
            'TIME_BONUS' => $challenge['time_bonus'],
            'STREAK_BONUS' => $challenge['streak_bonus']
        ];
    }
}