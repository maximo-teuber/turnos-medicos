<?php
// index.php — calendario + selección de especialidad/médico + mis turnos (lado derecho)
session_start();
require_once __DIR__ . '/db.php';

$logueado = !empty($_SESSION['Id_usuario']);
$nombre = ($logueado ? ($_SESSION['Nombre'] ?? '') : '');
$apellido = ($logueado ? ($_SESSION['Apellido'] ?? '') : '');

function ensureCsrf() {
  if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf_token'];
}
$csrf = ensureCsrf();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Turnos Médicos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
  <link rel="stylesheet" href="index.css">
</head>
<body>
  <header class="hdr">
    <div class="brand">Turnos Médicos</div>
    <div class="actions">
      <?php if ($logueado): ?>
        <span>Hola, <?= htmlspecialchars($nombre . ' ' . $apellido) ?></span>
        <form class="inline" action="logout.php" method="post">
          <button type="submit" class="btn ghost">Cerrar sesión</button>
        </form>
      <?php else: ?>
        <a class="btn" href="login.php">Iniciar sesión</a>
        <a class="btn ghost" href="register.php">Crear cuenta</a>
      <?php endif; ?>
    </div>
  </header>

  <main class="wrap">
    <h1>Bienvenido</h1>
    <p>Gestioná tus turnos de manera simple.</p>

    <?php if ($logueado): ?>
    <div class="layout">
      <!-- Columna izquierda: Reserva -->
      <section class="card">
        <h2>Reservar turno</h2>

        <div class="grid grid-3">
          <div class="field">
            <label for="selEsp">Especialidad</label>
            <select id="selEsp">
              <option value="">Cargando…</option>
            </select>
          </div>
          <div class="field">
            <label for="selMedico">Médico</label>
            <select id="selMedico" disabled>
              <option value="">Elegí especialidad…</option>
            </select>
          </div>
        </div>

        <!-- Calendario literal -->
        <div class="cal-wrap">
          <div class="cal-header">
            <button id="calPrev" class="btn ghost" aria-label="Mes anterior">‹</button>
            <div id="calTitle" class="cal-title">Mes Año</div>
            <button id="calNext" class="btn ghost" aria-label="Mes siguiente">›</button>
          </div>
          <div class="cal-grid cal-week">
            <div>Lun</div><div>Mar</div><div>Mié</div><div>Jue</div><div>Vie</div><div class="muted">Sáb</div><div class="muted">Dom</div>
          </div>
          <div id="calGrid" class="cal-grid cal-days"></div>
          <div id="calHint" class="cal-hint">Días habilitados: lunes a viernes (de hoy en adelante)</div>
        </div>

        <div class="grid">
          <div class="field">
            <label>Horarios disponibles</label>
            <div id="slots" class="slots">Elegí un día disponible…</div>
          </div>
        </div>

        <div class="actions-row">
          <button id="btnReservar" class="btn primary" disabled>Reservar</button>
          <span id="msg" class="msg"></span>
        </div>
      </section>

      <!-- Columna derecha: Mis turnos -->
      <section class="card side">
        <h3>Mis turnos</h3>
        <div class="table-wrap">
          <table id="tblTurnos">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Médico</th>
                <th>Especialidad</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </section>
    </div>
    <?php endif; ?>
  </main>

  <script src="index.js"></script>
</body>
</html>
