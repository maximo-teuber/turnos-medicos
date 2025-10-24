<?php
session_start();
require_once __DIR__ . '/db.php';

function dbx(){ return db(); }
function json_out($d,$c=200){ http_response_code($c); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function ensure_csrf(){
  $t = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  if (!$t || !hash_equals($_SESSION['csrf_token'] ?? '', $t)) json_out(['ok'=>false,'error'=>'CSRF inválido'],400);
}
function must_staff(PDO $pdo){
  if (empty($_SESSION['Id_usuario'])) { header('Location: login.php'); exit; }
  $uid = (int)$_SESSION['Id_usuario'];

  $st = $pdo->prepare("SELECT Id_secretaria FROM secretaria WHERE Id_usuario=? LIMIT 1");
  $st->execute([$uid]); $secData = $st->fetch(PDO::FETCH_ASSOC);
  $isSec = (bool)$secData;

  $st = $pdo->prepare("SELECT Id_medico FROM medico WHERE Id_usuario=? LIMIT 1");
  $st->execute([$uid]); $me = $st->fetch(PDO::FETCH_ASSOC);
  $isMed = (bool)$me;

  if (!$isSec && !$isMed) { http_response_code(403); echo "Acceso restringido"; exit; }
  return [$uid,$isSec,$isMed, $me ? (int)$me['Id_medico'] : null, $secData ? (int)$secData['Id_secretaria'] : null];
}
function weekday_name_es($ymd){
  $w = (int)date('N', strtotime($ymd));
  $map = [1=>'lunes',2=>'martes',3=>'miercoles',4=>'jueves',5=>'viernes',6=>'sabado',7=>'domingo'];
  return $map[$w] ?? '';
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
$pdo = dbx();

// ======= API =======
if (isset($_GET['fetch']) || isset($_POST['action'])) {
  [$uid,$isSec,$isMed,$myMedId,$mySecId] = must_staff($pdo);

  // Init
  if (($_GET['fetch'] ?? '') === 'init') {
    $esps = $pdo->query("SELECT Id_Especialidad, Nombre FROM especialidad ORDER BY Nombre")->fetchAll(PDO::FETCH_ASSOC);
    $meds = $pdo->query("
      SELECT m.Id_medico, u.Apellido, u.Nombre, u.dni, u.email, e.Nombre AS Especialidad, m.Legajo, 
             COALESCE(m.Dias_Disponibles,'') AS Dias_Disponibles, 
             COALESCE(m.Hora_Inicio,'08:00:00') AS Hora_Inicio, 
             COALESCE(m.Hora_Fin,'16:00:00') AS Hora_Fin,
             m.Id_Especialidad
      FROM medico m
      JOIN usuario u ON u.Id_usuario=m.Id_usuario
      LEFT JOIN especialidad e ON e.Id_Especialidad=m.Id_Especialidad
      ORDER BY u.Apellido,u.Nombre
    ")->fetchAll(PDO::FETCH_ASSOC);
    $secs = $pdo->query("
      SELECT s.Id_secretaria, u.Apellido, u.Nombre, u.dni, u.email, u.Id_usuario
      FROM secretaria s
      JOIN usuario u ON u.Id_usuario=s.Id_usuario
      ORDER BY u.Apellido,u.Nombre
    ")->fetchAll(PDO::FETCH_ASSOC);
    json_out(['ok'=>true,'especialidades'=>$esps,'medicos'=>$meds,'secretarias'=>$secs,'csrf'=>$csrf]);
  }

  // Crear médico
  if (($_POST['action'] ?? '') === 'create_medico') {
    ensure_csrf();
    try {
      $nombre = trim($_POST['nombre'] ?? '');
      $apellido = trim($_POST['apellido'] ?? '');
      $dni = trim($_POST['dni'] ?? '');
      $email = trim($_POST['email'] ?? '');
      $passwordRaw = $_POST['password'] ?? '';
      if ($passwordRaw === '') throw new Exception('Contraseña vacía');
      $password = password_hash($passwordRaw, PASSWORD_BCRYPT);
      $legajo = trim($_POST['legajo'] ?? '');
      $idEsp = intval($_POST['especialidad'] ?? 0);
      $diasRaw = $_POST['dias'] ?? '';
      $diasArr = array_filter(array_map(function($s){ return trim(strtolower($s)); }, explode(',', $diasRaw)));
      $dias = implode(',', $diasArr);
      $horaInicio = trim($_POST['hora_inicio'] ?? '08:00');
      $horaFin = trim($_POST['hora_fin'] ?? '16:00');
      if (preg_match('/^\d{2}:\d{2}$/',$horaInicio)) $horaInicio .= ':00';
      if (preg_match('/^\d{2}:\d{2}$/',$horaFin)) $horaFin .= ':00';

      if (!$nombre || !$apellido || !$dni || !$email || !$legajo || !$idEsp) throw new Exception('Faltan campos');

      $stmt = $pdo->prepare("INSERT INTO usuario (Nombre, Apellido, dni, email, Contraseña, Rol) VALUES (?,?,?,?,?,'medico')");
      $stmt->execute([$nombre, $apellido, $dni, $email, $password]);
      $idUsuario = $pdo->lastInsertId();

      $stmt = $pdo->prepare("INSERT INTO medico (Legajo, Id_usuario, Id_Especialidad, Dias_Disponibles, Hora_Inicio, Hora_Fin) VALUES (?,?,?,?,?,?)");
      $stmt->execute([$legajo, $idUsuario, $idEsp, $dias, $horaInicio, $horaFin]);

      json_out(['ok'=>true,'msg'=>'Médico creado']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  // Actualizar médico
  if (($_POST['action'] ?? '') === 'update_medico') {
    ensure_csrf();
    try {
      $idMed = intval($_POST['id_medico'] ?? 0);
      if ($idMed <= 0) throw new Exception('ID inválido');

      $legajo = trim($_POST['legajo'] ?? '');
      $idEsp = intval($_POST['especialidad'] ?? 0);
      $diasRaw = $_POST['dias'] ?? '';
      $diasArr = array_filter(array_map(function($s){ return trim(strtolower($s)); }, explode(',', $diasRaw)));
      $dias = implode(',', $diasArr);
      $horaInicio = trim($_POST['hora_inicio'] ?? '08:00');
      $horaFin = trim($_POST['hora_fin'] ?? '16:00');
      if (preg_match('/^\d{2}:\d{2}$/',$horaInicio)) $horaInicio .= ':00';
      if (preg_match('/^\d{2}:\d{2}$/',$horaFin)) $horaFin .= ':00';

      if (!$legajo || !$idEsp) throw new Exception('Faltan campos');

      $stmt = $pdo->prepare("UPDATE medico SET Legajo=?, Id_Especialidad=?, Dias_Disponibles=?, Hora_Inicio=?, Hora_Fin=? WHERE Id_medico=?");
      $stmt->execute([$legajo, $idEsp, $dias, $horaInicio, $horaFin, $idMed]);

      // Actualizar datos usuario
      $nombre = trim($_POST['nombre'] ?? '');
      $apellido = trim($_POST['apellido'] ?? '');
      $email = trim($_POST['email'] ?? '');
      if ($nombre && $apellido && $email) {
        $stmt = $pdo->prepare("UPDATE usuario u JOIN medico m ON m.Id_usuario=u.Id_usuario SET u.Nombre=?, u.Apellido=?, u.email=? WHERE m.Id_medico=?");
        $stmt->execute([$nombre, $apellido, $email, $idMed]);
      }

      json_out(['ok'=>true,'msg'=>'Médico actualizado']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  // Eliminar médico
  if (($_POST['action'] ?? '') === 'delete_medico') {
    ensure_csrf();
    try {
      $idMed = intval($_POST['id_medico'] ?? 0);
      if ($idMed <= 0) throw new Exception('ID inválido');

      // Verificar si tiene turnos
      $check = $pdo->prepare("SELECT COUNT(*) FROM turno WHERE Id_medico=?");
      $check->execute([$idMed]);
      if ($check->fetchColumn() > 0) throw new Exception('No se puede eliminar: tiene turnos asignados');

      // Obtener Id_usuario
      $stmt = $pdo->prepare("SELECT Id_usuario FROM medico WHERE Id_medico=?");
      $stmt->execute([$idMed]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$row) throw new Exception('Médico no encontrado');
      $idUsuario = $row['Id_usuario'];

      // Eliminar médico y usuario
      $pdo->prepare("DELETE FROM medico WHERE Id_medico=?")->execute([$idMed]);
      $pdo->prepare("DELETE FROM usuario WHERE Id_usuario=?")->execute([$idUsuario]);

      json_out(['ok'=>true,'msg'=>'Médico eliminado']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  // Crear secretaria
  if (($_POST['action'] ?? '') === 'create_secretaria') {
    ensure_csrf();
    try {
      $nombre = trim($_POST['nombre'] ?? '');
      $apellido = trim($_POST['apellido'] ?? '');
      $dni = trim($_POST['dni'] ?? '');
      $email = trim($_POST['email'] ?? '');
      $passwordRaw = $_POST['password'] ?? '';
      if ($passwordRaw === '') throw new Exception('Contraseña vacía');
      $password = password_hash($passwordRaw, PASSWORD_BCRYPT);

      if (!$nombre || !$apellido || !$dni || !$email) throw new Exception('Faltan campos');

      $stmt = $pdo->prepare("INSERT INTO usuario (Nombre, Apellido, dni, email, Contraseña, Rol) VALUES (?,?,?,?,?,'secretaria')");
      $stmt->execute([$nombre, $apellido, $dni, $email, $password]);
      $idUsuario = $pdo->lastInsertId();

      $stmt = $pdo->prepare("INSERT INTO secretaria (Id_usuario) VALUES (?)");
      $stmt->execute([$idUsuario]);

      json_out(['ok'=>true,'msg'=>'Secretaria creada']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  // Actualizar secretaria
  if (($_POST['action'] ?? '') === 'update_secretaria') {
    ensure_csrf();
    try {
      $idSec = intval($_POST['id_secretaria'] ?? 0);
      if ($idSec <= 0) throw new Exception('ID inválido');

      $nombre = trim($_POST['nombre'] ?? '');
      $apellido = trim($_POST['apellido'] ?? '');
      $email = trim($_POST['email'] ?? '');

      if (!$nombre || !$apellido || !$email) throw new Exception('Faltan campos');

      $stmt = $pdo->prepare("UPDATE usuario u JOIN secretaria s ON s.Id_usuario=u.Id_usuario SET u.Nombre=?, u.Apellido=?, u.email=? WHERE s.Id_secretaria=?");
      $stmt->execute([$nombre, $apellido, $email, $idSec]);

      json_out(['ok'=>true,'msg'=>'Secretaria actualizada']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  // Eliminar secretaria
  if (($_POST['action'] ?? '') === 'delete_secretaria') {
    ensure_csrf();
    try {
      $idSec = intval($_POST['id_secretaria'] ?? 0);
      if ($idSec <= 0) throw new Exception('ID inválido');

      $stmt = $pdo->prepare("SELECT Id_usuario FROM secretaria WHERE Id_secretaria=?");
      $stmt->execute([$idSec]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$row) throw new Exception('Secretaria no encontrada');
      $idUsuario = $row['Id_usuario'];

      $pdo->prepare("DELETE FROM secretaria WHERE Id_secretaria=?")->execute([$idSec]);
      $pdo->prepare("DELETE FROM usuario WHERE Id_usuario=?")->execute([$idUsuario]);

      json_out(['ok'=>true,'msg'=>'Secretaria eliminada']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  // Doctores por especialidad
  if (($_GET['fetch'] ?? '') === 'doctors') {
    $esp = (int)($_GET['especialidad_id'] ?? 0);
    if ($esp<=0) json_out(['ok'=>false,'error'=>'Especialidad inválida'],400);
    $st = $pdo->prepare("
      SELECT m.Id_medico, u.Apellido, u.Nombre
      FROM medico m JOIN usuario u ON u.Id_usuario=m.Id_usuario
      WHERE m.Id_Especialidad=? ORDER BY u.Apellido,u.Nombre");
    $st->execute([$esp]);
    json_out(['ok'=>true,'items'=>$st->fetchAll(PDO::FETCH_ASSOC),'csrf'=>$csrf]);
  }

  // Agenda (turnos list)
  if (($_GET['fetch'] ?? '') === 'agenda') {
    $med = (int)($_GET['medico_id'] ?? 0);
    $from = trim($_GET['from'] ?? '');
    $to   = trim($_GET['to'] ?? '');
    if ($med<=0) json_out(['ok'=>false,'error'=>'Médico inválido'],400);

    $conds = ["t.Id_medico = ?"];
    $params = [$med];
    if ($from !== '') { $conds[] = "DATE(t.Fecha) >= ?"; $params[] = $from; }
    if ($to   !== '') { $conds[] = "DATE(t.Fecha) <= ?"; $params[] = $to; }

    $sql = "
      SELECT t.Id_turno, t.Fecha, t.Estado,
             up.Nombre AS PNombre, up.Apellido AS PApellido
      FROM turno t
      JOIN paciente p ON p.Id_paciente=t.Id_paciente
      JOIN usuario up ON up.Id_usuario=p.Id_usuario
      WHERE ".implode(' AND ', $conds)."
      ORDER BY t.Fecha ASC
    ";
    $st = $pdo->prepare($sql); $st->execute($params);
    $items=[];
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
      $items[]=[
        'Id_turno'=>(int)$r['Id_turno'],
        'Id_medico'=>$med,
        'fecha'=>$r['Fecha'],
        'fecha_fmt'=>date('d/m/Y H:i', strtotime($r['Fecha'])),
        'estado'=>$r['Estado'] ?? '',
        'paciente'=>trim(($r['PApellido']??'').', '.($r['PNombre']??'')),
      ];
    }
    json_out(['ok'=>true,'items'=>$items,'csrf'=>$csrf]);
  }

  // Slots
  if (($_GET['fetch'] ?? '') === 'slots') {
    $med  = (int)($_GET['medico_id'] ?? 0);
    $date = $_GET['date'] ?? '';
    if ($med<=0) json_out(['ok'=>false,'error'=>'Médico inválido'],400);
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) json_out(['ok'=>false,'error'=>'Fecha inválida'],400);

    $st = $pdo->prepare("SELECT Hora_Inicio, Hora_Fin, COALESCE(Dias_Disponibles,'') AS Dias_Disponibles FROM medico WHERE Id_medico=?");
    $st->execute([$med]); $m = $st->fetch(PDO::FETCH_ASSOC);
    if (!$m) json_out(['ok'=>false,'error'=>'Médico no encontrado'],404);

    $diasPermitidos = array_filter(array_map('trim', explode(',', strtolower($m['Dias_Disponibles']))));
    $diaSemana = weekday_name_es($date);
    $allowed = false;
    if (in_array('lunes-viernes', $diasPermitidos, true)) {
      $w = (int)date('N', strtotime($date));
      $allowed = ($w>=1 && $w<=5);
    } else {
      $allowed = in_array($diaSemana, $diasPermitidos, true);
    }
    if (!$allowed) json_out(['ok'=>true,'slots'=>[],'note'=>'día no disponible']);

    $st = $pdo->prepare("SELECT TIME(Fecha) AS hhmm FROM turno WHERE DATE(Fecha)=? AND Id_medico=? AND (Estado IS NULL OR Estado <> 'cancelado')");
    $st->execute([$date,$med]);
    $busy = array_map(fn($r)=>substr($r['hhmm'],0,5), $st->fetchAll(PDO::FETCH_ASSOC));

    $hi = $m['Hora_Inicio'] ?: '08:00:00';
    $hf = $m['Hora_Fin'] ?: '16:00:00';
    $inicio = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $hi);
    $fin    = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $hf);
    if (!$inicio || !$fin) json_out(['ok'=>false,'error'=>'Error procesando horas'],500);
    $slots = [];
    for($t = clone $inicio; $t < $fin; $t->modify('+30 minutes')) {
      $s = $t->format('H:i');
      if (!in_array($s, $busy, true)) $slots[] = $s;
    }

    json_out(['ok'=>true,'slots'=>$slots,'csrf'=>$csrf]);
  }

  // Crear turno (secretaria/médico)
  if (($_POST['action'] ?? '') === 'create_turno') {
    ensure_csrf();
    try {
      $medId = (int)($_POST['medico_id'] ?? 0);
      $pacId = (int)($_POST['paciente_id'] ?? 0);
      $date = trim($_POST['date'] ?? '');
      $time = trim($_POST['time'] ?? '');

      if ($medId <= 0) throw new Exception('Médico inválido');
      if ($pacId <= 0) throw new Exception('Paciente inválido');
      if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) throw new Exception('Fecha inválida');
      if (!$time || !preg_match('/^\d{2}:\d{2}$/',$time)) throw new Exception('Hora inválida');

      $dt=DateTime::createFromFormat('Y-m-d H:i',"$date $time");
      if(!$dt) throw new Exception('Fecha/hora inválidas');
      
      $fechaHora=$dt->format('Y-m-d H:i:00');
      
      // Verificar disponibilidad
      $chk=$pdo->prepare("SELECT 1 FROM turno WHERE Fecha=? AND Id_medico=? AND (Estado IS NULL OR Estado <> 'cancelado') LIMIT 1");
      $chk->execute([$fechaHora,$medId]);
      if($chk->fetch()) throw new Exception('Horario ocupado');

      // Crear turno
      $stmt = $pdo->prepare("INSERT INTO turno (Fecha, Estado, Id_paciente, Id_medico, Id_secretaria) VALUES (?, 'reservado', ?, ?, ?)");
      $stmt->execute([$fechaHora, $pacId, $medId, $mySecId]);

      json_out(['ok'=>true,'msg'=>'Turno creado']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  // Cancelar turno
  if (($_POST['action'] ?? '') === 'cancel_turno') {
    ensure_csrf();
    $tid=(int)($_POST['turno_id'] ?? 0);
    if ($tid<=0) json_out(['ok'=>false,'error'=>'Turno inválido'],400);
    $pdo->prepare("UPDATE turno SET Estado='cancelado' WHERE Id_turno=?")->execute([$tid]);
    json_out(['ok'=>true,'mensaje'=>'Turno cancelado']);
  }

  // Eliminar turno
  if (($_POST['action'] ?? '') === 'delete_turno') {
    ensure_csrf();
    $tid=(int)($_POST['turno_id'] ?? 0);
    if ($tid<=0) json_out(['ok'=>false,'error'=>'Turno inválido'],400);
    $pdo->prepare("DELETE FROM turno WHERE Id_turno=?")->execute([$tid]);
    json_out(['ok'=>true,'mensaje'=>'Turno eliminado']);
  }

  // Reprogramar turno
  if (($_POST['action'] ?? '') === 'reschedule_turno') {
    ensure_csrf();
    $tid=(int)($_POST['turno_id'] ?? 0);
    $med=(int)($_POST['medico_id'] ?? 0);
    $date=$_POST['date'] ?? ''; $time=$_POST['time'] ?? '';
    if ($tid<=0||$med<=0) json_out(['ok'=>false,'error'=>'Datos inválidos'],400);
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) json_out(['ok'=>false,'error'=>'Fecha inválida'],400);
    if (!$time || !preg_match('/^\d{2}:\d{2}$/',$time)) json_out(['ok'=>false,'error'=>'Hora inválida'],400);

    $dt=DateTime::createFromFormat('Y-m-d H:i',"$date $time");
    if(!$dt) json_out(['ok'=>false,'error'=>'Fecha/hora inválidas'],400);

    $fechaHora=$dt->format('Y-m-d H:i:00');
    $chk=$pdo->prepare("SELECT 1 FROM turno WHERE Fecha=? AND Id_medico=? AND (Estado IS NULL OR Estado <> 'cancelado') AND Id_turno<>? LIMIT 1");
    $chk->execute([$fechaHora,$med,$tid]);
    if($chk->fetch()) json_out(['ok'=>false,'error'=>'Horario ocupado'],409);

    $pdo->prepare("UPDATE turno SET Fecha=?, Id_medico=?, Estado='reservado' WHERE Id_turno=?")->execute([$fechaHora,$med,$tid]);
    json_out(['ok'=>true,'mensaje'=>'Turno reprogramado','fecha'=>$fechaHora]);
  }

  // Buscar pacientes
  if (($_GET['fetch'] ?? '') === 'search_pacientes') {
    $search = trim($_GET['q'] ?? '');
    if (strlen($search) < 2) json_out(['ok'=>true,'items'=>[]]);
    
    $st = $pdo->prepare("
      SELECT p.Id_paciente, u.Nombre, u.Apellido, u.dni, u.email, p.Obra_social
      FROM paciente p
      JOIN usuario u ON u.Id_usuario = p.Id_usuario
      WHERE p.Activo = 1 AND (
        u.dni LIKE ? OR 
        u.Nombre LIKE ? OR 
        u.Apellido LIKE ? OR
        u.email LIKE ?
      )
      ORDER BY u.Apellido, u.Nombre
      LIMIT 20
    ");
    $param = "%$search%";
    $st->execute([$param, $param, $param, $param]);
    json_out(['ok'=>true,'items'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
  }

  json_out(['ok'=>false,'error'=>'Operación no soportada'],400);
}

// ======= HTML =======
[$uid,$isSec,$isMed,$myMedId,$mySecId] = must_staff($pdo);
$nombre = $_SESSION['Nombre'] ?? '';
$apellido = $_SESSION['Apellido'] ?? '';
$rolTexto = $isSec ? 'Secretaría' : 'Médico';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Panel Administrativo - Clínica</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
  <link rel="stylesheet" href="admin.css">
</head>
<body>
<header class="hdr">
  <div class="brand">🏥 Panel Administrativo</div>
  <div class="who">👤 <?= htmlspecialchars($apellido.', '.$nombre) ?> — <?= $rolTexto ?></div>
  <nav class="actions">
    <a class="btn ghost" href="index.php">🏠 Inicio</a>
    <form class="inline" action="logout.php" method="post"><button class="btn ghost" type="submit">🚪 Salir</button></form>
  </nav>
</header>

<main class="wrap">
  <div class="tabs">
    <button class="tab active" data-tab="medicos">👨‍⚕️ Médicos</button>
    <button class="tab" data-tab="secretarias">👩‍💼 Secretarias</button>
    <button class="tab" data-tab="turnos">📅 Gestión de Turnos</button>
  </div>

  <!-- ===== MÉDICOS ===== -->
  <section id="tab-medicos" class="card">
    <h2>Gestión de Médicos</h2>
    
    <h3>➕ Crear Médico</h3>
    <form id="createMedicoForm" class="grid grid-4">
      <div class="field"><label>Nombre *</label><input type="text" name="nombre" required></div>
      <div class="field"><label>Apellido *</label><input type="text" name="apellido" required></div>
      <div class="field"><label>DNI *</label><input type="text" name="dni" required></div>
      <div class="field"><label>Email *</label><input type="email" name="email" required></div>
      <div class="field"><label>Contraseña *</label><input type="password" name="password" required></div>
      <div class="field"><label>Legajo *</label><input type="text" name="legajo" required></div>
      <div class="field"><label>Especialidad *</label><select name="especialidad" id="espCreateSelect" required></select></div>
      <div class="field">
        <label>Días disponibles</label>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <label><input type="checkbox" name="dias_chk" value="lunes"> Lun</label>
          <label><input type="checkbox" name="dias_chk" value="martes"> Mar</label>
          <label><input type="checkbox" name="dias_chk" value="miercoles"> Mié</label>
          <label><input type="checkbox" name="dias_chk" value="jueves"> Jue</label>
          <label><input type="checkbox" name="dias_chk" value="viernes"> Vie</label>
        </div>
      </div>
      <div class="field"><label>Hora inicio</label><input type="time" name="hora_inicio" value="08:00"></div>
      <div class="field"><label>Hora fin</label><input type="time" name="hora_fin" value="16:00"></div>
      <div class="actions-row">
        <button class="btn" type="submit">✅ Crear</button>
        <span id="msgCreateMed" class="msg"></span>
      </div>
    </form>

    <h3 style="margin-top:24px">📋 Lista de Médicos</h3>
    <div class="table-wrap">
      <table class="mini">
        <thead><tr><th>Nombre</th><th>DNI</th><th>Especialidad</th><th>Legajo</th><th>Horario</th><th>Días</th><th>Acciones</th></tr></thead>
        <tbody id="tblMedicos"></tbody>
      </table>
    </div>
  </section>

  <!-- ===== SECRETARIAS ===== -->
  <section id="tab-secretarias" class="card hidden">
    <h2>Gestión de Secretarias</h2>
    
    <h3>➕ Crear Secretaria</h3>
    <form id="createSecretariaForm" class="grid grid-3">
      <div class="field"><label>Nombre *</label><input type="text" name="nombre" required></div>
      <div class="field"><label>Apellido *</label><input type="text" name="apellido" required></div>
      <div class="field"><label>DNI *</label><input type="text" name="dni" required></div>
      <div class="field"><label>Email *</label><input type="email" name="email" required></div>
      <div class="field"><label>Contraseña *</label><input type="password" name="password" required></div>
      <div class="actions-row">
        <button class="btn" type="submit">✅ Crear</button>
        <span id="msgCreateSec" class="msg"></span>
      </div>
    </form>

    <h3 style="margin-top:24px">📋 Lista de Secretarias</h3>
    <div class="table-wrap">
      <table class="mini">
        <thead><tr><th>Nombre</th><th>DNI</th><th>Email</th><th>Acciones</th></tr></thead>
        <tbody id="tblSecretarias"></tbody>
      </table>
    </div>
  </section>

  <!-- ===== TURNOS ===== -->
  <section id="tab-turnos" class="card hidden">
    <h2>📅 Gestión de Turnos</h2>
    
    <div class="grid grid-3" style="margin-bottom:16px">
      <div class="field"><label for="fEsp">Especialidad</label><select id="fEsp"><option value="">Cargando…</option></select></div>
      <div class="field"><label for="fMed">Médico</label><select id="fMed" disabled><option value="">Elegí especialidad…</option></select></div>
      <div class="actions-row">
        <button id="btnNewTurno" class="btn" disabled>➕ Crear Turno</button>
      </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:12px">
      <div class="field"><label for="fFrom">Desde</label><input id="fFrom" type="date"></div>
      <div class="field"><label for="fTo">Hasta</label><input id="fTo" type="date"></div>
    </div>

    <div class="actions-row" style="margin-bottom:16px">
      <button id="btnRefresh" class="btn ghost">🔄 Actualizar</button>
      <button id="btnClearDates" class="btn ghost">❌ Quitar filtro</button>
      <span id="msgTurns" class="msg"></span>
    </div>

    <h3>📋 Turnos del Médico</h3>
    <div class="table-wrap">
      <table id="tblAgenda">
        <thead><tr><th>Fecha</th><th>Paciente</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody></tbody>
      </table>
      <div id="noData" class="msg" style="padding:10px;display:none">Seleccioná un médico para ver sus turnos</div>
    </div>

    <!-- Reprogramar turno -->
    <div id="reprogSection" class="card" style="margin-top:16px;display:none;background:#0f172a">
      <h3>🔄 Reprogramar Turno</h3>
      <div class="grid grid-3">
        <div class="field"><label for="newDate">Nueva fecha</label><input id="newDate" type="date"></div>
        <div class="field"><label for="newTime">Nuevo horario</label><select id="newTime"><option value="">Elegí fecha…</option></select></div>
        <div class="actions-row">
          <button id="btnReprog" class="btn primary" disabled>✅ Confirmar</button>
          <button id="btnCancelReprog" class="btn ghost">❌ Cancelar</button>
        </div>
      </div>
    </div>
  </section>
</main>

<!-- Modal Crear Turno -->
<div id="modalCreateTurno" class="modal" style="display:none">
  <div class="modal-content">
    <h2>➕ Crear Nuevo Turno</h2>
    <form id="formCreateTurno">
      <div class="field">
        <label>Buscar Paciente (DNI, nombre o email)</label>
        <input type="text" id="searchPaciente" placeholder="Escribí al menos 2 caracteres...">
        <div id="pacienteResults" style="margin-top:8px"></div>
      </div>
      <input type="hidden" id="selectedPacienteId">
      <div class="field">
        <label>Paciente seleccionado</label>
        <div id="selectedPacienteInfo" style="padding:8px;background:#0b1220;border-radius:8px;min-height:40px;color:var(--muted)">
          Ninguno
        </div>
      </div>
      <div class="grid grid-2">
        <div class="field"><label>Fecha</label><input type="date" id="turnoDate" required></div>
        <div class="field"><label>Horario</label><select id="turnoTime" required><option value="">Elegí fecha primero...</option></select></div>
      </div>
      <div class="actions-row" style="margin-top:12px">
        <button type="submit" class="btn primary">✅ Crear Turno</button>
        <button type="button" id="btnCloseModal" class="btn ghost">❌ Cancelar</button>
        <span id="msgModal" class="msg"></span>
      </div>
    </form>
  </div>
</div>

<!-- Modal Editar Médico -->
<div id="modalEditMedico" class="modal" style="display:none">
  <div class="modal-content">
    <h2>✏️ Editar Médico</h2>
    <form id="formEditMedico">
      <input type="hidden" id="editMedId">
      <div class="grid grid-3">
        <div class="field"><label>Nombre</label><input type="text" id="editMedNombre" required></div>
        <div class="field"><label>Apellido</label><input type="text" id="editMedApellido" required></div>
        <div class="field"><label>Email</label><input type="email" id="editMedEmail" required></div>
        <div class="field"><label>Legajo</label><input type="text" id="editMedLegajo" required></div>
        <div class="field"><label>Especialidad</label><select id="editMedEsp" required></select></div>
        <div class="field">
          <label>Días disponibles</label>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <label><input type="checkbox" class="editDias" value="lunes"> Lun</label>
            <label><input type="checkbox" class="editDias" value="martes"> Mar</label>
            <label><input type="checkbox" class="editDias" value="miercoles"> Mié</label>
            <label><input type="checkbox" class="editDias" value="jueves"> Jue</label>
            <label><input type="checkbox" class="editDias" value="viernes"> Vie</label>
          </div>
        </div>
        <div class="field"><label>Hora inicio</label><input type="time" id="editMedHoraInicio"></div>
        <div class="field"><label>Hora fin</label><input type="time" id="editMedHoraFin"></div>
      </div>
      <div class="actions-row" style="margin-top:12px">
        <button type="submit" class="btn primary">💾 Guardar</button>
        <button type="button" id="btnCloseMedicoModal" class="btn ghost">❌ Cancelar</button>
        <span id="msgMedicoModal" class="msg"></span>
      </div>
    </form>
  </div>
</div>

<!-- Modal Editar Secretaria -->
<div id="modalEditSecretaria" class="modal" style="display:none">
  <div class="modal-content">
    <h2>✏️ Editar Secretaria</h2>
    <form id="formEditSecretaria">
      <input type="hidden" id="editSecId">
      <div class="grid grid-3">
        <div class="field"><label>Nombre</label><input type="text" id="editSecNombre" required></div>
        <div class="field"><label>Apellido</label><input type="text" id="editSecApellido" required></div>
        <div class="field"><label>Email</label><input type="email" id="editSecEmail" required></div>
      </div>
      <div class="actions-row" style="margin-top:12px">
        <button type="submit" class="btn primary">💾 Guardar</button>
        <button type="button" id="btnCloseSecretariaModal" class="btn ghost">❌ Cancelar</button>
        <span id="msgSecretariaModal" class="msg"></span>
      </div>
    </form>
  </div>
</div>

<script src="admin.js"></script>
</body>
</html>s