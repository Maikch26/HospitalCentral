
<?php
require_once "../config/conexion.php";

$mensaje = "";
$tipo_mensaje = "";

$especialidad = [
    "id_especialidad" => "",
    "nombre_especialidad" => "",
    "descripcion" => "",
    "estado" => "Activa"
];

$editar = false;

// REGISTRAR O ACTUALIZAR
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id = $_POST["id_especialidad"] ?? "";
    $nombre = trim($_POST["nombre_especialidad"] ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");
    $estado = $_POST["estado"] ?? "Activa";

    if ($nombre === "") {
        $mensaje = "El nombre de la especialidad es obligatorio.";
        $tipo_mensaje = "danger";

    } elseif (!in_array($estado, ["Activa", "Inactiva"], true)) {
        $mensaje = "El estado seleccionado no es válido.";
        $tipo_mensaje = "danger";

    } else {
        try {
            if ($id !== "") {

                $sql = "UPDATE especialidad
                        SET nombre_especialidad = ?,
                            descripcion = ?,
                            estado = ?
                        WHERE id_especialidad = ?";

                $stmt = $conexion->prepare($sql);
                $stmt->execute([
                    $nombre,
                    $descripcion,
                    $estado,
                    $id
                ]);

                $mensaje = "Especialidad actualizada correctamente.";

            } else {

                $sql = "INSERT INTO especialidad
                        (nombre_especialidad, descripcion, estado)
                        VALUES (?, ?, ?)";

                $stmt = $conexion->prepare($sql);
                $stmt->execute([
                    $nombre,
                    $descripcion,
                    $estado
                ]);

                $mensaje = "Especialidad registrada correctamente.";
            }

            $tipo_mensaje = "success";

        } catch (PDOException $e) {

            if ($e->getCode() == "23000") {
                $mensaje = "Esta especialidad ya existe. Verifica el nombre.";
            } else {
                $mensaje = "Error al guardar: " . $e->getMessage();
            }

            $tipo_mensaje = "danger";
        }
    }
}

// CONSULTAR ESPECIALIDAD PARA EDITAR
if (isset($_GET["editar"])) {

    $id = (int) $_GET["editar"];

    $stmt = $conexion->prepare(
        "SELECT * FROM especialidad WHERE id_especialidad = ?"
    );

    $stmt->execute([$id]);

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resultado) {
        $especialidad = $resultado;
        $editar = true;
    }
}

// BUSCADOR
$busqueda = trim($_GET["buscar"] ?? "");

$sql = "SELECT
            e.id_especialidad,
            e.nombre_especialidad,
            e.descripcion,
            e.estado,
            COUNT(DISTINCT me.id_medico) AS total_medicos,
            COUNT(DISTINCT c.id_consultorio) AS total_consultorios
        FROM especialidad e
        LEFT JOIN medico_especialidad me
            ON e.id_especialidad = me.id_especialidad
        LEFT JOIN consultorio c
            ON e.id_especialidad = c.id_especialidad";

if ($busqueda !== "") {
    $sql .= " WHERE e.nombre_especialidad LIKE :buscar";
}

$sql .= " GROUP BY
            e.id_especialidad,
            e.nombre_especialidad,
            e.descripcion,
            e.estado
          ORDER BY e.nombre_especialidad ASC";

$stmt = $conexion->prepare($sql);

if ($busqueda !== "") {
    $stmt->bindValue(":buscar", "%" . $busqueda . "%");
}

$stmt->execute();
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ESTADISTICAS
$total = $conexion->query(
    "SELECT COUNT(*) FROM especialidad"
)->fetchColumn();

$activas = $conexion->query(
    "SELECT COUNT(*) FROM especialidad WHERE estado = 'Activa'"
)->fetchColumn();

$total_medicos = $conexion->query(
    "SELECT COUNT(DISTINCT id_medico) FROM medico_especialidad"
)->fetchColumn();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Especialidades | Hospital Central de Neiva</title>

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
        <h2>Especialidades médicas</h2>
        <p class="mb-0">
            Hospital Central de Neiva - Administración de especialidades
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

    <!-- ESTADISTICAS -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card tarjeta p-3">
                <h6 class="text-muted">Total de especialidades</h6>
                <h2 class="titulo"><?php echo $total; ?></h2>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card tarjeta p-3">
                <h6 class="text-muted">Especialidades activas</h6>
                <h2 class="text-success"><?php echo $activas; ?></h2>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card tarjeta p-3">
                <h6 class="text-muted">Médicos con especialidad</h6>
                <h2 class="text-primary"><?php echo $total_medicos; ?></h2>
            </div>
        </div>

    </div>

    <div class="row g-4">

        <!-- FORMULARIO -->
        <div class="col-lg-4">

            <div class="card tarjeta p-4">

                <h4 class="titulo mb-3">
                    <?php echo $editar ? "Modificar especialidad" : "Registrar especialidad"; ?>
                </h4>

                <form method="POST" action="especialidades.php">

                    <input type="hidden" name="id_especialidad"
                           value="<?php echo htmlspecialchars($especialidad["id_especialidad"]); ?>">

                    <div class="mb-3">
                        <label class="form-label">Nombre de la especialidad *</label>

                        <input type="text"
                               name="nombre_especialidad"
                               class="form-control"
                               required
                               maxlength="100"
                               value="<?php echo htmlspecialchars($especialidad["nombre_especialidad"]); ?>"
                               placeholder="Ej. Cardiología">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descripción</label>

                        <textarea name="descripcion"
                                  class="form-control"
                                  rows="4"
                                  placeholder="Descripción de la especialidad"><?php
                            echo htmlspecialchars($especialidad["descripcion"] ?? "");
                        ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Estado</label>

                        <select name="estado" class="form-select">

                            <option value="Activa"
                                <?php echo $especialidad["estado"] === "Activa" ? "selected" : ""; ?>>
                                Activa
                            </option>

                            <option value="Inactiva"
                                <?php echo $especialidad["estado"] === "Inactiva" ? "selected" : ""; ?>>
                                Inactiva
                            </option>

                        </select>
                    </div>

                    <button type="submit" class="btn btn-hospital w-100">
                        <?php echo $editar ? "Guardar cambios" : "Registrar especialidad"; ?>
                    </button>

                    <?php if ($editar): ?>
                        <a href="especialidades.php"
                           class="btn btn-outline-secondary w-100 mt-2">
                            Cancelar edición
                        </a>
                    <?php endif; ?>

                </form>

                <p class="text-muted small mt-3 mb-0">
                    * Campo obligatorio.
                </p>

            </div>
        </div>

        <!-- LISTADO -->
        <div class="col-lg-8">

            <div class="card tarjeta p-4">

                <h4 class="titulo mb-3">Especialidades registradas</h4>

                <form method="GET" class="row g-2 mb-3">

                    <div class="col-md-9">
                        <input type="text"
                               name="buscar"
                               class="form-control"
                               placeholder="Buscar especialidad..."
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
                                <th>Especialidad</th>
                                <th>Médicos</th>
                                <th>Consultorios</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if (count($lista) > 0): ?>

                            <?php foreach ($lista as $fila): ?>

                                <tr>
                                    <td>
                                        <strong>
                                            <?php echo htmlspecialchars($fila["nombre_especialidad"]); ?>
                                        </strong>

                                        <br>

                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($fila["descripcion"] ?? ""); ?>
                                        </small>
                                    </td>

                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo $fila["total_medicos"]; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge bg-info text-dark">
                                            <?php echo $fila["total_consultorios"]; ?>
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
                                        <a href="especialidades.php?editar=<?php echo $fila["id_especialidad"]; ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            Editar
                                        </a>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No se encontraron especialidades.
                                </td>
                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

                <p class="text-muted small mb-0">
                    Resultados encontrados: <?php echo count($lista); ?>
                </p>

            </div>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
