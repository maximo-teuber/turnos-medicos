<?php
// logout.php — destruye sesión y vuelve al inicio
session_start();

// Borra variables de sesión
$_SESSION = [];

// Borra cookie de sesión (si aplica)
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params["path"], $params["domain"],
    $params["secure"], $params["httponly"]
  );
}

// Destruye la sesión
session_destroy();

// Redirige al inicio
header('Location: index.php');
exit;
