
<?php
date_default_timezone_set("America/Bogota");

require_once __DIR__ . "/../config/conexion.php";

function e($valor) {
    return htmlspecialchars((string)($valor ?? ""), ENT_QUOTES, "UTF-8");
}

$mensaje = "";
$error = "";

/*
|--------------------------------------------------------------------------
| REGISTRAR O MODIFICAR DIAGNÓSTICO
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "guardar") {
    try {
        $id_diagnostico = (int)($_POST["id_diagnostico"] ?? 0);
        $id_historia = (int)($_POST["id_historia"] ?? 0);
        $id_medico = (int)($_POST["id_medico"] ?? 0);
        $id_cita = (int)($_POST["id_cita"] ?? 0);

        $fecha_diagnostico = trim($_POST["fecha_diagnostico"] ?? "");
        $descripcion = trim($_POST["descripcion"] ?? "");
        $tratamiento = trim($_POST["tratamiento"] ?? "");

        if ($id_historia <= 0 || $id_medico <= 0 || $descripcion === "") {
            throw new Exception("Completa la historia clínica, el médico y la descripción.");
        }

        if ($fecha_diagnostico === "") {
            $fecha_diagnostico = date("Y-m-d H:i:s");
        } else {
            $fecha_objeto = DateTime::createFromFormat("Y-m-d\TH:i", $fecha_diagnostico);

            if (!$fecha_objeto || $fecha_objeto->format("Y-m-d\TH:i") !== $fecha_diagnostico) {
                throw new Exception("La fecha y hora del diagnóstico no son válidas.");
            }

            $fecha_diagnostico = $fecha_objeto->format("Y-m-d H:i:s");
        }

        $conexion->beginTransaction();

        // Verificar que exista la historia clínica y obtener su paciente.
        $consulta = $conexion->prepare("
            SELECT id_historia, id_paciente
            FROM historia_clinica
            WHERE id_historia = ?
        ");
        $consulta->execute([$id_historia]);

        $historia = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$historia) {
            throw new Exception("La historia clínica seleccionada no existe.");
        }

        // Verificar que el médico esté activo.
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

        // Si se selecciona una cita, verificar que corresponda
        // al mismo paciente y al mismo médico.
        if ($id_cita > 0) {
            $consulta = $conexion->prepare("
                SELECT id_cita
                FROM cita
                WHERE id_cita = ?
                  AND id_paciente = ?
                  AND id_medico = ?
                  AND estado = 'Atendida'
            ");

            $consulta->execute([
                $id_cita,
                $historia["id_paciente"],
                $id_medico
            ]);

            if (!$consulta->fetch()) {
                throw new Exception(
                    "La cita seleccionada debe estar atendida y corresponder al paciente y médico elegidos."
                );
            }
        }

        // Guardar o modificar.
        if ($id_diagnostico > 0) {
            $consulta = $conexion->prepare("
                UPDATE diagnostico
                SET
                    id_historia = ?,
                    id_medico = ?,
                    id_cita = ?,
                    fecha_diagnostico = ?,
                    descripcion = ?,
                    tratamiento = ?
                WHERE id_diagnostico = ?
            ");

            $consulta->execute([
                $id_historia,
                $id_medico,
                $id_cita > 0 ? $id_cita : null,
                $fecha_diagnostico,
                $descripcion,
                $tratamiento !== "" ? $tratamiento : null,
                $id_diagnostico
            ]);

            $mensaje = "El diagnóstico fue actualizado correctamente.";

        } else {
            $consulta = $conexion->prepare("
                INSERT INTO diagnostico (
                    id_historia,
                    id_medico,
                    id_cita,
                    fecha_diagnostico,
                    descripcion,
                    tratamiento
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");

            $consulta->execute([
                $id_historia,
                $id_medico,
                $id_cita > 0 ? $id_cita : null,
                $fecha_diagnostico,
                $descripcion,
                $tratamiento !== "" ? $tratamiento : null
            ]);

            $mensaje = "El diagnóstico fue registrado correctamente.";
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
| LISTAS PARA LOS SELECTORES
|--------------------------------------------------------------------------
*/

// Historias clínicas con sus pacientes.
$historias = $conexion->query("
    SELECT
        h.id_historia,
        h.numero_historia,
        p.id_paciente,
        p.nombres,
        p.apellidos,
        p.documento
    FROM historia_clinica h
    INNER JOIN paciente p ON h.id_paciente = p.id_paciente
    ORDER BY p.nombres, p.apellidos
")->fetchAll(PDO::FETCH_ASSOC);

// Médicos activos.
$medicos = $conexion->query("
    SELECT
        id_medico,
        nombres,
        apellidos,
        registro_medico
    FROM medico
    WHERE estado = 'Activo'
    ORDER BY nombres, apellidos
")->fetchAll(PDO::FETCH_ASSOC);

// Citas atendidas.
$citas = $conexion->query("
    SELECT
        c.id_cita,
        c.id_paciente,
        c.id_medico,
        c.fecha_cita,
        c.hora_cita,
        p.nombres AS paciente_nombre,
        p.apellidos AS paciente_apellido,
        m.nombres AS medico_nombre,
        m.apellidos AS medico_apellido
    FROM cita c
    INNER JOIN paciente p ON c.id_paciente = p.id_paciente
    INNER JOIN medico m ON c.id_medico = m.id_medico
    WHERE c.estado = 'Atendida'
    ORDER BY c.fecha_cita DESC, c.hora_cita DESC
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| CARGAR DIAGNÓSTICO PARA EDICIÓN
|--------------------------------------------------------------------------
*/

$diagnostico_editar = null;

if (isset($_GET["editar"])) {
    $id_editar = (int)$_GET["editar"];

    $consulta = $conexion->prepare("
        SELECT *
        FROM diagnostico
        WHERE id_diagnostico = ?
    ");

    $consulta->execute([$id_editar]);
    $diagnostico_editar = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$diagnostico_editar) {
        $error = "No se encontró el diagnóstico solicitado.";
    }
}

/*
|--------------------------------------------------------------------------
| BUSCADOR Y LISTADO
|--------------------------------------------------------------------------
*/

$buscar = trim($_GET["buscar"] ?? "");

$sql = "
    SELECT
        d.*,
        h.numero_historia,
        p.nombres AS paciente_nombre,
        p.apellidos AS paciente_apellido,
        p.documento,
        m.nombres AS medico_nombre,
        m.apellidos AS medico_apellido,
        c.fecha_cita,
        c.hora_cita
    FROM diagnostico d
    INNER JOIN historia_clinica h ON d.id_historia = h.id_historia
    INNER JOIN paciente p ON h.id_paciente = p.id_paciente
    INNER JOIN medico m ON d.id_medico = m.id_medico
    LEFT JOIN cita c ON d.id_cita = c.id_cita
    WHERE 1 = 1
";

$parametros = [];

if ($buscar !== "") {
    $sql .= "
        AND (
            p.nombres LIKE ?
            OR p.apellidos LIKE ?
            OR p.documento LIKE ?
            OR m.nombres LIKE ?
            OR m.apellidos LIKE ?
            OR d.descripcion LIKE ?
            OR h.numero_historia LIKE ?
        )
    ";

    $texto = "%" . $buscar . "%";

    $parametros = [
        $texto,
        $texto,
        $texto,
        $texto,
        $texto,
        $texto,
        $texto
    ];
}

$sql .= " ORDER BY d.fecha_diagnostico DESC";

$consulta = $conexion->prepare($sql);
$consulta->execute($parametros);
$diagnosticos = $consulta->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS
|--------------------------------------------------------------------------
*/

$estadisticas = $conexion->query("
    SELECT
        COUNT(*) AS total,
        COUNT(DISTINCT id_historia) AS historias,
        SUM(tratamiento IS NOT NULL AND tratamiento <> '') AS con_tratamiento
    FROM diagnostico
")->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Diagnósticos médicos | Hospital Central</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

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

        .ayuda {
            font-size: 13px;
            color: #718096;
        }

        .descripcion {
            min-width: 220px;
            max-width: 350px;
            white-space: normal;
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
            <h2>Diagnósticos médicos</h2>
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

        <div class="col-md-4">
            <div class="tarjeta estadistica">
                <h3><?= (int)($estadisticas["total"] ?? 0) ?></h3>
                <p>Total de diagnósticos</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="tarjeta estadistica">
                <h3><?= (int)($estadisticas["historias"] ?? 0) ?></h3>
                <p>Historias clínicas con diagnósticos</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="tarjeta estadistica">
                <h3><?= (int)($estadisticas["con_tratamiento"] ?? 0) ?></h3>
                <p>Diagnósticos con tratamiento</p>
            </div>
        </div>

    </div>

    <!-- FORMULARIO -->

    <div class="tarjeta">

        <h4 class="mb-3">
            <?= $diagnostico_editar ? "Modificar diagnóstico" : "Registrar diagnóstico" ?>
        </h4>

        <form method="POST">

            <input type="hidden" name="accion" value="guardar">

            <input
                type="hidden"
                name="id_diagnostico"
                value="<?= e($diagnostico_editar["id_diagnostico"] ?? 0) ?>"
            >

            <div class="row g-3">

                <div class="col-md-6">

                    <label class="form-label">Historia clínica *</label>

                    <select name="id_historia" class="form-select" required>

                        <option value="">Seleccione una historia clínica</option>

                        <?php foreach ($historias as $h): ?>

                            <option
                                value="<?= e($h["id_historia"]) ?>"
                                <?= (($diagnostico_editar["id_historia"] ?? "") == $h["id_historia"]) ? "selected" : "" ?>
                            >
                                <?= e(
                                    $h["numero_historia"] . " | " .
                                    $h["nombres"] . " " .
                                    $h["apellidos"] . " | Documento: " .
                                    $h["documento"]
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-md-6">

                    <label class="form-label">Médico responsable *</label>

                    <select name="id_medico" class="form-select" required>

                        <option value="">Seleccione un médico</option>

                        <?php foreach ($medicos as $m): ?>

                            <option
                                value="<?= e($m["id_medico"]) ?>"
                                <?= (($diagnostico_editar["id_medico"] ?? "") == $m["id_medico"]) ? "selected" : "" ?>
                            >
                                <?= e(
                                    $m["nombres"] . " " .
                                    $m["apellidos"] . " | " .
                                    $m["registro_medico"]
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-12">

                    <label class="form-label">Cita relacionada (opcional)</label>

                    <select name="id_cita" class="form-select">

                        <option value="0">Sin cita relacionada</option>

                        <?php foreach ($citas as $c): ?>

                            <option
                                value="<?= e($c["id_cita"]) ?>"
                                <?= (($diagnostico_editar["id_cita"] ?? 0) == $c["id_cita"]) ? "selected" : "" ?>
                            >
                                <?= e(
                                    "Cita #" . $c["id_cita"] . " | " .
                                    $c["paciente_nombre"] . " " .
                                    $c["paciente_apellido"] . " | " .
                                    $c["medico_nombre"] . " " .
                                    $c["medico_apellido"] . " | " .
                                    $c["fecha_cita"] . " " .
                                    substr($c["hora_cita"], 0, 5)
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <div class="ayuda mt-1">
                        Solo se pueden relacionar citas que estén marcadas como Atendida.
                    </div>

                </div>

                <div class="col-md-6">

                    <label class="form-label">Fecha y hora del diagnóstico</label>

                    <input
                        type="datetime-local"
                        name="fecha_diagnostico"
                        class="form-control"
                        value="<?= e(
                            isset($diagnostico_editar["fecha_diagnostico"])
                            ? date(
                                "Y-m-d\TH:i",
                                strtotime($diagnostico_editar["fecha_diagnostico"])
                            )
                            : date("Y-m-d\TH:i")
                        ) ?>"
                    >

                </div>

                <div class="col-md-12">

                    <label class="form-label">Descripción del diagnóstico *</label>

                    <textarea
                        name="descripcion"
                        class="form-control"
                        rows="3"
                        required
                    ><?= e($diagnostico_editar["descripcion"] ?? "") ?></textarea>

                </div>

                <div class="col-md-12">

                    <label class="form-label">Tratamiento indicado</label>

                    <textarea
                        name="tratamiento"
                        class="form-control"
                        rows="3"
                    ><?= e($diagnostico_editar["tratamiento"] ?? "") ?></textarea>

                </div>

                <div class="col-12 d-flex gap-2 flex-wrap">

                    <button type="submit" class="btn btn-primary">
                        <?= $diagnostico_editar ? "Guardar cambios" : "Registrar diagnóstico" ?>
                    </button>

                    <?php if ($diagnostico_editar): ?>

                        <a href="diagnosticos.php" class="btn btn-outline-secondary">
                            Cancelar edición
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        </form>

    </div>

    <!-- BUSCADOR -->

    <div class="tarjeta">

        <h4 class="mb-3">Buscar diagnósticos</h4>

        <form method="GET" class="row g-3">

            <div class="col-md-9">

                <input
                    type="text"
                    name="buscar"
                    class="form-control"
                    placeholder="Paciente, documento, médico, historia clínica o diagnóstico"
                    value="<?= e($buscar) ?>"
                >

            </div>

            <div class="col-md-3 d-flex gap-2">

                <button type="submit" class="btn btn-primary">
                    Buscar
                </button>

                <a href="diagnosticos.php" class="btn btn-outline-secondary">
                    Limpiar
                </a>

            </div>

        </form>

    </div>

    <!-- TABLA -->

    <div class="tarjeta">

        <h4 class="mb-3">Historial de diagnósticos</h4>

        <div class="tabla-contenedor">

            <table class="table table-hover">

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Historia clínica</th>
                        <th>Paciente</th>
                        <th>Médico</th>
                        <th>Fecha</th>
                        <th>Descripción</th>
                        <th>Tratamiento</th>
                        <th>Cita</th>
                        <th>Acciones</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (count($diagnosticos) > 0): ?>

                        <?php foreach ($diagnosticos as $d): ?>

                            <tr>

                                <td><?= e($d["id_diagnostico"]) ?></td>

                                <td><?= e($d["numero_historia"]) ?></td>

                                <td>
                                    <?= e($d["paciente_nombre"] . " " . $d["paciente_apellido"]) ?>
                                    <br>
                                    <small class="text-secondary">
                                        <?= e($d["documento"]) ?>
                                    </small>
                                </td>

                                <td>
                                    <?= e($d["medico_nombre"] . " " . $d["medico_apellido"]) ?>
                                </td>

                                <td><?= e($d["fecha_diagnostico"]) ?></td>

                                <td class="descripcion">
                                    <?= nl2br(e($d["descripcion"])) ?>
                                </td>

                                <td class="descripcion">
                                    <?= $d["tratamiento"]
                                        ? nl2br(e($d["tratamiento"]))
                                        : '<span class="text-secondary">Sin tratamiento registrado</span>' ?>
                                </td>

                                <td>
                                    <?= $d["id_cita"]
                                        ? "Cita #" . e($d["id_cita"])
                                        : "Sin cita" ?>
                                </td>

                                <td>
                                    <a
                                        href="diagnosticos.php?editar=<?= e($d["id_diagnostico"]) ?>"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        Editar
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="9" class="text-center text-secondary py-4">
                                No se encontraron diagnósticos.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <p class="ayuda mb-0">
            Los diagnósticos se conservan como parte del historial clínico
            del paciente.
        </p>

    </div>

</div>

</body>
</html>
