<?php
// turnos_api.php — API para index (paciente)
session_start();
require_once __DIR__ . '/db.php';

function json_out($data, $code=200){ http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function require_login(){ if (empty($_SESSION['Id_usuario'])) json_out(['ok'=>false,'error'=>'No autenticado'],401); return (int)$_SESSION['Id_usuario']; }
function ensure_csrf(){ $t = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''; if (!$t || !hash_equals($_SESSION['csrf_token'] ?? '', $t)) json_out(['ok'=>false,'error'=>'CSRF inválido'],400); }
function is_weekday($ymd){ $ts=strtotime($ymd.' 00:00:00'); if($ts===false)return false; $w=(int)date('w',$ts); return $w>=1&&$w<=5; }
function slots_for_day($ymd){ $start=new DateTime("$ymd 08:00:00"); $end=new DateTime("$ymd 12:00:00"); $out=[]; for($t=clone $start;$t<$end;$t->modify('+30 minutes')) $out[]=$t->format('H:i'); return $out; }

$pdo = db();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'specialties') {
  $rows = $pdo->query("SELECT Id_Especialidad, Nombre FROM especialidad ORDER BY Nombre")->fetchAll(PDO::FETCH_ASSOC);
  json_out(['ok'=>true,'items'=>$rows]);
}
if ($action === 'doctors') {
  $esp = (int)($_GET['especialidad_id'] ?? 0);
  if ($esp<=0) json_out(['ok'=>false,'error'=>'Especialidad inválida'],400);
  $st = $pdo->prepare("
    SELECT m.Id_medico, u.Nombre, u.Apellido
    FROM medico m
    JOIN usuario u ON u.Id_usuario = m.Id_usuario
    WHERE m.Id_Especialidad = ?
    ORDER BY u.Apellido, u.Nombre
  ");
  $st->execute([$esp]);
  json_out(['ok'=>true,'items'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
}
if ($action === 'slots') {
  $med = (int)($_GET['medico_id'] ?? 0);
  $date = $_GET['date'] ?? '';

  if ($med <= 0) json_out(['ok' => false, 'error' => 'Médico inválido'], 400);
  if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json_out(['ok' => false, 'error' => 'Fecha inválida'], 400);

  // Obtener los horarios del médico
  $st = $pdo->prepare("SELECT Hora_Inicio, Hora_Fin, Dias_Disponibles FROM medico WHERE Id_medico=?");
  $st->execute([$med]);
  $m = $st->fetch(PDO::FETCH_ASSOC);
  if (!$m) json_out(['ok' => false, 'error' => 'Médico no encontrado'], 404);

  // Verificar si el día está dentro de los días disponibles
  $diasPermitidos = array_map('strtolower', array_map('trim', explode(',', $m['Dias_Disponibles'])));
  $diaSemana = strtolower(strftime('%A', strtotime($date)));
  if (!in_array($diaSemana, $diasPermitidos) && !in_array('lunes-viernes', $diasPermitidos)) {
    json_out(['ok' => true, 'slots' => []]); // día no disponible
  }

  // Obtener horarios ocupados del médico ese día
  $st = $pdo->prepare("SELECT TIME(Fecha) AS hhmm FROM turno WHERE DATE(Fecha)=? AND Id_medico=? AND (Estado IS NULL OR Estado <> 'cancelado')");
  $st->execute([$date, $med]);
  $busy = array_map(fn($r) => substr($r['hhmm'], 0, 5), $st->fetchAll(PDO::FETCH_ASSOC));

  // Generar los intervalos según la disponibilidad del médico
  $inicio = new DateTime($m['Hora_Inicio']);
  $fin = new DateTime($m['Hora_Fin']);
  $slots = [];
  while ($inicio < $fin) {
      $slot = $inicio->format('H:i');
      if (!in_array($slot, $busy)) $slots[] = $slot;
      $inicio->modify('+30 minutes');
  }

  json_out(['ok' => true, 'slots' => $slots]);
}

if ($action === 'my_appointments') {
  $uid = require_login();
  $st = $pdo->prepare("SELECT Id_paciente FROM paciente WHERE Id_usuario=? LIMIT 1");
  $st->execute([$uid]); $pacId = (int)($st->fetchColumn() ?: 0);
  if ($pacId<=0) json_out(['ok'=>true,'items'=>[]]);
  $st = $pdo->prepare("
    SELECT t.Id_turno, t.Fecha, t.Estado, t.Id_medico,
           m.Id_medico AS MedId, um.Nombre AS MNombre, um.Apellido AS MApellido, e.Nombre AS Especialidad
    FROM turno t
    LEFT JOIN medico m       ON m.Id_medico = t.Id_medico
    LEFT JOIN usuario um     ON um.Id_usuario = m.Id_usuario
    LEFT JOIN especialidad e ON e.Id_Especialidad = m.Id_Especialidad
    WHERE t.Id_paciente = ?
    ORDER BY t.Fecha DESC
  ");
  $st->execute([$pacId]);
  $items=[]; foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
    $items[]=[
      'Id_turno'=>(int)$r['Id_turno'],
      'Id_medico'=>(int)($r['Id_medico'] ?? $r['MedId'] ?? 0),
      'fecha'=>$r['Fecha'],
      'fecha_fmt'=>date('d/m/Y H:i', strtotime($r['Fecha'])),
      'estado'=>$r['Estado'] ?? '',
      'medico'=>trim(($r['MApellido'] ?? '').', '.($r['MNombre'] ?? '')),
      'especialidad'=>$r['Especialidad'] ?? '',
    ];
  }
  json_out(['ok'=>true,'items'=>$items]);
}
if ($action === 'book' && $_SERVER['REQUEST_METHOD']==='POST') {
  ensure_csrf();
  $uid = require_login();
  $date = trim($_POST['date'] ?? '');
  $time = trim($_POST['time'] ?? '');
  $med  = (int)($_POST['medico_id'] ?? 0);
  if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) json_out(['ok'=>false,'error'=>'Fecha inválida'],400);
  if (!$time || !preg_match('/^\d{2}:\d{2}$/',$time)) json_out(['ok'=>false,'error'=>'Hora inválida'],400);
  if ($med<=0) json_out(['ok'=>false,'error'=>'Médico inválido'],400);
  if (!is_weekday($date)) json_out(['ok'=>false,'error'=>'Sólo lunes a viernes'],400);

  $chkM = $pdo->prepare("SELECT 1 FROM medico WHERE Id_medico=?");
  $chkM->execute([$med]); if (!$chkM->fetch()) json_out(['ok'=>false,'error'=>'Médico no encontrado'],404);

  $st = $pdo->prepare("SELECT Id_paciente FROM paciente WHERE Id_usuario=? LIMIT 1");
  $st->execute([$uid]); $pacId = (int)($st->fetchColumn() ?: 0);
  if ($pacId<=0) json_out(['ok'=>false,'error'=>'El usuario no está registrado como paciente'],400);

  $dt = DateTime::createFromFormat('Y-m-d H:i', "$date $time");
  if (!$dt) json_out(['ok'=>false,'error'=>'Fecha/hora inválidas'],400);
  $h=(int)$dt->format('H'); $m=(int)$dt->format('i');
  if ($h<8 || ($h==12 && $m>0) || $h>12) json_out(['ok'=>false,'error'=>'Fuera de horario'],400);
  if (!in_array($m,[0,30],true)) json_out(['ok'=>false,'error'=>'Intervalo no válido (30 min)'],400);
  if ($h==11 && $m>30) json_out(['ok'=>false,'error'=>'Último turno 11:30'],400);

  $fechaHora = $dt->format('Y-m-d H:i:00');
  $chk = $pdo->prepare("SELECT 1 FROM turno WHERE Fecha=? AND Id_medico=? AND (Estado IS NULL OR Estado <> 'cancelado') LIMIT 1");
  $chk->execute([$fechaHora, $med]);
  if ($chk->fetch()) json_out(['ok'=>false,'error'=>'Ese horario ya está ocupado para el médico elegido'],409);

  $ins = $pdo->prepare("INSERT INTO turno (Fecha, Estado, Id_paciente, Id_medico) VALUES (?, 'reservado', ?, ?)");
  $ins->execute([$fechaHora, $pacId, $med]);

  json_out(['ok'=>true,'mensaje'=>'Turno reservado']);
}
if ($action === 'cancel' && $_SERVER['REQUEST_METHOD']==='POST') {
  ensure_csrf();
  $uid = require_login();
  $tid = (int)($_POST['turno_id'] ?? 0);
  if ($tid<=0) json_out(['ok'=>false,'error'=>'Turno inválido'],400);
  $st = $pdo->prepare("
    SELECT t.Id_turno
    FROM turno t JOIN paciente p ON p.Id_paciente=t.Id_paciente
    WHERE t.Id_turno=? AND p.Id_usuario=? LIMIT 1");
  $st->execute([$tid,$uid]); if (!$st->fetch()) json_out(['ok'=>false,'error'=>'No autorizado'],403);
  $pdo->prepare("UPDATE turno SET Estado='cancelado' WHERE Id_turno=?")->execute([$tid]);
  json_out(['ok'=>true,'mensaje'=>'Turno cancelado']);
}
if ($action === 'reschedule' && $_SERVER['REQUEST_METHOD']==='POST') {
  ensure_csrf();
  $uid = require_login();
  $tid  = (int)($_POST['turno_id'] ?? 0);
  $date = trim($_POST['date'] ?? '');
  $time = trim($_POST['time'] ?? '');
  $med  = (int)($_POST['medico_id'] ?? 0);
  if ($tid<=0) json_out(['ok'=>false,'error'=>'Turno inválido'],400);
  if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) json_out(['ok'=>false,'error'=>'Fecha inválida'],400);
  if (!$time || !preg_match('/^\d{2}:\d{2}$/',$time)) json_out(['ok'=>false,'error'=>'Hora inválida'],400);
  if ($med<=0) json_out(['ok'=>false,'error'=>'Médico inválido'],400);
  $st = $pdo->prepare("
    SELECT t.Id_medico
    FROM turno t JOIN paciente p ON p.Id_paciente=t.Id_paciente
    WHERE t.Id_turno=? AND p.Id_usuario=? LIMIT 1");
  $st->execute([$tid,$uid]); if (!$st->fetch()) json_out(['ok'=>false,'error'=>'No autorizado'],403);

  $dt = DateTime::createFromFormat('Y-m-d H:i', "$date $time");
  if (!$dt) json_out(['ok'=>false,'error'=>'Fecha/hora inválidas'],400);
  $h=(int)$dt->format('H'); $m=(int)$dt->format('i');
  if ($h<8 || ($h==12 && $m>0) || $h>12) json_out(['ok'=>false,'error'=>'Fuera de horario'],400);
  if (!in_array($m,[0,30],true)) json_out(['ok'=>false,'error'=>'Intervalo no válido (30 min)'],400);
  if ($h==11 && $m>30) json_out(['ok'=>false,'error'=>'Último turno 11:30'],400);

  $fechaHora = $dt->format('Y-m-d H:i:00');
  $chk = $pdo->prepare("SELECT 1 FROM turno WHERE Fecha=? AND Id_medico=? AND (Estado IS NULL OR Estado <> 'cancelado') AND Id_turno<>? LIMIT 1");
  $chk->execute([$fechaHora, $med, $tid]);
  if ($chk->fetch()) json_out(['ok'=>false,'error'=>'Ese horario ya está ocupado para el médico elegido'],409);

  $pdo->prepare("UPDATE turno SET Fecha=?, Id_medico=?, Estado='reservado' WHERE Id_turno=?")->execute([$fechaHora, $med, $tid]);
  json_out(['ok'=>true,'mensaje'=>'Turno reprogramado','fecha'=>$fechaHora]);
}

json_out(['ok'=>false,'error'=>'Acción no soportada'],400);
