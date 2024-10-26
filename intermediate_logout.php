<?php
// intermediate_logout.php

// Supprimer les cookies SSO
setcookie('SSOwAuthHash', '', time() - 3600, '/', 'vindigni.fr');
setcookie('SSOwAuthExpire', '', time() - 3600, '/', 'vindigni.fr');
setcookie('SSOwAuthUser', '', time() - 3600, '/', 'vindigni.fr');
setcookie('PHPSESSID', '', time() - 3600, '/', 'vindigni.fr');

// Afficher un message de déconnexion et rediriger après quelques secondes
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Déconnexion en cours...</title>
    <meta http-equiv="refresh" content="3;url=https://vindigni.fr/index.php">
</head>
<body>
    <p>Vous avez été déconnecté. Vous serez redirigé vers l'accueil dans quelques secondes...</p>
    <script>
        // Supprimer les cookies côté client pour garantir la déconnexion
        document.cookie = "SSOwAuthHash=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=vindigni.fr;";
        document.cookie = "SSOwAuthExpire=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=vindigni.fr;";
        document.cookie = "SSOwAuthUser=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=vindigni.fr;";
        document.cookie = "PHPSESSID=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=vindigni.fr;";
        // Rediriger manuellement si le méta refresh échoue
        setTimeout(function() {
            window.location.href = "https://vindigni.fr/index.php";
        }, 3000);
    </script>
</body>
</html>
