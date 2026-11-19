
<?php
require_once "../config/conexion.php";

$mensaje = "";
$tipo_mensaje = "";
$editar = false;

// Valores iniciales del formulario
$eps = [
    "id_eps" => "",
    "nombre_eps" => "",
    "nit" => "",
    "telefono" => "",
    "correo" => "",
    "direccion" => "",
    "estado" => "Activa"
];

// PROCESAR REGISTRO O ACTUALIZACION
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id_eps = $_POST["id_eps"] ?? "";
    $nombre = trim($_POST["nombre_eps"] ?? "");
    $nit = trim($_POST["nit"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");
    $correo = trim($_POST["correo"] ?? "");
    $direccion = trim($_POST["direccion"] ?? "");
    $estado = $_POST["estado"] ?? "Activa";

    if ($nombre === "" || $nit === "") {
        $mensaje = "El nombre y el NIT son obligatorios.";
        $tipo_mensaje = "danger";
    } elseif (!in_array($estado, ["Activa", "Inactiva"], true)) {
        $mensaje = "El estado seleccionado no es válido.";
        $tipo_mensaje = "danger";
    } elseif ($correo !== "" && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "El correo electrónico no tiene un formato válido.";
        $tipo_mensaje = "danger";
    } else {
        try {
            if ($id_eps !== "") {
                // ACTUALIZAR EPS
                $sql = "UPDATE eps
                        SET nombre_eps = ?, nit = ?, telefono = ?,
                            correo = ?, direccion = ?, estado = ?
                        WHERE id_eps = ?";

                $stmt = $conexion->prepare($sql);
                $stmt->execute([
                    $nombre, $nit, $telefono, $correo,
                    $direccion, $estado, $id_eps
                ]);

                $mensaje = "EPS actualizada correctamente.";
            } else {
                // REGISTRAR EPS
                $sql = "INSERT INTO eps
                        (nombre_eps, nit, telefono, correo, direccion, estado)
                        VALUES (?, ?, ?, ?, ?, ?)";

                $stmt = $conexion->prepare($sql);
                $stmt->execute([
                    $nombre, $nit, $telefono,
                    $correo, $direccion, $estado
                ]);

                $mensaje = "EPS registrada correctamente.";
            }

            $tipo_mensaje = "success";

        } catch (PDOException $e) {
            if ($e->getCode() == "23000") {
                $mensaje = "No se pudo guardar. Verifica que el NIT no esté registrado.";
            } else {
                $mensaje = "Error al guardar la EPS: " . $e->getMessage();
            }

            $tipo_mensaje = "danger";
        }
    }
}

// CARGAR EPS PARA EDITAR
if (isset($_GET["editar"])) {
    $id = (int) $_GET["editar"];

    $stmt = $conexion->prepare(
        "SELECT * FROM eps WHERE id_eps = ?"
    );
    $stmt->execute([$id]);

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resultado) {
        $eps = $resultado;
        $editar = true;
    }
}

// BUSCADOR
$busqueda = trim($_GET["buscar"] ?? "");

$sql = "SELECT
            e.*,
            COUNT(p.id_paciente) AS total_pacientes
        FROM eps e
        LEFT JOIN paciente p ON e.id_eps = p.id_eps";

if ($busqueda !== "") {
    $sql .= " WHERE e.nombre_eps LIKE :buscar
              OR e.nit LIKE :buscar";
}

$sql .= " GROUP BY e.id_eps
          ORDER BY e.nombre_eps ASC";

$stmt = $conexion->prepare($sql);

if ($busqueda !== "") {
    $stmt->bindValue(":buscar", "%" . $busqueda . "%");
}

$stmt->execute();
$lista_eps = $stmt->fetchAll(PDO::FETCH_ASSOC);

// TOTAL DE EPS
$total_eps = $conexion->query(
    "SELECT COUNT(*) FROM eps"
)->fetchColumn();

// TOTAL DE PACIENTES AFILIADOS
$total_afiliados = $conexion->query(
    "SELECT COUNT(*) FROM paciente"
)->fetchColumn();

// TOTAL DE EPS ACTIVAS
$total_activas = $conexion->query(
    "SELECT COUNT(*) FROM eps WHERE estado = 'Activa'"
)->fetchColumn();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Administración de EPS | Hospital Central</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        body {
            background: #f4f7fb;
            font-family: Arial, sans-serif;
        }

        .encabezado {
            background: #123b59;
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .tarjeta {
            border: none;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.06);
        }

        .titulo {
            color: #123b59;
            font-weight: bold;
        }

        .btn-hospital {
            background: #123b59;
            color: white;
        }

        .btn-hospital:hover {
            background: #0b293e;
            color: white;
        }

        .tabla-contenedor {
            overflow-x: auto;
        }

        .form-label {
            font-weight: 600;
        }
    </style>
</head>

<body>

<div class="container-fluid p-4">

    <div class="encabezado">
        <h2>Administración de EPS</h2>
        <p class="mb-0">
            Hospital Central de Neiva - Gestión de entidades promotoras de salud
        </p>
    </div>

    <div class="mb-3">
        <a href="../index.php" class="btn btn-outline-secondary">
            ← Volver al panel principal
        </a>
    </div>

    <?php if ($mensaje !== ""): ?>
        <div class="alert alert-<?php echo htmlspecialchars($tipo_mensaje); ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($mensaje); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- TARJETAS DE RESUMEN -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card tarjeta p-3">
                <h6 class="text-muted">Total de EPS</h6>
                <h2 class="titulo">
                    <?php echo $total_eps; ?>
                </h2>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card tarjeta p-3">
                <h6 class="text-muted">EPS activas</h6>
                <h2 class="text-success">
                    <?php echo $total_activas; ?>
                </h2>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card tarjeta p-3">
                <h6 class="text-muted">Pacientes afiliados</h6>
                <h2 class="text-primary">
                    <?php echo $total_afiliados; ?>
                </h2>
            </div>
        </div>

    </div>

    <div class="row g-4">

        <!-- FORMULARIO -->
        <div class="col-lg-4">

            <div class="card tarjeta p-4">

                <h4 class="titulo mb-3">
                    <?php echo $editar ? "Modificar EPS" : "Registrar nueva EPS"; ?>
                </h4>

                <form method="POST" action="eps.php">

                    <input type="hidden" name="id_eps"
                           value="<?php echo htmlspecialchars($eps["id_eps"]); ?>">

                    <div class="mb-3">
                        <label class="form-label">Nombre de la EPS *</label>
                        <input type="text" name="nombre_eps"
                               class="form-control" required
                               value="<?php echo htmlspecialchars($eps["nombre_eps"]); ?>"
                               placeholder="Nombre de la entidad">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">NIT *</label>
                        <input type="text" name="nit"
                               class="form-control" required
                               value="<?php echo htmlspecialchars($eps["nit"]); ?>"
                               placeholder="Número de identificación tributaria">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono"
                               class="form-control"
                               value="<?php echo htmlspecialchars($eps["telefono"] ?? ""); ?>"
                               placeholder="Teléfono de contacto">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" name="correo"
                               class="form-control"
                               value="<?php echo htmlspecialchars($eps["correo"] ?? ""); ?>"
                               placeholder="correo@eps.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Dirección</label>
                        <textarea name="direccion" class="form-control"
                                  rows="2"
                                  placeholder="Dirección de la EPS"><?php
                            echo htmlspecialchars($eps["direccion"] ?? "");
                        ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="Activa"
                                <?php echo $eps["estado"] === "Activa" ? "selected" : ""; ?>>
                                Activa
                            </option>

                            <option value="Inactiva"
                                <?php echo $eps["estado"] === "Inactiva" ? "selected" : ""; ?>>
                                Inactiva
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-hospital w-100">
                        <?php echo $editar ? "Guardar cambios" : "Registrar EPS"; ?>
                    </button>

                    <?php if ($editar): ?>
                        <a href="eps.php" class="btn btn-outline-secondary w-100 mt-2">
                            Cancelar edición
                        </a>
                    <?php endif; ?>

                </form>

                <p class="text-muted small mt-3">
                    * Los campos marcados son obligatorios.
                </p>

            </div>
        </div>

        <!-- LISTADO -->
        <div class="col-lg-8">

            <div class="card tarjeta p-4">

                <h4 class="titulo mb-3">EPS registradas</h4>

                <form method="GET" class="row g-2 mb-3">

                    <div class="col-md-9">
                        <input type="text" name="buscar"
                               class="form-control"
                               placeholder="Buscar por nombre o NIT..."
                               value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>

                    <div class="col-md-3 d-grid">
                        <button type="submit" class="btn btn-hospital">
                            Buscar
                        </button>
                    </div>

                </form>

                <div class="tabla-contenedor">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">
                            <tr>
                                <th>EPS</th>
                                <th>NIT</th>
                                <th>Teléfono</th>
                                <th>Pacientes</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if (count($lista_eps) > 0): ?>

                            <?php foreach ($lista_eps as $fila): ?>

                                <tr>
                                    <td>
                                        <strong>
                                            <?php echo htmlspecialchars($fila["nombre_eps"]); ?>
                                        </strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($fila["correo"] ?? ""); ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($fila["nit"]); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($fila["telefono"] ?? ""); ?>
                                    </td>

                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo $fila["total_pacientes"]; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if ($fila["estado"] === "Activa"): ?>
                                            <span class="badge bg-success">Activa</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactiva</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <a href="eps.php?editar=<?php echo $fila["id_eps"]; ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            Editar
                                        </a>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No se encontraron EPS.
                                </td>
                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

                <p class="text-muted small mb-0">
                    Se muestran <?php echo count($lista_eps); ?> resultados.
                </p>

            </div>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
