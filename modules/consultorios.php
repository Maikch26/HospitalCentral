
<?php
require_once "../config/conexion.php";

$mensaje = "";
$tipo_mensaje = "";

$consultorio = [
    "id_consultorio" => "",
    "codigo" => "",
    "nombre_consultorio" => "",
    "ubicacion" => "",
    "id_especialidad" => "",
    "estado" => "Disponible"
];

$editar = false;

// CONSULTAR ESPECIALIDADES PARA EL FORMULARIO
$stmt = $conexion->query(
    "SELECT id_especialidad, nombre_especialidad
     FROM especialidad
     ORDER BY nombre_especialidad"
);
$especialidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// REGISTRAR O ACTUALIZAR CONSULTORIO
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id = $_POST["id_consultorio"] ?? "";
    $codigo = strtoupper(trim($_POST["codigo"] ?? ""));
    $nombre = trim($_POST["nombre_consultorio"] ?? "");
    $ubicacion = trim($_POST["ubicacion"] ?? "");
    $id_especialidad = $_POST["id_especialidad"] ?? "";
    $estado = $_POST["estado"] ?? "Disponible";

    $estados_validos = [
        "Disponible",
        "Mantenimiento",
        "Inactivo"
    ];

    if ($codigo === "" || $nombre === "" || $id_especialidad === "") {
        $mensaje = "Completa el código, nombre y especialidad.";
        $tipo_mensaje = "danger";

    } elseif (!in_array($estado, $estados_validos, true)) {
        $mensaje = "El estado seleccionado no es válido.";
        $tipo_mensaje = "danger";

    } else {
        try {

            if ($id !== "") {

                $sql = "UPDATE consultorio
                        SET codigo = ?,
                            nombre_consultorio = ?,
                            ubicacion = ?,
                            id_especialidad = ?,
                            estado = ?
                        WHERE id_consultorio = ?";

                $stmt = $conexion->prepare($sql);
                $stmt->execute([
                    $codigo,
                    $nombre,
                    $ubicacion,
                    $id_especialidad,
                    $estado,
                    $id
                ]);

                $mensaje = "Consultorio actualizado correctamente.";

            } else {

                $sql = "INSERT INTO consultorio
                        (codigo, nombre_consultorio, ubicacion,
                         id_especialidad, estado)
                        VALUES (?, ?, ?, ?, ?)";

                $stmt = $conexion->prepare($sql);
                $stmt->execute([
                    $codigo,
                    $nombre,
                    $ubicacion,
                    $id_especialidad,
                    $estado
                ]);

                $mensaje = "Consultorio registrado correctamente.";
            }

            $tipo_mensaje = "success";

        } catch (PDOException $e) {

            if ($e->getCode() == "23000") {
                $mensaje = "No se pudo guardar. Verifica que el código no esté repetido y que la especialidad exista.";
            } else {
                $mensaje = "Error al guardar: " . $e->getMessage();
            }

            $tipo_mensaje = "danger";
        }
    }
}

// CONSULTAR CONSULTORIO PARA EDITAR
if (isset($_GET["editar"])) {

    $id = (int) $_GET["editar"];

    $stmt = $conexion->prepare(
        "SELECT * FROM consultorio WHERE id_consultorio = ?"
    );

    $stmt->execute([$id]);

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resultado) {
        $consultorio = $resultado;
        $editar = true;
    }
}

// BUSCADOR Y LISTADO
$busqueda = trim($_GET["buscar"] ?? "");

$sql = "SELECT
            c.id_consultorio,
            c.codigo,
            c.nombre_consultorio,
            c.ubicacion,
            c.estado,
            e.nombre_especialidad,
            COUNT(DISTINCT t.id_turno) AS total_turnos
        FROM consultorio c
        INNER JOIN especialidad e
            ON c.id_especialidad = e.id_especialidad
        LEFT JOIN turno t
            ON c.id_consultorio = t.id_consultorio";

if ($busqueda !== "") {
    $sql .= " WHERE c.codigo LIKE :buscar
              OR c.nombre_consultorio LIKE :buscar
              OR c.ubicacion LIKE :buscar
              OR e.nombre_especialidad LIKE :buscar";
}

$sql .= " GROUP BY
            c.id_consultorio,
            c.codigo,
            c.nombre_consultorio,
            c.ubicacion,
            c.estado,
            e.nombre_especialidad
          ORDER BY c.codigo ASC";

$stmt = $conexion->prepare($sql);

if ($busqueda !== "") {
    $stmt->bindValue(":buscar", "%" . $busqueda . "%");
}

$stmt->execute();
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ESTADISTICAS
$total = $conexion->query(
    "SELECT COUNT(*) FROM consultorio"
)->fetchColumn();

$disponibles = $conexion->query(
    "SELECT COUNT(*) FROM consultorio WHERE estado = 'Disponible'"
)->fetchColumn();

$mantenimiento = $conexion->query(
    "SELECT COUNT(*) FROM consultorio WHERE estado = 'Mantenimiento'"
)->fetchColumn();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Consultorios | Hospital Central de Neiva</title>

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
        <h2>Administración de consultorios</h2>
        <p class="mb-0">
            Hospital Central de Neiva - Gestión de espacios médicos
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
                <h6 class="text-muted">Total de consultorios</h6>
                <h2 class="titulo"><?php echo $total; ?></h2>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card tarjeta p-3">
                <h6 class="text-muted">Disponibles</h6>
                <h2 class="text-success"><?php echo $disponibles; ?></h2>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card tarjeta p-3">
                <h6 class="text-muted">En mantenimiento</h6>
                <h2 class="text-warning"><?php echo $mantenimiento; ?></h2>
            </div>
        </div>

    </div>

    <div class="row g-4">

        <!-- FORMULARIO -->
        <div class="col-lg-4">

            <div class="card tarjeta p-4">

                <h4 class="titulo mb-3">
                    <?php echo $editar ? "Modificar consultorio" : "Registrar consultorio"; ?>
                </h4>

                <form method="POST" action="consultorios.php">

                    <input type="hidden"
                           name="id_consultorio"
                           value="<?php echo htmlspecialchars($consultorio["id_consultorio"]); ?>">

                    <div class="mb-3">
                        <label class="form-label">Código *</label>

                        <input type="text"
                               name="codigo"
                               class="form-control"
                               required
                               maxlength="20"
                               value="<?php echo htmlspecialchars($consultorio["codigo"]); ?>"
                               placeholder="Ej. C-109">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nombre del consultorio *</label>

                        <input type="text"
                               name="nombre_consultorio"
                               class="form-control"
                               required
                               maxlength="100"
                               value="<?php echo htmlspecialchars($consultorio["nombre_consultorio"]); ?>"
                               placeholder="Ej. Consultorio de Cardiología">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Ubicación</label>

                        <input type="text"
                               name="ubicacion"
                               class="form-control"
                               value="<?php echo htmlspecialchars($consultorio["ubicacion"] ?? ""); ?>"
                               placeholder="Ej. Primer piso, bloque A">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Especialidad *</label>

                        <select name="id_especialidad"
                                class="form-select"
                                required>

                            <option value="">Seleccione una especialidad</option>

                            <?php foreach ($especialidades as $esp): ?>

                                <option value="<?php echo $esp["id_especialidad"]; ?>"
                                    <?php echo (string)$consultorio["id_especialidad"] === (string)$esp["id_especialidad"] ? "selected" : ""; ?>>

                                    <?php echo htmlspecialchars($esp["nombre_especialidad"]); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Estado</label>

                        <select name="estado" class="form-select">

                            <option value="Disponible"
                                <?php echo $consultorio["estado"] === "Disponible" ? "selected" : ""; ?>>
                                Disponible
                            </option>

                            <option value="Mantenimiento"
                                <?php echo $consultorio["estado"] === "Mantenimiento" ? "selected" : ""; ?>>
                                Mantenimiento
                            </option>

                            <option value="Inactivo"
                                <?php echo $consultorio["estado"] === "Inactivo" ? "selected" : ""; ?>>
                                Inactivo
                            </option>

                        </select>
                    </div>

                    <button type="submit" class="btn btn-hospital w-100">
                        <?php echo $editar ? "Guardar cambios" : "Registrar consultorio"; ?>
                    </button>

                    <?php if ($editar): ?>
                        <a href="consultorios.php"
                           class="btn btn-outline-secondary w-100 mt-2">
                            Cancelar edición
                        </a>
                    <?php endif; ?>

                </form>

                <p class="text-muted small mt-3 mb-0">
                    * Campos obligatorios.
                </p>

            </div>
        </div>

        <!-- LISTADO -->
        <div class="col-lg-8">

            <div class="card tarjeta p-4">

                <h4 class="titulo mb-3">Consultorios registrados</h4>

                <form method="GET" class="row g-2 mb-3">

                    <div class="col-md-9">
                        <input type="text"
                               name="buscar"
                               class="form-control"
                               placeholder="Buscar por código, nombre, ubicación o especialidad..."
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
                                <th>Código / Nombre</th>
                                <th>Especialidad</th>
                                <th>Ubicación</th>
                                <th>Turnos</th>
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
                                            <?php echo htmlspecialchars($fila["codigo"]); ?>
                                        </strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($fila["nombre_consultorio"]); ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($fila["nombre_especialidad"]); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($fila["ubicacion"] ?? ""); ?>
                                    </td>

                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo $fila["total_turnos"]; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if ($fila["estado"] === "Disponible"): ?>
                                            <span class="badge bg-success">Disponible</span>
                                        <?php elseif ($fila["estado"] === "Mantenimiento"): ?>
                                            <span class="badge bg-warning text-dark">Mantenimiento</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <a href="consultorios.php?editar=<?php echo $fila["id_consultorio"]; ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            Editar
                                        </a>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No se encontraron consultorios.
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
