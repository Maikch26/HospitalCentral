
<?php
require_once "../config/conexion.php";

$mensaje = "";
$tipo_mensaje = "";

$dias = [
    "Lunes",
    "Martes",
    "Miercoles",
    "Jueves",
    "Viernes",
    "Sabado",
    "Domingo"
];

$turno = [
    "id_turno" => "",
    "id_medico" => "",
    "id_especialidad" => "",
    "id_consultorio" => "",
    "dia_semana" => "Lunes",
    "hora_inicio" => "08:00",
    "hora_fin" => "12:00",
    "cupos_disponibles" => 10,
    "estado" => "Activo"
];

$editar = false;

// LISTAS PARA LOS SELECT
$medicos = $conexion->query(
    "SELECT id_medico, nombres, apellidos, estado
     FROM medico
     ORDER BY nombres, apellidos"
)->fetchAll(PDO::FETCH_ASSOC);

$especialidades = $conexion->query(
    "SELECT id_especialidad, nombre_especialidad, estado
     FROM especialidad
     ORDER BY nombre_especialidad"
)->fetchAll(PDO::FETCH_ASSOC);

$consultorios = $conexion->query(
    "SELECT id_consultorio, codigo, nombre_consultorio,
            id_especialidad, estado
     FROM consultorio
     ORDER BY codigo"
)->fetchAll(PDO::FETCH_ASSOC);


// REGISTRAR O ACTUALIZAR TURNO
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id = $_POST["id_turno"] ?? "";
    $id_medico = (int)($_POST["id_medico"] ?? 0);
    $id_especialidad = (int)($_POST["id_especialidad"] ?? 0);
    $id_consultorio = (int)($_POST["id_consultorio"] ?? 0);
    $dia = $_POST["dia_semana"] ?? "";
    $hora_inicio = $_POST["hora_inicio"] ?? "";
    $hora_fin = $_POST["hora_fin"] ?? "";
    $cupos = (int)($_POST["cupos_disponibles"] ?? 0);
    $estado = $_POST["estado"] ?? "";

    // Mantener los datos en el formulario si ocurre un error
    $turno = [
        "id_turno" => $id,
        "id_medico" => $id_medico,
        "id_especialidad" => $id_especialidad,
        "id_consultorio" => $id_consultorio,
        "dia_semana" => $dia,
        "hora_inicio" => $hora_inicio,
        "hora_fin" => $hora_fin,
        "cupos_disponibles" => $cupos,
        "estado" => $estado
    ];

    $estados_validos = ["Activo", "Inactivo"];

    if (
        $id_medico <= 0 ||
        $id_especialidad <= 0 ||
        $id_consultorio <= 0 ||
        !in_array($dia, $dias, true) ||
        $hora_inicio === "" ||
        $hora_fin === "" ||
        $cupos <= 0
    ) {
        $mensaje = "Completa todos los campos correctamente. Los cupos deben ser mayores que cero.";
        $tipo_mensaje = "danger";

    } elseif (!in_array($estado, $estados_validos, true)) {
        $mensaje = "El estado seleccionado no es válido.";
        $tipo_mensaje = "danger";

    } elseif (strtotime($hora_inicio) >= strtotime($hora_fin)) {
        $mensaje = "La hora de inicio debe ser anterior a la hora de finalización.";
        $tipo_mensaje = "danger";

    } else {

        try {
            $conexion->beginTransaction();

            // VERIFICAR QUE EL MEDICO TENGA LA ESPECIALIDAD
            $stmt = $conexion->prepare(
                "SELECT COUNT(*)
                 FROM medico_especialidad
                 WHERE id_medico = ?
                   AND id_especialidad = ?"
            );

            $stmt->execute([$id_medico, $id_especialidad]);

            if ((int)$stmt->fetchColumn() === 0) {
                throw new Exception(
                    "El médico seleccionado no tiene registrada esa especialidad."
                );
            }

            // VERIFICAR QUE EL CONSULTORIO PERTENEZCA A LA ESPECIALIDAD
            $stmt = $conexion->prepare(
                "SELECT id_especialidad, estado
                 FROM consultorio
                 WHERE id_consultorio = ?"
            );

            $stmt->execute([$id_consultorio]);
            $datos_consultorio = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$datos_consultorio) {
                throw new Exception("El consultorio seleccionado no existe.");
            }

            if ((int)$datos_consultorio["id_especialidad"] !== $id_especialidad) {
                throw new Exception(
                    "El consultorio seleccionado no corresponde a la especialidad del turno."
                );
            }

            if ($estado === "Activo" && $datos_consultorio["estado"] !== "Disponible") {
                throw new Exception(
                    "No se puede activar un turno en un consultorio que no está disponible."
                );
            }

            // VERIFICAR QUE EL MEDICO ESTE ACTIVO
            $stmt = $conexion->prepare(
                "SELECT estado FROM medico WHERE id_medico = ?"
            );

            $stmt->execute([$id_medico]);
            $estado_medico = $stmt->fetchColumn();

            if (!$estado_medico) {
                throw new Exception("El médico seleccionado no existe.");
            }

            if ($estado === "Activo" && $estado_medico !== "Activo") {
                throw new Exception(
                    "No se puede activar un turno para un médico inactivo."
                );
            }

            // COMPROBAR CRUCES DE HORARIO
            // Se revisan los turnos activos del mismo médico
            // o del mismo consultorio en el mismo día.
            if ($estado === "Activo") {

                $sql = "SELECT id_turno, id_medico, id_consultorio
                        FROM turno
                        WHERE estado = 'Activo'
                          AND dia_semana = ?
                          AND hora_inicio < ?
                          AND hora_fin > ?
                          AND id_medico = ?";

                $parametros = [
                    $dia,
                    $hora_fin,
                    $hora_inicio,
                    $id_medico
                ];

                if ($id !== "") {
                    $sql .= " AND id_turno <> ?";
                    $parametros[] = (int)$id;
                }

                $stmt = $conexion->prepare($sql);
                $stmt->execute($parametros);

                if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                    throw new Exception(
                        "El médico ya tiene otro turno activo en ese horario y día."
                    );
                }

                $sql = "SELECT id_turno
                        FROM turno
                        WHERE estado = 'Activo'
                          AND dia_semana = ?
                          AND hora_inicio < ?
                          AND hora_fin > ?
                          AND id_consultorio = ?";

                $parametros = [
                    $dia,
                    $hora_fin,
                    $hora_inicio,
                    $id_consultorio
                ];

                if ($id !== "") {
                    $sql .= " AND id_turno <> ?";
                    $parametros[] = (int)$id;
                }

                $stmt = $conexion->prepare($sql);
                $stmt->execute($parametros);

                if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                    throw new Exception(
                        "El consultorio ya tiene otro turno activo en ese horario y día."
                    );
                }
            }

            // GUARDAR
            if ($id !== "") {

                $sql = "UPDATE turno
                        SET id_medico = ?,
                            id_especialidad = ?,
                            id_consultorio = ?,
                            dia_semana = ?,
                            hora_inicio = ?,
                            hora_fin = ?,
                            cupos_disponibles = ?,
                            estado = ?
                        WHERE id_turno = ?";

                $stmt = $conexion->prepare($sql);

                $stmt->execute([
                    $id_medico,
                    $id_especialidad,
                    $id_consultorio,
                    $dia,
                    $hora_inicio,
                    $hora_fin,
                    $cupos,
                    $estado,
                    $id
                ]);

                $mensaje = "Turno actualizado correctamente.";

            } else {

                $sql = "INSERT INTO turno
                        (id_medico, id_especialidad, id_consultorio,
                         dia_semana, hora_inicio, hora_fin,
                         cupos_disponibles, estado)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $conexion->prepare($sql);

                $stmt->execute([
                    $id_medico,
                    $id_especialidad,
                    $id_consultorio,
                    $dia,
                    $hora_inicio,
                    $hora_fin,
                    $cupos,
                    $estado
                ]);

                $mensaje = "Turno registrado correctamente.";
            }

            $conexion->commit();

            $tipo_mensaje = "success";

            // Limpiar formulario después de guardar
            $turno = [
                "id_turno" => "",
                "id_medico" => "",
                "id_especialidad" => "",
                "id_consultorio" => "",
                "dia_semana" => "Lunes",
                "hora_inicio" => "08:00",
                "hora_fin" => "12:00",
                "cupos_disponibles" => 10,
                "estado" => "Activo"
            ];

            $editar = false;

        } catch (Exception $e) {

            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            $mensaje = $e->getMessage();
            $tipo_mensaje = "danger";

        } catch (PDOException $e) {

            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            $mensaje = "Error de base de datos: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
}


// CONSULTAR TURNO PARA EDITAR
if (isset($_GET["editar"])) {

    $id = (int)$_GET["editar"];

    $stmt = $conexion->prepare(
        "SELECT * FROM turno WHERE id_turno = ?"
    );

    $stmt->execute([$id]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resultado) {
        $turno = $resultado;
        $editar = true;
    }
}


// BUSCADOR Y LISTADO
$busqueda = trim($_GET["buscar"] ?? "");

$sql = "SELECT
            t.id_turno,
            t.id_medico,
            t.id_especialidad,
            t.id_consultorio,
            t.dia_semana,
            t.hora_inicio,
            t.hora_fin,
            t.cupos_disponibles,
            t.estado,
            m.nombres,
            m.apellidos,
            e.nombre_especialidad,
            c.codigo,
            c.nombre_consultorio,
            COUNT(DISTINCT ci.id_cita) AS total_citas
        FROM turno t
        INNER JOIN medico m
            ON t.id_medico = m.id_medico
        INNER JOIN especialidad e
            ON t.id_especialidad = e.id_especialidad
        INNER JOIN consultorio c
            ON t.id_consultorio = c.id_consultorio
        LEFT JOIN cita ci
            ON t.id_turno = ci.id_turno";

if ($busqueda !== "") {
    $sql .= " WHERE m.nombres LIKE :buscar
              OR m.apellidos LIKE :buscar
              OR e.nombre_especialidad LIKE :buscar
              OR c.codigo LIKE :buscar
              OR t.dia_semana LIKE :buscar";
}

$sql .= " GROUP BY
            t.id_turno,
            t.id_medico,
            t.id_especialidad,
            t.id_consultorio,
            t.dia_semana,
            t.hora_inicio,
            t.hora_fin,
            t.cupos_disponibles,
            t.estado,
            m.nombres,
            m.apellidos,
            e.nombre_especialidad,
            c.codigo,
            c.nombre_consultorio
          ORDER BY
            FIELD(t.dia_semana,
                'Lunes','Martes','Miercoles','Jueves',
                'Viernes','Sabado','Domingo'),
            t.hora_inicio";

$stmt = $conexion->prepare($sql);

if ($busqueda !== "") {
    $stmt->bindValue(":buscar", "%" . $busqueda . "%");
}

$stmt->execute();
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ESTADISTICAS
$total = $conexion->query(
    "SELECT COUNT(*) FROM turno"
)->fetchColumn();

$activos = $conexion->query(
    "SELECT COUNT(*) FROM turno WHERE estado = 'Activo'"
)->fetchColumn();

$inactivos = $conexion->query(
    "SELECT COUNT(*) FROM turno WHERE estado = 'Inactivo'"
)->fetchColumn();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Turnos médicos | Hospital Central de Neiva</title>

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
        <h2>Administración de turnos médicos</h2>
        <p class="mb-0">
            Hospital Central de Neiva - Programación de horarios de atención
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
                <h6 class="text-muted">Total de turnos</h6>
                <h2 class="titulo"><?php echo $total; ?></h2>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card tarjeta p-3">
                <h6 class="text-muted">Turnos activos</h6>
                <h2 class="text-success"><?php echo $activos; ?></h2>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card tarjeta p-3">
                <h6 class="text-muted">Turnos inactivos</h6>
                <h2 class="text-secondary"><?php echo $inactivos; ?></h2>
            </div>
        </div>

    </div>

    <div class="row g-4">

        <!-- FORMULARIO -->
        <div class="col-lg-4">

            <div class="card tarjeta p-4">

                <h4 class="titulo mb-3">
                    <?php echo $editar ? "Modificar turno" : "Registrar turno"; ?>
                </h4>

                <form method="POST" action="turnos.php">

                    <input type="hidden"
                           name="id_turno"
                           value="<?php echo htmlspecialchars($turno["id_turno"]); ?>">

                    <div class="mb-3">
                        <label class="form-label">Médico *</label>

                        <select name="id_medico" class="form-select" required>
                            <option value="">Seleccione un médico</option>

                            <?php foreach ($medicos as $medico): ?>
                                <option value="<?php echo $medico["id_medico"]; ?>"
                                    <?php echo (string)$turno["id_medico"] === (string)$medico["id_medico"] ? "selected" : ""; ?>>
                                    <?php
                                    echo htmlspecialchars(
                                        $medico["nombres"] . " " . $medico["apellidos"]
                                        . ($medico["estado"] !== "Activo" ? " (Inactivo)" : "")
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Especialidad *</label>

                        <select name="id_especialidad" class="form-select" required>
                            <option value="">Seleccione una especialidad</option>

                            <?php foreach ($especialidades as $esp): ?>
                                <option value="<?php echo $esp["id_especialidad"]; ?>"
                                    <?php echo (string)$turno["id_especialidad"] === (string)$esp["id_especialidad"] ? "selected" : ""; ?>>
                                    <?php
                                    echo htmlspecialchars(
                                        $esp["nombre_especialidad"]
                                        . ($esp["estado"] !== "Activa" ? " (Inactiva)" : "")
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Consultorio *</label>

                        <select name="id_consultorio" class="form-select" required>
                            <option value="">Seleccione un consultorio</option>

                            <?php foreach ($consultorios as $consultorio_op): ?>
                                <option value="<?php echo $consultorio_op["id_consultorio"]; ?>"
                                    <?php echo (string)$turno["id_consultorio"] === (string)$consultorio_op["id_consultorio"] ? "selected" : ""; ?>>
                                    <?php
                                    echo htmlspecialchars(
                                        $consultorio_op["codigo"] . " - "
                                        . $consultorio_op["nombre_consultorio"]
                                        . ($consultorio_op["estado"] !== "Disponible" ? " (" . $consultorio_op["estado"] . ")" : "")
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Día de la semana *</label>

                        <select name="dia_semana" class="form-select" required>
                            <?php foreach ($dias as $dia_op): ?>
                                <option value="<?php echo $dia_op; ?>"
                                    <?php echo $turno["dia_semana"] === $dia_op ? "selected" : ""; ?>>
                                    <?php echo $dia_op; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">

                        <div class="col-6 mb-3">
                            <label class="form-label">Hora de inicio *</label>

                            <input type="time"
                                   name="hora_inicio"
                                   class="form-control"
                                   required
                                   value="<?php echo htmlspecialchars(substr($turno["hora_inicio"], 0, 5)); ?>">
                        </div>

                        <div class="col-6 mb-3">
                            <label class="form-label">Hora de finalización *</label>

                            <input type="time"
                                   name="hora_fin"
                                   class="form-control"
                                   required
                                   value="<?php echo htmlspecialchars(substr($turno["hora_fin"], 0, 5)); ?>">
                        </div>

                    </div>

                    <div class="mb-3">
                        <label class="form-label">Cupos disponibles *</label>

                        <input type="number"
                               name="cupos_disponibles"
                               class="form-control"
                               min="1"
                               required
                               value="<?php echo htmlspecialchars($turno["cupos_disponibles"]); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Estado</label>

                        <select name="estado" class="form-select">

                            <option value="Activo"
                                <?php echo $turno["estado"] === "Activo" ? "selected" : ""; ?>>
                                Activo
                            </option>

                            <option value="Inactivo"
                                <?php echo $turno["estado"] === "Inactivo" ? "selected" : ""; ?>>
                                Inactivo
                            </option>

                        </select>
                    </div>

                    <button type="submit" class="btn btn-hospital w-100">
                        <?php echo $editar ? "Guardar cambios" : "Registrar turno"; ?>
                    </button>

                    <?php if ($editar): ?>
                        <a href="turnos.php"
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

                <h4 class="titulo mb-3">Turnos registrados</h4>

                <form method="GET" class="row g-2 mb-3">

                    <div class="col-md-9">
                        <input type="text"
                               name="buscar"
                               class="form-control"
                               placeholder="Buscar médico, especialidad, consultorio o día..."
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
                                <th>Médico / Especialidad</th>
                                <th>Consultorio</th>
                                <th>Horario</th>
                                <th>Cupos</th>
                                <th>Citas</th>
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
                                            <?php echo htmlspecialchars($fila["nombres"] . " " . $fila["apellidos"]); ?>
                                        </strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($fila["nombre_especialidad"]); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($fila["dia_semana"]); ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($fila["codigo"]); ?>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($fila["nombre_consultorio"]); ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            substr($fila["hora_inicio"], 0, 5)
                                            . " - "
                                            . substr($fila["hora_fin"], 0, 5)
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo $fila["cupos_disponibles"]; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge bg-info text-dark">
                                            <?php echo $fila["total_citas"]; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if ($fila["estado"] === "Activo"): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <a href="turnos.php?editar=<?php echo $fila["id_turno"]; ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            Editar
                                        </a>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No se encontraron turnos.
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
