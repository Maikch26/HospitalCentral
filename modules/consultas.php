
<?php
require_once "../config/conexion.php";

date_default_timezone_set("America/Bogota");

$consultas = [
    1 => "Pacientes y sus EPS",
    2 => "Consultorios y especialidades",
    3 => "Citas con pacientes y médicos",
    4 => "Turnos con médicos y consultorios",
    5 => "Pacientes mujeres por EPS",
    6 => "Citas canceladas o con inasistencia",
    7 => "Especialidades médicas",
    8 => "Pacientes cuyo nombre inicia por A o tienen Gmail",
    9 => "Citas dentro de un rango de fechas",
    10 => "Pacientes atendidos en EPS cuyo nombre contiene SALUD",
    11 => "Citas sin turno de Cardiología, Pediatría o Dermatología",
    12 => "Diagnósticos con tratamiento registrado"
];

$numero = isset($_GET["consulta"]) ? (int) $_GET["consulta"] : 1;

if (!isset($consultas[$numero])) {
    $numero = 1;
}

$filas = [];
$error = "";
$titulo = $consultas[$numero];

$epsSeleccionada = $_GET["eps"] ?? "";
$fechaInicio = $_GET["fecha_inicio"] ?? date("Y-m-01");
$fechaFin = $_GET["fecha_fin"] ?? date("Y-m-t");

try {

    switch ($numero) {

        case 1:
            $sql = "SELECT p.id_paciente, p.documento,
                           CONCAT(p.nombres, ' ', p.apellidos) AS paciente,
                           e.nombre_eps
                    FROM paciente p
                    INNER JOIN eps e ON p.id_eps = e.id_eps
                    ORDER BY paciente";
            $stmt = $conexion->prepare($sql);
            $stmt->execute();
            break;

        case 2:
            $sql = "SELECT c.codigo, c.nombre_consultorio,
                           c.ubicacion, e.nombre_especialidad,
                           c.estado
                    FROM consultorio c
                    INNER JOIN especialidad e
                        ON c.id_especialidad = e.id_especialidad
                    ORDER BY c.codigo";
            $stmt = $conexion->prepare($sql);
            $stmt->execute();
            break;

        case 3:
            $sql = "SELECT ci.id_cita,
                           CONCAT(p.nombres, ' ', p.apellidos) AS paciente,
                           CONCAT(m.nombres, ' ', m.apellidos) AS medico,
                           e.nombre_especialidad,
                           ci.fecha_cita, ci.hora_cita, ci.estado
                    FROM cita ci
                    INNER JOIN paciente p ON ci.id_paciente = p.id_paciente
                    INNER JOIN medico m ON ci.id_medico = m.id_medico
                    INNER JOIN especialidad e
                        ON ci.id_especialidad = e.id_especialidad
                    ORDER BY ci.fecha_cita, ci.hora_cita";
            $stmt = $conexion->prepare($sql);
            $stmt->execute();
            break;

        case 4:
            $sql = "SELECT t.id_turno,
                           CONCAT(m.nombres, ' ', m.apellidos) AS medico,
                           e.nombre_especialidad,
                           c.codigo AS consultorio,
                           t.dia_semana, t.hora_inicio, t.hora_fin,
                           t.cupos_disponibles, t.estado
                    FROM turno t
                    INNER JOIN medico m ON t.id_medico = m.id_medico
                    INNER JOIN especialidad e
                        ON t.id_especialidad = e.id_especialidad
                    INNER JOIN consultorio c
                        ON t.id_consultorio = c.id_consultorio
                    ORDER BY t.dia_semana, t.hora_inicio";
            $stmt = $conexion->prepare($sql);
            $stmt->execute();
            break;

        case 5:
            $sql = "SELECT p.documento,
                           CONCAT(p.nombres, ' ', p.apellidos) AS paciente,
                           p.telefono, p.correo, e.nombre_eps
                    FROM paciente p
                    INNER JOIN eps e ON p.id_eps = e.id_eps
                    WHERE p.genero = 'Femenino'
                      AND (:eps = '' OR e.id_eps = :id_eps)
                    ORDER BY paciente";
            $stmt = $conexion->prepare($sql);
            $stmt->bindValue(":eps", $epsSeleccionada);
            $stmt->bindValue(
                ":id_eps",
                $epsSeleccionada === "" ? 0 : (int)$epsSeleccionada,
                PDO::PARAM_INT
            );
            $stmt->execute();
            break;

        case 6:
            $sql = "SELECT ci.id_cita,
                           CONCAT(p.nombres, ' ', p.apellidos) AS paciente,
                           CONCAT(m.nombres, ' ', m.apellidos) AS medico,
                           ci.fecha_cita, ci.hora_cita, ci.estado
                    FROM cita ci
                    INNER JOIN paciente p ON ci.id_paciente = p.id_paciente
                    INNER JOIN medico m ON ci.id_medico = m.id_medico
                    WHERE ci.estado IN ('Cancelada', 'No asistio')
                    ORDER BY ci.fecha_cita DESC";
            $stmt = $conexion->prepare($sql);
            $stmt->execute();
            break;

        case 7:
            $sql = "SELECT e.id_especialidad, e.nombre_especialidad,
                           e.descripcion, e.estado,
                           COUNT(DISTINCT me.id_medico) AS total_medicos
                    FROM especialidad e
                    LEFT JOIN medico_especialidad me
                        ON e.id_especialidad = me.id_especialidad
                    WHERE e.nombre_especialidad IN
                        ('Cardiologia', 'Pediatria', 'Dermatologia')
                    GROUP BY e.id_especialidad, e.nombre_especialidad,
                             e.descripcion, e.estado
                    ORDER BY e.nombre_especialidad";
            $stmt = $conexion->prepare($sql);
            $stmt->execute();
            break;

        case 8:
            $sql = "SELECT p.documento,
                           CONCAT(p.nombres, ' ', p.apellidos) AS paciente,
                           p.correo, p.telefono
                    FROM paciente p
                    WHERE p.nombres LIKE 'A%'
                       OR p.correo LIKE '%@gmail.com'
                    ORDER BY paciente";
            $stmt = $conexion->prepare($sql);
            $stmt->execute();
            break;

        case 9:
            $sql = "SELECT ci.id_cita,
                           CONCAT(p.nombres, ' ', p.apellidos) AS paciente,
                           CONCAT(m.nombres, ' ', m.apellidos) AS medico,
                           ci.fecha_cita, ci.hora_cita, ci.estado
                    FROM cita ci
                    INNER JOIN paciente p ON ci.id_paciente = p.id_paciente
                    INNER JOIN medico m ON ci.id_medico = m.id_medico
                    WHERE ci.fecha_cita BETWEEN :inicio AND :fin
                    ORDER BY ci.fecha_cita, ci.hora_cita";
            $stmt = $conexion->prepare($sql);
            $stmt->bindValue(":inicio", $fechaInicio);
            $stmt->bindValue(":fin", $fechaFin);
            $stmt->execute();
            break;

        case 10:
            $sql = "SELECT ci.id_cita,
                           CONCAT(p.nombres, ' ', p.apellidos) AS paciente,
                           e.nombre_eps,
                           ci.fecha_cita, ci.estado
                    FROM cita ci
                    INNER JOIN paciente p ON ci.id_paciente = p.id_paciente
                    INNER JOIN eps e ON p.id_eps = e.id_eps
                    WHERE ci.estado = 'Atendida'
                      AND UPPER(e.nombre_eps) LIKE '%SALUD%'
                    ORDER BY ci.fecha_cita";
            $stmt = $conexion->prepare($sql);
            $stmt->execute();
            break;

        case 11:
            $sql = "SELECT ci.id_cita,
                           CONCAT(p.nombres, ' ', p.apellidos) AS paciente,
                           CONCAT(m.nombres, ' ', m.apellidos) AS medico,
                           e.nombre_especialidad,
                           ci.fecha_cita, ci.hora_cita, ci.estado
                    FROM cita ci
                    INNER JOIN paciente p ON ci.id_paciente = p.id_paciente
                    INNER JOIN medico m ON ci.id_medico = m.id_medico
                    INNER JOIN especialidad e
                        ON ci.id_especialidad = e.id_especialidad
                    WHERE ci.id_turno IS NULL
                      AND e.nombre_especialidad IN
                        ('Cardiologia', 'Pediatria', 'Dermatologia')
                    ORDER BY ci.fecha_cita";
            $stmt = $conexion->prepare($sql);
            $stmt->execute();
            break;

        case 12:
            $sql = "SELECT d.id_diagnostico,
                           CONCAT(p.nombres, ' ', p.apellidos) AS paciente,
                           CONCAT(m.nombres, ' ', m.apellidos) AS medico,
                           d.fecha_diagnostico,
                           d.descripcion, d.tratamiento
                    FROM diagnostico d
                    INNER JOIN historia_clinica h
                        ON d.id_historia = h.id_historia
                    INNER JOIN paciente p
                        ON h.id_paciente = p.id_paciente
                    INNER JOIN medico m
                        ON d.id_medico = m.id_medico
                    WHERE d.tratamiento IS NOT NULL
                      AND TRIM(d.tratamiento) <> ''
                    ORDER BY d.fecha_diagnostico DESC";
            $stmt = $conexion->prepare($sql);
            $stmt->execute();
            break;
    }

    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error al ejecutar la consulta: " . $e->getMessage();
}

$listaEps = $conexion->query(
    "SELECT id_eps, nombre_eps FROM eps ORDER BY nombre_eps"
)->fetchAll(PDO::FETCH_ASSOC);

function escapar($valor) {
    return htmlspecialchars((string)($valor ?? ""), ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultas SQL | Hospital Central</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/estilos.css">

    <style>
        .contenido-consultas {
            padding: 30px;
        }

        .tarjeta-consulta {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            height: 100%;
        }

        .tarjeta-consulta a {
            text-decoration: none;
            color: #334155;
            display: block;
        }

        .tarjeta-consulta.activa {
            border: 2px solid #2563eb;
            background: #eff6ff;
        }

        .tarjeta-consulta.activa a {
            color: #1d4ed8;
            font-weight: bold;
        }

        .tabla-resultados {
            background: white;
            border-radius: 12px;
            padding: 20px;
            overflow-x: auto;
        }

        .tabla-resultados table {
            font-size: 14px;
        }

        .filtros {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>

<div class="contenido-consultas">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Consultas SQL</h2>
            <p class="text-muted">
                Consultas académicas del sistema Hospital Central de Neiva
            </p>
        </div>

        <a href="../index.php" class="btn btn-outline-primary">
            Volver al panel
        </a>
    </div>

    <div class="row g-3 mb-4">

        <?php foreach ($consultas as $id => $nombre): ?>

            <div class="col-md-4 col-lg-3">
                <div class="tarjeta-consulta <?= $numero === $id ? 'activa' : '' ?>">
                    <a href="consultas.php?consulta=<?= $id ?>">
                        <small>CONSULTA <?= $id ?></small>
                        <div><?= escapar($nombre) ?></div>
                    </a>
                </div>
            </div>

        <?php endforeach; ?>

    </div>

    <div class="filtros">

        <h4>Consulta <?= $numero ?>: <?= escapar($titulo) ?></h4>

        <?php if ($numero === 5): ?>

            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="consulta" value="5">

                <div class="col-md-6">
                    <label class="form-label">Filtrar por EPS</label>

                    <select name="eps" class="form-select">
                        <option value="">Todas las EPS</option>

                        <?php foreach ($listaEps as $eps): ?>
                            <option value="<?= $eps["id_eps"] ?>"
                                <?= (string)$epsSeleccionada === (string)$eps["id_eps"] ? "selected" : "" ?>>
                                <?= escapar($eps["nombre_eps"]) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="col-md-3">
                    <button class="btn btn-primary w-100">
                        Buscar
                    </button>
                </div>
            </form>

        <?php elseif ($numero === 9): ?>

            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="consulta" value="9">

                <div class="col-md-4">
                    <label class="form-label">Fecha inicial</label>
                    <input type="date" name="fecha_inicio"
                           class="form-control"
                           value="<?= escapar($fechaInicio) ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Fecha final</label>
                    <input type="date" name="fecha_fin"
                           class="form-control"
                           value="<?= escapar($fechaFin) ?>" required>
                </div>

                <div class="col-md-3">
                    <button class="btn btn-primary w-100">
                        Consultar fechas
                    </button>
                </div>
            </form>

        <?php else: ?>

            <p class="text-muted mb-0">
                Resultados obtenidos directamente de la base de datos.
            </p>

        <?php endif; ?>

    </div>

    <div class="tabla-resultados">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5>Resultados encontrados</h5>

            <span class="badge bg-primary">
                <?= count($filas) ?> registros
            </span>
        </div>

        <?php if ($error !== ""): ?>

            <div class="alert alert-danger">
                <?= escapar($error) ?>
            </div>

        <?php elseif (empty($filas)): ?>

            <div class="alert alert-info">
                No se encontraron registros para esta consulta.
            </div>

        <?php else: ?>

            <table class="table table-striped table-hover align-middle">

                <thead class="table-dark">
                    <tr>
                        <?php foreach (array_keys($filas[0]) as $columna): ?>
                            <th><?= escapar(ucwords(str_replace("_", " ", $columna))) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($filas as $fila): ?>
                        <tr>
                            <?php foreach ($fila as $valor): ?>
                                <td><?= escapar($valor) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
