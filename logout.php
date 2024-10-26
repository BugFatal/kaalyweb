<?php
// logout.php

// URL de déconnexion du SSO de YunoHost
$ssoLogoutUrl = "https://bug-fatal.fr/yunohost/sso/?action=logout&r=" . urlencode("https://vindigni.fr/index.php");

// Tenter une redirection PHP
header("Location: $ssoLogoutUrl");
exit;
?>

<!-- Solution de redirection JavaScript au cas où le SSO ne renvoie pas correctement -->
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Déconnexion en cours...</title>
    <meta http-equiv="refresh" content="0;url=<?php echo $ssoLogoutUrl; ?>">
    <script>
        // Redirection JavaScript
        window.location.href = "<?php echo $ssoLogoutUrl; ?>";
    </script>
</head>
<body>
    <p>Déconnexion en cours... Si vous n'êtes pas redirigé automatiquement, <a href="<?php echo $ssoLogoutUrl; ?>">cliquez ici</a>.</p>
</body>
</html>
