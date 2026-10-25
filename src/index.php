<?php
/*
 * Ferretería El Martillo — Inventario (Cliente A de WebLaunch Inc.)
 * Stack clásico: PHP 7.4 + Apache + MySQL 5.7
 * Todas las credenciales llegan por variables de entorno (Secret de Kubernetes).
 */

function env($name, $default = null) {
    $v = getenv($name);
    return ($v === false || $v === '') ? $default : $v;
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$cliente   = env('CLIENT_NAME', 'Ferretería El Martillo');
$gitCommit = env('GIT_COMMIT', 'local');
$dbHost    = env('DB_HOST', 'localhost');
$dbPort    = env('DB_PORT', '3306');
$dbName    = env('DB_NAME');
$dbUser    = env('DB_USER');
$dbPass    = env('DB_PASSWORD');

$pdo = null;
$dbError = null;
try {
    $pdo = new PDO(
        "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5)
    );
    $pdo->exec("CREATE TABLE IF NOT EXISTS productos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(20) NOT NULL UNIQUE,
        nombre VARCHAR(120) NOT NULL,
        categoria VARCHAR(60) NOT NULL,
        precio DECIMAL(10,2) NOT NULL,
        stock INT NOT NULL DEFAULT 0,
        actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $ex) {
    $dbError = $ex->getMessage();
}

$msg = null;
if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';
    try {
        if ($accion === 'crear') {
            $st = $pdo->prepare("INSERT INTO productos (codigo, nombre, categoria, precio, stock) VALUES (?,?,?,?,?)");
            $st->execute(array($_POST['codigo'], $_POST['nombre'], $_POST['categoria'], $_POST['precio'], $_POST['stock']));
            $msg = array('success', 'Producto creado.');
        } elseif ($accion === 'actualizar') {
            $st = $pdo->prepare("UPDATE productos SET codigo=?, nombre=?, categoria=?, precio=?, stock=? WHERE id=?");
            $st->execute(array($_POST['codigo'], $_POST['nombre'], $_POST['categoria'], $_POST['precio'], $_POST['stock'], $_POST['id']));
            $msg = array('info', 'Producto actualizado.');
        } elseif ($accion === 'eliminar') {
            $st = $pdo->prepare("DELETE FROM productos WHERE id=?");
            $st->execute(array($_POST['id']));
            $msg = array('warning', 'Producto eliminado.');
        }
    } catch (Exception $ex) {
        $msg = array('danger', 'Error: ' . $ex->getMessage());
    }
}

$productos = array();
$editar = null;
$mysqlVersion = 'no disponible';
if ($pdo) {
    $mysqlVersion = $pdo->query("SELECT VERSION()")->fetchColumn();
    $productos = $pdo->query("SELECT * FROM productos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    if (isset($_GET['editar'])) {
        $st = $pdo->prepare("SELECT * FROM productos WHERE id=?");
        $st->execute(array($_GET['editar']));
        $editar = $st->fetch(PDO::FETCH_ASSOC);
    }
}
if (!$pdo) { http_response_code(503); }
$f = $editar ? $editar : array('id' => '', 'codigo' => '', 'nombre' => '', 'categoria' => '', 'precio' => '', 'stock' => '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="generator" content="PHP <?= e(PHP_VERSION) ?>">
  <title><?= e($cliente) ?> · Inventario</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css">
  <style>
    body { padding-top: 70px; background: #f4f1ea; }
    .navbar-inverse { background: #8a3b12; border-color: #6e2f0e; }
    .navbar-inverse .navbar-brand { color: #fff; font-weight: bold; }
    .panel-stack td:first-child { width: 40%; font-weight: bold; }
  </style>
</head>
<body>
<nav class="navbar navbar-inverse navbar-fixed-top">
  <div class="container">
    <span class="navbar-brand"><span class="glyphicon glyphicon-wrench"></span> <?= e($cliente) ?></span>
    <p class="navbar-text navbar-right">Alojado en el PaaS de WebLaunch Inc.</p>
  </div>
</nav>

<div class="container">
  <?php if ($dbError): ?>
    <div class="alert alert-danger"><strong>Base de datos no disponible.</strong> <?= e($dbError) ?></div>
  <?php endif; ?>
  <?php if ($msg): ?>
    <div class="alert alert-<?= e($msg[0]) ?>"><?= e($msg[1]) ?></div>
  <?php endif; ?>

  <div class="row">
    <div class="col-md-8">
      <div class="panel panel-default">
        <div class="panel-heading"><strong>Inventario de productos — v2</strong> <span class="badge"><?= count($productos) ?></span></div>
        <table class="table table-striped table-condensed">
          <thead><tr><th>Código</th><th>Producto</th><th>Categoría</th><th class="text-right">Precio</th><th class="text-right">Stock</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($productos as $p): ?>
            <tr>
              <td><?= e($p['codigo']) ?></td>
              <td><?= e($p['nombre']) ?></td>
              <td><?= e($p['categoria']) ?></td>
              <td class="text-right">$<?= number_format($p['precio'], 2) ?></td>
              <td class="text-right"><?= (int)$p['stock'] ?></td>
              <td class="text-right" style="white-space:nowrap">
                <a class="btn btn-xs btn-default" href="?editar=<?= (int)$p['id'] ?>">Editar</a>
                <form method="post" style="display:inline" class="form-eliminar">
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <button class="btn btn-xs btn-danger">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$productos): ?>
            <tr><td colspan="6" class="text-muted text-center">Sin productos todavía. Agregue el primero con el formulario.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="col-md-4">
      <div class="panel panel-<?= $editar ? 'info' : 'primary' ?>">
        <div class="panel-heading"><strong><?= $editar ? 'Editar producto' : 'Nuevo producto' ?></strong></div>
        <div class="panel-body">
          <form method="post" action="./">
            <input type="hidden" name="accion" value="<?= $editar ? 'actualizar' : 'crear' ?>">
            <input type="hidden" name="id" value="<?= e($f['id']) ?>">
            <div class="form-group"><label>Código</label><input class="form-control" name="codigo" required maxlength="20" value="<?= e($f['codigo']) ?>"></div>
            <div class="form-group"><label>Nombre</label><input class="form-control" name="nombre" required maxlength="120" value="<?= e($f['nombre']) ?>"></div>
            <div class="form-group"><label>Categoría</label>
              <select class="form-control" name="categoria">
                <?php foreach (array('Herramientas', 'Construcción', 'Electricidad', 'Fontanería', 'Pinturas') as $c): ?>
                  <option <?= $f['categoria'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="row">
              <div class="col-xs-6 form-group"><label>Precio ($)</label><input class="form-control" type="number" step="0.01" min="0" name="precio" required value="<?= e($f['precio']) ?>"></div>
              <div class="col-xs-6 form-group"><label>Stock</label><input class="form-control" type="number" min="0" name="stock" required value="<?= e($f['stock']) ?>"></div>
            </div>
            <button class="btn btn-<?= $editar ? 'info' : 'primary' ?> btn-block"><?= $editar ? 'Guardar cambios' : 'Agregar' ?></button>
            <?php if ($editar): ?><a href="./" class="btn btn-default btn-block">Cancelar</a><?php endif; ?>
          </form>
        </div>
      </div>

      <div class="panel panel-default panel-stack">
        <div class="panel-heading"><strong>Stack desplegado</strong></div>
        <table class="table table-condensed">
          <tr><td>Lenguaje</td><td>PHP <?= e(PHP_VERSION) ?></td></tr>
          <tr><td>Servidor web</td><td><?= e(isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Apache') ?></td></tr>
          <tr><td>Base de datos</td><td>MySQL <?= e($mysqlVersion) ?></td></tr>
          <tr><td>Frontend</td><td>Bootstrap 3.4.1 · jQuery <span id="jqv"></span></td></tr>
          <tr><td>Host de BD</td><td><?= e($dbHost) ?></td></tr>
          <tr><td>Pod</td><td><?= e(gethostname()) ?></td></tr>
          <tr><td>Commit</td><td><code><?= e($gitCommit) ?></code></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script>
  $('#jqv').text($.fn.jquery);
  $('.form-eliminar').on('submit', function () { return window.confirm('¿Eliminar este producto?'); });
</script>
</body>
</html>
