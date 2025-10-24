<?php
// auth.php — ?action=register|login (solo bloque register mostrado)
session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$action = $_GET['action'] ?? '';

if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  // Leer parámetros
  $nombre   = trim($_POST['nombre']   ?? '');
  $apellido = trim($_POST['apellido'] ?? '');
  $email    = trim($_POST['email']    ?? '');
  $dni      = trim($_POST['dni']      ?? '');
  $pass     = (string)($_POST['password'] ?? '');
  $obra     = trim($_POST['obra_social'] ?? '');
  $libreta  = trim($_POST['libreta_sanitaria'] ?? '');

  // Back-compat: si vino un “nombre” combinado (sin apellido), partir en dos
  if ($apellido === '' && strpos($nombre, ' ') !== false) {
    $parts = preg_split('/\s+/', $nombre, 2);
    $nombre = trim($parts[0] ?? $nombre);
    $apellido = trim($parts[1] ?? '');
  }

  // Validación
  if ($nombre==='' || $apellido==='' || $email==='' || $dni==='' || $pass==='' || $obra==='') {
    echo json_encode(['ok'=>false,'error'=>'Faltan campos']); exit;
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok'=>false,'error'=>'Email inválido']); exit;
  }
  if (!preg_match('/^[0-9]{7,10}$/', $dni)) {
    echo json_encode(['ok'=>false,'error'=>'DNI inválido']); exit;
  }

  try {
    // Unicidad
    $st = $pdo->prepare("SELECT 1 FROM usuario WHERE email=? OR dni=? LIMIT 1");
    $st->execute([$email,$dni]);
    if ($st->fetch()) { echo json_encode(['ok'=>false,'error'=>'Ya existe usuario']); exit; }

    $pdo->beginTransaction();

    // Usuario con Nombre y Apellido separados
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO usuario (Nombre, Apellido, dni, email, Contraseña, Rol) VALUES (?,?,?,?,?,?)")
        ->execute([$nombre, $apellido, $dni, $email, $hash, '']);
    $uid = (int)$pdo->lastInsertId();

    // Paciente activo con Obra Social y Libreta
    $pdo->prepare("INSERT INTO paciente (Obra_social, Libreta_sanitaria, Id_usuario, Activo) VALUES (?,?,?,1)")
        ->execute([$obra !== '' ? $obra : null, $libreta !== '' ? $libreta : null, $uid]);

    $pdo->commit();

    // Sesión
    $_SESSION['Id_usuario']=$uid;
    $_SESSION['dni']=$dni;
    $_SESSION['email']=$email;
    $_SESSION['Rol']='';
    $_SESSION['Nombre']=$nombre;
    $_SESSION['Apellido']=$apellido;

    echo json_encode(['ok'=>true,'usuario_id'=>$uid]);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
  }
  exit;
}

// ... (tu bloque de login y otros actions)
echo json_encode(['ok'=>false,'error'=>'Acción no soportada']);
