
<?php
date_default_timezone_set("America/Bogota");

require_once __DIR__ . "/../config/conexion.php";

function e($valor) {
    return htmlspecialchars((string)($valor ?? ""), ENT_QUOTES, "UTF-8");
}

$mensaje = "";
$tipo_mensaje = "success";
$error = "";

$estados = [
    "Programada",
    "Confirmada",
    "Atendida",
    "Cancelada",
    "No asistio"
];

$dias = [
    1 => "Lunes",
    2 => "Martes",
    3 => "Miercoles",
    4 => "Jueves",
    5 => "Viernes",
    6 => "Sabado",
    7 => "Domingo"
];

/*
|--------------------------------------------------------------------------
| CAMBIAR ESTADO DE UNA CITA
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "estado") {
    try {
        $id = (int)($_POST["id_cita"] ?? 0);
        $nuevo_estado = $_POST["nuevo_estado"] ?? "";

        if ($id <= 0 || !in_array($nuevo_estado, $estados, true)) {
            throw new Exception("Los datos del cambio de estado no son válidos.");
        }

        $consulta = $conexion->prepare("
            UPDATE cita
            SET estado = ?
            WHERE id_cita = ?
        ");
        $consulta->execute([$nuevo_estado, $id]);

        $mensaje = "El estado de la cita fue actualizado correctamente.";
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| REGISTRAR O ACTUALIZAR UNA CITA
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "guardar") {
    try {
        $id_cita = (int)($_POST["id_cita"] ?? 0);
        $id_paciente = (int)($_POST["id_paciente"] ?? 0);
        $id_medico = (int)($_POST["id_medico"] ?? 0);
        $id_especialidad = (int)($_POST["id_especialidad"] ?? 0);
        $id_consultorio = (int)($_POST["id_consultorio"] ?? 0);
        $id_turno = (int)($_POST["id_turno"] ?? 0);

        $fecha_cita = $_POST["fecha_cita"] ?? "";
        $hora_cita = $_POST["hora_cita"] ?? "";
        $motivo = trim($_POST["motivo_consulta"] ?? "");
        $observaciones = trim($_POST["observaciones"] ?? "");

        if (
            $id_paciente <= 0 ||
            $id_medico <= 0 ||
            $id_especialidad <= 0 ||
            $id_consultorio <= 0 ||
            $id_turno <= 0 ||
            $fecha_cita === "" ||
            $hora_cita === ""
        ) {
            throw new Exception("Completa todos los campos obligatorios.");
        }

        $fecha_objeto = DateTime::createFromFormat("Y-m-d", $fecha_cita);

        if (!$fecha_objeto || $fecha_objeto->format("Y-m-d") !== $fecha_cita) {
            throw new Exception("La fecha de la cita no es válida.");
        }

        $hora_objeto = DateTime::createFromFormat("H:i", $hora_cita);

        if (!$hora_objeto || $hora_objeto->format("H:i") !== $hora_cita) {
            throw new Exception("La hora de la cita no es válida.");
        }

        $dia_semana = $dias[(int)$fecha_objeto->format("N")];

        $conexion->beginTransaction();

        // 1. Verificar que el paciente exista.
        $consulta = $conexion->prepare("
            SELECT id_paciente
            FROM paciente
            WHERE id_paciente = ?
        ");
        $consulta->execute([$id_paciente]);

        if (!$consulta->fetch()) {
            throw new Exception("El paciente seleccionado no existe.");
        }

        // 2. Verificar que el médico esté activo.
        $consulta = $conexion->prepare("
            SELECT id_medico
            FROM medico
            WHERE id_medico = ?
              AND estado = 'Activo'
        ");
        $consulta->execute([$id_medico]);

        if (!$consulta->fetch()) {
            throw new Exception("El médico no existe o está inactivo.");
        }

        // 3. Verificar que la especialidad esté activa.
        $consulta = $conexion->prepare("
            SELECT id_especialidad
            FROM especialidad
            WHERE id_especialidad = ?
              AND estado = 'Activa'
        ");
        $consulta->execute([$id_especialidad]);

        if (!$consulta->fetch()) {
            throw new Exception("La especialidad no existe o está inactiva.");
        }

        // 4. Verificar que el consultorio esté disponible
        // y corresponda a la especialidad seleccionada.
        $consulta = $conexion->prepare("
            SELECT id_consultorio
            FROM consultorio
            WHERE id_consultorio = ?
              AND id_especialidad = ?
              AND estado = 'Disponible'
        ");
        $consulta->execute([$id_consultorio, $id_especialidad]);

        if (!$consulta->fetch()) {
            throw new Exception(
                "El consultorio no está disponible o no corresponde a la especialidad."
            );
        }

        // 5. Verificar que el médico tenga certificada la especialidad.
        $consulta = $conexion->prepare("
            SELECT id_medico
            FROM medico_especialidad
            WHERE id_medico = ?
              AND id_especialidad = ?
        ");
        $consulta->execute([$id_medico, $id_especialidad]);

        if (!$consulta->fetch()) {
            throw new Exception(
                "El médico no tiene registrada esa especialidad."
            );
        }

        // 6. Verificar que el turno corresponda al médico,
        // especialidad y consultorio seleccionados.
        $consulta = $conexion->prepare("
            SELECT
                id_turno,
                dia_semana,
                hora_inicio,
                hora_fin,
                cupos_disponibles
            FROM turno
            WHERE id_turno = ?
              AND id_medico = ?
              AND id_especialidad = ?
              AND id_consultorio = ?
              AND estado = 'Activo'
            FOR UPDATE
        ");

        $consulta->execute([
            $id_turno,
            $id_medico,
            $id_especialidad,
            $id_consultorio
        ]);

        $turno = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$turno) {
            throw new Exception(
                "El turno seleccionado no corresponde a los datos o está inactivo."
            );
        }

        // 7. Validar el día de atención.
        if ($turno["dia_semana"] !== $dia_semana) {
            throw new Exception(
                "El turno seleccionado no atiende el día " . $dia_semana . "."
            );
        }

        // 8. Validar que la hora esté dentro del turno.
       if (
    $hora_cita < substr($turno["hora_inicio"], 0, 5) ||
    $hora_cita >= substr($turno["hora_fin"], 0, 5)
) {
            throw new Exception(
                "La hora debe estar dentro del horario del turno: " .
                substr($turno["hora_inicio"], 0, 5) . " a " .
                substr($turno["hora_fin"], 0, 5) . "."
            );
        }

        // 9. Evitar que paciente, médico o consultorio
        // tengan otra cita en la misma fecha y hora.
        // Las citas canceladas no bloquean el horario.
        $sql_conflicto = "
            SELECT id_cita
            FROM cita
            WHERE fecha_cita = ?
              AND hora_cita = ?
              AND estado <> 'Cancelada'
              AND (
                    id_paciente = ?
                    OR id_medico = ?
                    OR id_consultorio = ?
              )
        ";

        $parametros_conflicto = [
            $fecha_cita,
            $hora_cita,
            $id_paciente,
            $id_medico,
            $id_consultorio
        ];

        if ($id_cita > 0) {
            $sql_conflicto .= " AND id_cita <> ?";
            $parametros_conflicto[] = $id_cita;
        }

        $consulta = $conexion->prepare($sql_conflicto);
        $consulta->execute($parametros_conflicto);

        if ($consulta->fetch()) {
            throw new Exception(
                "Ya existe una cita en esa fecha y hora para el paciente, médico o consultorio seleccionado."
            );
        }

        // 10. Contar los cupos ocupados del turno en esa fecha.
        // No se modifica el valor original de cupos_disponibles.
        $sql_cupos = "
            SELECT COUNT(*)
            FROM cita
            WHERE id_turno = ?
              AND fecha_cita = ?
              AND estado <> 'Cancelada'
        ";

        $parametros_cupos = [$id_turno, $fecha_cita];

        if ($id_cita > 0) {
            $sql_cupos .= " AND id_cita <> ?";
            $parametros_cupos[] = $id_cita;
        }

        $consulta = $conexion->prepare($sql_cupos);
        $consulta->execute($parametros_cupos);

        $cupos_ocupados = (int)$consulta->fetchColumn();

        if ($cupos_ocupados >= (int)$turno["cupos_disponibles"]) {
            throw new Exception(
                "El turno ya alcanzó su capacidad de " .
                $turno["cupos_disponibles"] . " citas para esa fecha."
            );
        }

        // 11. Guardar o actualizar la cita.
        if ($id_cita > 0) {
            $consulta = $conexion->prepare("
                UPDATE cita
                SET
                    id_paciente = ?,
                    id_medico = ?,
                    id_especialidad = ?,
                    id_consultorio = ?,
                    id_turno = ?,
                    fecha_cita = ?,
                    hora_cita = ?,
                    motivo_consulta = ?,
                    observaciones = ?
                WHERE id_cita = ?
            ");

            $consulta->execute([
                $id_paciente,
                $id_medico,
                $id_especialidad,
                $id_consultorio,
                $id_turno,
                $fecha_cita,
                $hora_cita,
                $motivo,
                $observaciones,
                $id_cita
            ]);

            $mensaje = "La cita fue actualizada correctamente.";
        } else {
            $consulta = $conexion->prepare("
                INSERT INTO cita (
                    id_paciente,
                    id_medico,
                    id_especialidad,
                    id_consultorio,
                    id_turno,
                    fecha_cita,
                    hora_cita,
                    estado,
                    motivo_consulta,
                    observaciones
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Programada', ?, ?)
            ");

            $consulta->execute([
                $id_paciente,
                $id_medico,
                $id_especialidad,
                $id_consultorio,
                $id_turno,
                $fecha_cita,
                $hora_cita,
                $motivo,
                $observaciones
            ]);

            $mensaje = "La cita fue registrada correctamente.";
        }

        $conexion->commit();

    } catch (Throwable $ex) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        $error = $ex->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| OBTENER INFORMACIÓN PARA LOS FORMULARIOS
|--------------------------------------------------------------------------
*/

$pacientes = $conexion->query("
    SELECT id_paciente, nombres, apellidos, documento
    FROM paciente
    ORDER BY nombres, apellidos
")->fetchAll(PDO::FETCH_ASSOC);

$medicos = $conexion->query("
    SELECT id_medico, nombres, apellidos, registro_medico
    FROM medico
    WHERE estado = 'Activo'
    ORDER BY nombres, apellidos
")->fetchAll(PDO::FETCH_ASSOC);

$especialidades = $conexion->query("
    SELECT id_especialidad, nombre_especialidad
    FROM especialidad
    WHERE estado = 'Activa'
    ORDER BY nombre_especialidad
")->fetchAll(PDO::FETCH_ASSOC);

$consultorios = $conexion->query("
    SELECT id_consultorio, codigo, nombre_consultorio
    FROM consultorio
    WHERE estado = 'Disponible'
    ORDER BY codigo
")->fetchAll(PDO::FETCH_ASSOC);

$turnos = $conexion->query("
    SELECT
        t.id_turno,
        t.id_medico,
        t.id_especialidad,
        t.id_consultorio,
        t.dia_semana,
        t.hora_inicio,
        t.hora_fin,
        t.cupos_disponibles,
        m.nombres AS medico_nombre,
        m.apellidos AS medico_apellido,
        es.nombre_especialidad,
        c.codigo AS codigo_consultorio
    FROM turno t
    INNER JOIN medico m ON t.id_medico = m.id_medico
    INNER JOIN especialidad es ON t.id_especialidad = es.id_especialidad
    INNER JOIN consultorio c ON t.id_consultorio = c.id_consultorio
    WHERE t.estado = 'Activo'
      AND m.estado = 'Activo'
      AND es.estado = 'Activa'
      AND c.estado = 'Disponible'
    ORDER BY t.dia_semana, t.hora_inicio
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| CARGAR CITA PARA EDITAR
|--------------------------------------------------------------------------
*/

$cita_editar = null;

if (isset($_GET["editar"])) {
    $id_editar = (int)$_GET["editar"];

    $consulta = $conexion->prepare("
        SELECT *
        FROM cita
        WHERE id_cita = ?
    ");
    $consulta->execute([$id_editar]);

    $cita_editar = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$cita_editar) {
        $error = "No se encontró la cita solicitada.";
    }
}

/*
|--------------------------------------------------------------------------
| FILTROS Y LISTADO DE CITAS
|--------------------------------------------------------------------------
*/

$filtro = trim($_GET["buscar"] ?? "");
$filtro_fecha = trim($_GET["fecha"] ?? "");
$filtro_estado = trim($_GET["estado"] ?? "");

$sql = "
    SELECT
        c.*,
        p.nombres AS paciente_nombre,
        p.apellidos AS paciente_apellido,
        p.documento,
        m.nombres AS medico_nombre,
        m.apellidos AS medico_apellido,
        es.nombre_especialidad,
        co.codigo AS codigo_consultorio,
        t.dia_semana
    FROM cita c
    INNER JOIN paciente p ON c.id_paciente = p.id_paciente
    INNER JOIN medico m ON c.id_medico = m.id_medico
    INNER JOIN especialidad es ON c.id_especialidad = es.id_especialidad
    INNER JOIN consultorio co ON c.id_consultorio = co.id_consultorio
    LEFT JOIN turno t ON c.id_turno = t.id_turno
    WHERE 1 = 1
";

$parametros = [];

if ($filtro !== "") {
    $sql .= "
        AND (
            p.nombres LIKE ?
            OR p.apellidos LIKE ?
            OR p.documento LIKE ?
            OR m.nombres LIKE ?
            OR m.apellidos LIKE ?
        )
    ";

    $texto_busqueda = "%" . $filtro . "%";

    $parametros = array_merge(
        $parametros,
        [
            $texto_busqueda,
            $texto_busqueda,
            $texto_busqueda,
            $texto_busqueda,
            $texto_busqueda
        ]
    );
}

if ($filtro_fecha !== "") {
    $sql .= " AND c.fecha_cita = ?";
    $parametros[] = $filtro_fecha;
}

if ($filtro_estado !== "" && in_array($filtro_estado, $estados, true)) {
    $sql .= " AND c.estado = ?";
    $parametros[] = $filtro_estado;
}

$sql .= " ORDER BY c.fecha_cita DESC, c.hora_cita DESC";

$consulta = $conexion->prepare($sql);
$consulta->execute($parametros);
$citas = $consulta->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS
|--------------------------------------------------------------------------
*/

$estadisticas = $conexion->query("
    SELECT
        COUNT(*) AS total,
        SUM(estado = 'Programada') AS programadas,
        SUM(estado = 'Confirmada') AS confirmadas,
        SUM(estado = 'Atendida') AS atendidas,
        SUM(estado = 'Cancelada') AS canceladas
    FROM cita
")->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de citas | Hospital Central</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f4f7fb;
            color: #263445;
            font-family: Arial, sans-serif;
        }

        .contenido {
            padding: 30px;
        }

        .encabezado {
            margin-bottom: 25px;
        }

        .encabezado h2 {
            font-weight: 700;
            color: #183b56;
        }

        .tarjeta {
            background: white;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 3px 15px rgba(0,0,0,.05);
            margin-bottom: 22px;
        }

        .estadistica {
            border-left: 5px solid #198754;
        }

        .estadistica h3 {
            font-size: 28px;
            font-weight: bold;
            margin: 0;
        }

        .estadistica p {
            color: #718096;
            margin: 5px 0 0;
        }

        .form-label {
            font-weight: 600;
            color: #34495e;
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px;
        }

        .btn {
            border-radius: 8px;
        }

        .tabla-contenedor {
            overflow-x: auto;
        }

        .table {
            vertical-align: middle;
        }

        .table thead {
            background: #eaf1f7;
        }

        .estado {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .Programada { background: #e0f2fe; color: #0369a1; }
        .Confirmada { background: #dcfce7; color: #166534; }
        .Atendida { background: #d1fae5; color: #065f46; }
        .Cancelada { background: #fee2e2; color: #991b1b; }
        .No-asistio { background: #fef3c7; color: #92400e; }

        .ayuda {
            font-size: 13px;
            color: #718096;
        }

        @media (max-width: 768px) {
            .contenido {
                padding: 15px;
            }
        }
    </style>
</head>

<body>

<div class="contenido">

    <div class="encabezado d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2>Gestión de citas médicas</h2>
            <p class="text-secondary mb-0">
                Hospital Central de Neiva
            </p>
        </div>

        <a href="../index.php" class="btn btn-outline-secondary">
            Volver al panel principal
        </a>
    </div>

    <?php if ($mensaje !== ""): ?>
        <div class="alert alert-success">
            <?= e($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- ESTADÍSTICAS -->

    <div class="row g-3 mb-3">

        <div class="col-md-4 col-lg">
            <div class="tarjeta estadistica">
                <h3><?= (int)($estadisticas["total"] ?? 0) ?></h3>
                <p>Total de citas</p>
            </div>
        </div>

        <div class="col-md-4 col-lg">
            <div class="tarjeta estadistica">
                <h3><?= (int)($estadisticas["programadas"] ?? 0) ?></h3>
                <p>Programadas</p>
            </div>
        </div>

        <div class="col-md-4 col-lg">
            <div class="tarjeta estadistica">
                <h3><?= (int)($estadisticas["confirmadas"] ?? 0) ?></h3>
                <p>Confirmadas</p>
            </div>
        </div>

        <div class="col-md-4 col-lg">
            <div class="tarjeta estadistica">
                <h3><?= (int)($estadisticas["atendidas"] ?? 0) ?></h3>
                <p>Atendidas</p>
            </div>
        </div>

        <div class="col-md-4 col-lg">
            <div class="tarjeta estadistica">
                <h3><?= (int)($estadisticas["canceladas"] ?? 0) ?></h3>
                <p>Canceladas</p>
            </div>
        </div>

    </div>

    <!-- FORMULARIO -->

    <div class="tarjeta">

        <h4 class="mb-3">
            <?= $cita_editar ? "Modificar cita" : "Registrar nueva cita" ?>
        </h4>

        <form method="POST">

            <input type="hidden" name="accion" value="guardar">

            <input
                type="hidden"
                name="id_cita"
                value="<?= e($cita_editar["id_cita"] ?? 0) ?>"
            >

            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label">Paciente *</label>

                    <select name="id_paciente" class="form-select" required>
                        <option value="">Seleccione un paciente</option>

                        <?php foreach ($pacientes as $p): ?>
                            <option
                                value="<?= e($p["id_paciente"]) ?>"
                                <?= (($cita_editar["id_paciente"] ?? "") == $p["id_paciente"]) ? "selected" : "" ?>
                            >
                                <?= e($p["nombres"] . " " . $p["apellidos"] . " - " . $p["documento"]) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Médico *</label>

                    <select name="id_medico" class="form-select" required>
                        <option value="">Seleccione un médico</option>

                        <?php foreach ($medicos as $m): ?>
                            <option
                                value="<?= e($m["id_medico"]) ?>"
                                <?= (($cita_editar["id_medico"] ?? "") == $m["id_medico"]) ? "selected" : "" ?>
                            >
                                <?= e($m["nombres"] . " " . $m["apellidos"] . " - " . $m["registro_medico"]) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Especialidad *</label>

                    <select name="id_especialidad" class="form-select" required>
                        <option value="">Seleccione una especialidad</option>

                        <?php foreach ($especialidades as $es): ?>
                            <option
                                value="<?= e($es["id_especialidad"]) ?>"
                                <?= (($cita_editar["id_especialidad"] ?? "") == $es["id_especialidad"]) ? "selected" : "" ?>
                            >
                                <?= e($es["nombre_especialidad"]) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Consultorio *</label>

                    <select name="id_consultorio" class="form-select" required>
                        <option value="">Seleccione un consultorio</option>

                        <?php foreach ($consultorios as $co): ?>
                            <option
                                value="<?= e($co["id_consultorio"]) ?>"
                                <?= (($cita_editar["id_consultorio"] ?? "") == $co["id_consultorio"]) ? "selected" : "" ?>
                            >
                                <?= e($co["codigo"] . " - " . $co["nombre_consultorio"]) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Turno médico *</label>

                    <select name="id_turno" class="form-select" required>
                        <option value="">Seleccione un turno</option>

                        <?php foreach ($turnos as $t): ?>
                            <option
                                value="<?= e($t["id_turno"]) ?>"
                                <?= (($cita_editar["id_turno"] ?? "") == $t["id_turno"]) ? "selected" : "" ?>
                            >
                                <?= e(
                                    $t["medico_nombre"] . " " .
                                    $t["medico_apellido"] . " | " .
                                    $t["nombre_especialidad"] . " | " .
                                    $t["codigo_consultorio"] . " | " .
                                    $t["dia_semana"] . " | " .
                                    substr($t["hora_inicio"], 0, 5) . " - " .
                                    substr($t["hora_fin"], 0, 5) . " | Cupos: " .
                                    $t["cupos_disponibles"]
                                ) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                    <div class="ayuda mt-1">
                        El turno debe coincidir con el médico, la especialidad,
                        el consultorio, el día y la hora de la cita.
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Fecha de la cita *</label>

                    <input
                        type="date"
                        name="fecha_cita"
                        class="form-control"
                        value="<?= e($cita_editar["fecha_cita"] ?? "") ?>"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">Hora de la cita *</label>

                    <input
                        type="time"
                        name="hora_cita"
                        class="form-control"
                        value="<?= e(isset($cita_editar["hora_cita"]) ? substr($cita_editar["hora_cita"], 0, 5) : "") ?>"
                        required
                    >
                </div>

                <div class="col-12">
                    <label class="form-label">Motivo de consulta</label>

                    <textarea
                        name="motivo_consulta"
                        class="form-control"
                        rows="2"
                    ><?= e($cita_editar["motivo_consulta"] ?? "") ?></textarea>
                </div>

                <div class="col-12">
                    <label class="form-label">Observaciones</label>

                    <textarea
                        name="observaciones"
                        class="form-control"
                        rows="2"
                    ><?= e($cita_editar["observaciones"] ?? "") ?></textarea>
                </div>

                <div class="col-12 d-flex gap-2 flex-wrap">

                    <button type="submit" class="btn btn-primary">
                        <?= $cita_editar ? "Guardar cambios" : "Registrar cita" ?>
                    </button>

                    <?php if ($cita_editar): ?>
                        <a href="citas.php" class="btn btn-outline-secondary">
                            Cancelar edición
                        </a>
                    <?php endif; ?>

                </div>

            </div>

        </form>

    </div>

    <!-- BUSCADOR -->

    <div class="tarjeta">

        <h4 class="mb-3">Consultar citas registradas</h4>

        <form method="GET" class="row g-3">

            <div class="col-md-4">
                <label class="form-label">Paciente o médico</label>

                <input
                    type="text"
                    name="buscar"
                    class="form-control"
                    placeholder="Nombre, apellido o documento"
                    value="<?= e($filtro) ?>"
                >
            </div>

            <div class="col-md-3">
                <label class="form-label">Fecha</label>

                <input
                    type="date"
                    name="fecha"
                    class="form-control"
                    value="<?= e($filtro_fecha) ?>"
                >
            </div>

            <div class="col-md-3">
                <label class="form-label">Estado</label>

                <select name="estado" class="form-select">
                    <option value="">Todos los estados</option>

                    <?php foreach ($estados as $estado): ?>
                        <option
                            value="<?= e($estado) ?>"
                            <?= $filtro_estado === $estado ? "selected" : "" ?>
                        >
                            <?= e($estado) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div class="col-md-2 d-flex align-items-end gap-2">
                <button class="btn btn-primary" type="submit">
                    Buscar
                </button>

                <a href="citas.php" class="btn btn-outline-secondary">
                    Limpiar
                </a>
            </div>

        </form>

    </div>

    <!-- TABLA DE CITAS -->

    <div class="tarjeta">

        <h4 class="mb-3">Listado de citas</h4>

        <div class="tabla-contenedor">

            <table class="table table-hover">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Paciente</th>
                        <th>Médico</th>
                        <th>Especialidad</th>
                        <th>Consultorio</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($citas) > 0): ?>

                    <?php foreach ($citas as $c): ?>

                        <?php
                        $clase_estado = str_replace(" ", "-", $c["estado"]);
                        ?>

                        <tr>

                            <td><?= e($c["id_cita"]) ?></td>

                            <td>
                                <?= e($c["paciente_nombre"] . " " . $c["paciente_apellido"]) ?>
                                <br>
                                <small class="text-secondary">
                                    <?= e($c["documento"]) ?>
                                </small>
                            </td>

                            <td>
                                <?= e($c["medico_nombre"] . " " . $c["medico_apellido"]) ?>
                            </td>

                            <td><?= e($c["nombre_especialidad"]) ?></td>

                            <td><?= e($c["codigo_consultorio"]) ?></td>

                            <td><?= e($c["fecha_cita"]) ?></td>

                            <td><?= e(substr($c["hora_cita"], 0, 5)) ?></td>

                            <td>
                                <span class="estado <?= e($clase_estado) ?>">
                                    <?= e($c["estado"]) ?>
                                </span>
                            </td>

                            <td>

                                <a
                                    href="citas.php?editar=<?= e($c["id_cita"]) ?>"
                                    class="btn btn-sm btn-outline-primary mb-1"
                                >
                                    Editar
                                </a>

                                <form method="POST" class="d-flex gap-1 mt-1">

                                    <input type="hidden" name="accion" value="estado">

                                    <input
                                        type="hidden"
                                        name="id_cita"
                                        value="<?= e($c["id_cita"]) ?>"
                                    >

                                    <select
                                        name="nuevo_estado"
                                        class="form-select form-select-sm"
                                    >

                                        <?php foreach ($estados as $estado): ?>
                                            <option
                                                value="<?= e($estado) ?>"
                                                <?= $c["estado"] === $estado ? "selected" : "" ?>
                                            >
                                                <?= e($estado) ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-success"
                                        title="Actualizar estado"
                                    >
                                        OK
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="9" class="text-center text-secondary py-4">
                            No se encontraron citas.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <p class="ayuda mb-0">
            Las citas se conservan en el historial. Para retirarlas de la agenda,
            cambia su estado a Cancelada; no se eliminan físicamente.
        </p>

    </div>

</div>

</body>
</html>
