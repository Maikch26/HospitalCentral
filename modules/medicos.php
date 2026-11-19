<?php
require_once "../config/conexion.php";

$mensaje = "";
$tipo_mensaje = "";

$medico = [
    "id_medico" => "",
    "tipo_documento" => "CC",
    "documento" => "",
    "nombres" => "",
    "apellidos" => "",
    "registro_medico" => "",
    "telefono" => "",
    "correo" => "",
    "fecha_contratacion" => "",
    "estado" => "Activo"
];

$especialidades_seleccionadas = [];
$editar = false;

/* =========================================================
   DETECTAR TABLA MÉDICO
========================================================= */

$tablas = $conexion->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

if (in_array("médico", $tablas, true)) {
    $tablaMedico = "médico";
} elseif (in_array("medico", $tablas, true)) {
    $tablaMedico = "medico";
} else {
    die("No se encontró la tabla de médicos.");
}

/* =========================================================
   DETECTAR COLUMNAS
========================================================= */

$columnas = $conexion->query(
    "SHOW COLUMNS FROM `$tablaMedico`"
)->fetchAll(PDO::FETCH_COLUMN);

$colRegistro = in_array("registro_médico", $columnas, true)
    ? "registro_médico"
    : "registro_medico";

$colTelefono = in_array("teléfono", $columnas, true)
    ? "teléfono"
    : "telefono";

$colFecha = in_array("fecha_contratación", $columnas, true)
    ? "fecha_contratación"
    : "fecha_contratacion";

/* =========================================================
   CONSULTAR ESPECIALIDADES
========================================================= */

$stmt = $conexion->query(
    "SELECT id_especialidad, nombre_especialidad
     FROM especialidad
     ORDER BY nombre_especialidad"
);

$especialidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   PROCESAR FORMULARIO
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"] ?? "guardar";

    /* =====================================================
       ELIMINAR MÉDICO
    ===================================================== */

    if ($accion === "eliminar") {

        $idEliminar = (int)($_POST["id_medico"] ?? 0);

        try {

            $conexion->beginTransaction();

            $stmt = $conexion->prepare(
                "DELETE FROM medico_especialidad
                 WHERE id_medico = ?"
            );

            $stmt->execute([$idEliminar]);

            $stmt = $conexion->prepare(
                "DELETE FROM `$tablaMedico`
                 WHERE id_medico = ?"
            );

            $stmt->execute([$idEliminar]);

            $conexion->commit();

            $mensaje = "Médico eliminado correctamente.";
            $tipo_mensaje = "success";

        } catch (PDOException $e) {

            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            $mensaje = "No se pudo eliminar el médico porque tiene registros relacionados.";
            $tipo_mensaje = "danger";
        }

    } else {

        /* =================================================
           OBTENER DATOS
        ================================================= */

        $id = $_POST["id_medico"] ?? "";

        $tipo_documento = $_POST["tipo_documento"] ?? "CC";
        $documento = trim($_POST["documento"] ?? "");
        $nombres = trim($_POST["nombres"] ?? "");
        $apellidos = trim($_POST["apellidos"] ?? "");
        $registro_medico = trim($_POST["registro_medico"] ?? "");
        $telefono = trim($_POST["telefono"] ?? "");
        $correo = trim($_POST["correo"] ?? "");
        $fecha_contratacion = $_POST["fecha_contratacion"] ?? "";
        $estado = $_POST["estado"] ?? "Activo";

        $especialidades_seleccionadas =
            $_POST["especialidades"] ?? [];

        $medico = [
            "id_medico" => $id,
            "tipo_documento" => $tipo_documento,
            "documento" => $documento,
            "nombres" => $nombres,
            "apellidos" => $apellidos,
            "registro_medico" => $registro_medico,
            "telefono" => $telefono,
            "correo" => $correo,
            "fecha_contratacion" => $fecha_contratacion,
            "estado" => $estado
        ];

        $editar = ($id !== "");

        /* =================================================
           VALIDACIONES
        ================================================= */

        $tipos_validos = ["CC", "CE", "PA"];
        $estados_validos = ["Activo", "Inactivo"];

        if (
            $documento === "" ||
            $nombres === "" ||
            $apellidos === "" ||
            $registro_medico === ""
        ) {

            $mensaje = "Completa todos los campos obligatorios.";
            $tipo_mensaje = "danger";

        } elseif (!in_array($tipo_documento, $tipos_validos, true)) {

            $mensaje = "El tipo de documento no es válido.";
            $tipo_mensaje = "danger";

        } elseif (!in_array($estado, $estados_validos, true)) {

            $mensaje = "El estado seleccionado no es válido.";
            $tipo_mensaje = "danger";

        } elseif (
            $correo !== "" &&
            !filter_var($correo, FILTER_VALIDATE_EMAIL)
        ) {

            $mensaje = "El correo electrónico no es válido.";
            $tipo_mensaje = "danger";

        } elseif (empty($especialidades_seleccionadas)) {

            $mensaje = "Selecciona al menos una especialidad.";
            $tipo_mensaje = "danger";

        } else {

            try {

                $conexion->beginTransaction();

                /* =========================================
                   ACTUALIZAR MÉDICO
                ========================================= */

                if ($id !== "") {

                    $sql = "UPDATE `$tablaMedico`
                            SET tipo_documento = ?,
                                documento = ?,
                                nombres = ?,
                                apellidos = ?,
                                `$colRegistro` = ?,
                                `$colTelefono` = ?,
                                correo = ?,
                                `$colFecha` = ?,
                                estado = ?
                            WHERE id_medico = ?";

                    $stmt = $conexion->prepare($sql);

                    $stmt->execute([
                        $tipo_documento,
                        $documento,
                        $nombres,
                        $apellidos,
                        $registro_medico,
                        $telefono !== "" ? $telefono : null,
                        $correo !== "" ? $correo : null,
                        $fecha_contratacion !== "" ? $fecha_contratacion : null,
                        $estado,
                        $id
                    ]);

                    $idMedico = (int)$id;

                    /* Eliminar especialidades anteriores */

                    $stmt = $conexion->prepare(
                        "DELETE FROM medico_especialidad
                         WHERE id_medico = ?"
                    );

                    $stmt->execute([$idMedico]);

                    $mensaje = "Médico actualizado correctamente.";

                } else {

                    /* =====================================
                       REGISTRAR MÉDICO
                    ===================================== */

                    $sql = "INSERT INTO `$tablaMedico`
                            (
                                tipo_documento,
                                documento,
                                nombres,
                                apellidos,
                                `$colRegistro`,
                                `$colTelefono`,
                                correo,
                                `$colFecha`,
                                estado
                            )
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

                    $stmt = $conexion->prepare($sql);

                    $stmt->execute([
                        $tipo_documento,
                        $documento,
                        $nombres,
                        $apellidos,
                        $registro_medico,
                        $telefono !== "" ? $telefono : null,
                        $correo !== "" ? $correo : null,
                        $fecha_contratacion !== "" ? $fecha_contratacion : null,
                        $estado
                    ]);

                    $idMedico = (int)$conexion->lastInsertId();

                    $mensaje = "Médico registrado correctamente.";
                }

                /* =========================================
                   ASIGNAR ESPECIALIDADES
                ========================================= */

                $stmtEspecialidad = $conexion->prepare(
                    "INSERT INTO medico_especialidad
                     (id_medico, id_especialidad)
                     VALUES (?, ?)"
                );

                foreach ($especialidades_seleccionadas as $idEspecialidad) {

                    $idEspecialidad = (int)$idEspecialidad;

                    if ($idEspecialidad > 0) {

                        $stmtEspecialidad->execute([
                            $idMedico,
                            $idEspecialidad
                        ]);
                    }
                }

                $conexion->commit();

                $tipo_mensaje = "success";

                /* Limpiar formulario */

                $medico = [
                    "id_medico" => "",
                    "tipo_documento" => "CC",
                    "documento" => "",
                    "nombres" => "",
                    "apellidos" => "",
                    "registro_medico" => "",
                    "telefono" => "",
                    "correo" => "",
                    "fecha_contratacion" => "",
                    "estado" => "Activo"
                ];

                $especialidades_seleccionadas = [];
                $editar = false;

            } catch (PDOException $e) {

                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }

                if ($e->getCode() == "23000") {

                    $mensaje = "El documento o registro médico ya existe, o alguna especialidad no es válida.";

                } else {

                    $mensaje = "Error al guardar: " . $e->getMessage();
                }

                $tipo_mensaje = "danger";
            }
        }
    }
}

/* =========================================================
   CONSULTAR MÉDICO PARA EDITAR
========================================================= */

if (isset($_GET["editar"])) {

    $id = (int)$_GET["editar"];

    $stmt = $conexion->prepare(
        "SELECT *
         FROM `$tablaMedico`
         WHERE id_medico = ?"
    );

    $stmt->execute([$id]);

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resultado) {

        $medico = [
            "id_medico" => $resultado["id_medico"],
            "tipo_documento" => $resultado["tipo_documento"],
            "documento" => $resultado["documento"],
            "nombres" => $resultado["nombres"],
            "apellidos" => $resultado["apellidos"],
            "registro_medico" => $resultado[$colRegistro],
            "telefono" => $resultado[$colTelefono],
            "correo" => $resultado["correo"],
            "fecha_contratacion" => $resultado[$colFecha],
            "estado" => $resultado["estado"]
        ];

        $stmt = $conexion->prepare(
            "SELECT id_especialidad
             FROM medico_especialidad
             WHERE id_medico = ?"
        );

        $stmt->execute([$id]);

        $especialidades_seleccionadas =
            $stmt->fetchAll(PDO::FETCH_COLUMN);

        $editar = true;
    }
}

/* =========================================================
   BUSCADOR Y LISTADO
========================================================= */

$busqueda = trim($_GET["buscar"] ?? "");

$sql = "SELECT
            m.id_medico,
            m.tipo_documento,
            m.documento,
            m.nombres,
            m.apellidos,
            m.`$colRegistro` AS registro_medico,
            m.`$colTelefono` AS telefono,
            m.correo,
            m.`$colFecha` AS fecha_contratacion,
            m.estado,

            GROUP_CONCAT(
                DISTINCT e.nombre_especialidad
                ORDER BY e.nombre_especialidad
                SEPARATOR ', '
            ) AS especialidades

        FROM `$tablaMedico` m

        LEFT JOIN medico_especialidad me
            ON m.id_medico = me.id_medico

        LEFT JOIN especialidad e
            ON me.id_especialidad = e.id_especialidad";

if ($busqueda !== "") {

    $sql .= " WHERE
                m.documento LIKE :buscar
                OR m.nombres LIKE :buscar
                OR m.apellidos LIKE :buscar
                OR m.`$colRegistro` LIKE :buscar
                OR m.`$colTelefono` LIKE :buscar
                OR e.nombre_especialidad LIKE :buscar";
}

$sql .= " GROUP BY
            m.id_medico,
            m.tipo_documento,
            m.documento,
            m.nombres,
            m.apellidos,
            m.`$colRegistro`,
            m.`$colTelefono`,
            m.correo,
            m.`$colFecha`,
            m.estado

          ORDER BY m.apellidos ASC, m.nombres ASC";

$stmt = $conexion->prepare($sql);

if ($busqueda !== "") {
    $stmt->bindValue(":buscar", "%" . $busqueda . "%");
}

$stmt->execute();

$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   ESTADÍSTICAS
========================================================= */

$total = $conexion->query(
    "SELECT COUNT(*) FROM `$tablaMedico`"
)->fetchColumn();

$activos = $conexion->query(
    "SELECT COUNT(*)
     FROM `$tablaMedico`
     WHERE estado = 'Activo'"
)->fetchColumn();

$inactivos = $conexion->query(
    "SELECT COUNT(*)
     FROM `$tablaMedico`
     WHERE estado = 'Inactivo'"
)->fetchColumn();

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Médicos | Hospital Central de Neiva</title>

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
            border: 1px solid #123b59;
        }

        .btn-hospital:hover {
            background: #0b293e;
            color: white;
            border-color: #0b293e;
        }

        .tabla-contenedor {
            overflow-x: auto;
        }

        .form-label {
            font-weight: 600;
        }

        .estadistica {
            color: #123b59;
            font-weight: bold;
        }

        .especialidades-select {
            min-height: 120px;
        }

        .acciones {
            white-space: nowrap;
        }

        .alerta-hospital {
            background: #eaf1f7;
            color: #123b59;
            border: 1px solid #cbd9e6;
            border-radius: 8px;
        }

    </style>

</head>

<body>

<div class="container-fluid p-4">

    <!-- ENCABEZADO -->

    <div class="encabezado">

        <h2>Administración de médicos</h2>

        <p class="mb-0">
            Hospital Central de Neiva - Gestión del personal médico
        </p>

    </div>

    <!-- VOLVER AL PANEL -->

    <div class="mb-3">

        <a href="../index.php"
           class="btn btn-outline-secondary">

            ← Volver al panel principal

        </a>

    </div>

    <!-- MENSAJES -->

    <?php if ($mensaje !== ""): ?>

        <div class="alert alerta-hospital alert-dismissible fade show">

            <?php echo htmlspecialchars($mensaje); ?>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">

            </button>

        </div>

    <?php endif; ?>

    <!-- ESTADÍSTICAS -->

    <div class="row g-3 mb-4">

        <div class="col-md-4">

            <div class="card tarjeta p-3">

                <h6 class="text-muted">
                    Total de médicos
                </h6>

                <h2 class="estadistica">
                    <?php echo $total; ?>
                </h2>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card tarjeta p-3">

                <h6 class="text-muted">
                    Médicos activos
                </h6>

                <h2 class="estadistica">
                    <?php echo $activos; ?>
                </h2>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card tarjeta p-3">

                <h6 class="text-muted">
                    Médicos inactivos
                </h6>

                <h2 class="estadistica">
                    <?php echo $inactivos; ?>
                </h2>

            </div>

        </div>

    </div>

    <div class="row g-4">

        <!-- FORMULARIO -->

        <div class="col-lg-4">

            <div class="card tarjeta p-4">

                <h4 class="titulo mb-3">

                    <?php echo $editar
                        ? "Modificar médico"
                        : "Registrar médico"; ?>

                </h4>

                <form method="POST"
                      action="medicos.php">

                    <input type="hidden"
                           name="id_medico"
                           value="<?php echo htmlspecialchars($medico["id_medico"]); ?>">

                    <!-- TIPO DE DOCUMENTO -->

                    <div class="mb-3">

                        <label class="form-label">
                            Tipo de documento *
                        </label>

                        <select name="tipo_documento"
                                class="form-select"
                                required>

                            <option value="CC"
                                <?php echo $medico["tipo_documento"] === "CC" ? "selected" : ""; ?>>
                                Cédula de ciudadanía
                            </option>

                            <option value="CE"
                                <?php echo $medico["tipo_documento"] === "CE" ? "selected" : ""; ?>>
                                Cédula de extranjería
                            </option>

                            <option value="PA"
                                <?php echo $medico["tipo_documento"] === "PA" ? "selected" : ""; ?>>
                                Pasaporte
                            </option>

                        </select>

                    </div>

                    <!-- DOCUMENTO -->

                    <div class="mb-3">

                        <label class="form-label">
                            Número de documento *
                        </label>

                        <input type="text"
                               name="documento"
                               class="form-control"
                               required
                               maxlength="25"
                               value="<?php echo htmlspecialchars($medico["documento"]); ?>"
                               placeholder="Número de identificación">

                    </div>

                    <!-- NOMBRES -->

                    <div class="mb-3">

                        <label class="form-label">
                            Nombres *
                        </label>

                        <input type="text"
                               name="nombres"
                               class="form-control"
                               required
                               maxlength="100"
                               value="<?php echo htmlspecialchars($medico["nombres"]); ?>"
                               placeholder="Nombres del médico">

                    </div>

                    <!-- APELLIDOS -->

                    <div class="mb-3">

                        <label class="form-label">
                            Apellidos *
                        </label>

                        <input type="text"
                               name="apellidos"
                               class="form-control"
                               required
                               maxlength="100"
                               value="<?php echo htmlspecialchars($medico["apellidos"]); ?>"
                               placeholder="Apellidos del médico">

                    </div>

                    <!-- REGISTRO MÉDICO -->

                    <div class="mb-3">

                        <label class="form-label">
                            Registro médico *
                        </label>

                        <input type="text"
                               name="registro_medico"
                               class="form-control"
                               required
                               maxlength="40"
                               value="<?php echo htmlspecialchars($medico["registro_medico"]); ?>"
                               placeholder="Número de registro profesional">

                    </div>

                    <!-- ESPECIALIDADES -->

                    <div class="mb-3">

                        <label class="form-label">
                            Especialidades *
                        </label>

                        <select name="especialidades[]"
                                class="form-select especialidades-select"
                                multiple
                                required>

                            <?php foreach ($especialidades as $esp): ?>

                                <option value="<?php echo $esp["id_especialidad"]; ?>"
                                    <?php
                                    echo in_array(
                                        (string)$esp["id_especialidad"],
                                        array_map(
                                            "strval",
                                            $especialidades_seleccionadas
                                        ),
                                        true
                                    ) ? "selected" : "";
                                    ?>>

                                    <?php echo htmlspecialchars($esp["nombre_especialidad"]); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <small class="text-muted">

                            Mantén presionada la tecla Ctrl para seleccionar varias especialidades.

                        </small>

                    </div>

                    <!-- TELÉFONO -->

                    <div class="mb-3">

                        <label class="form-label">
                            Teléfono
                        </label>

                        <input type="text"
                               name="telefono"
                               class="form-control"
                               maxlength="20"
                               value="<?php echo htmlspecialchars($medico["telefono"] ?? ""); ?>"
                               placeholder="Número de contacto">

                    </div>

                    <!-- CORREO -->

                    <div class="mb-3">

                        <label class="form-label">
                            Correo electrónico
                        </label>

                        <input type="email"
                               name="correo"
                               class="form-control"
                               maxlength="120"
                               value="<?php echo htmlspecialchars($medico["correo"] ?? ""); ?>"
                               placeholder="correo@hospital.com">

                    </div>

                    <!-- FECHA CONTRATACIÓN -->

                    <div class="mb-3">

                        <label class="form-label">
                            Fecha de contratación
                        </label>

                        <input type="date"
                               name="fecha_contratacion"
                               class="form-control"
                               value="<?php echo htmlspecialchars($medico["fecha_contratacion"] ?? ""); ?>">

                    </div>

                    <!-- ESTADO -->

                    <div class="mb-3">

                        <label class="form-label">
                            Estado
                        </label>

                        <select name="estado"
                                class="form-select">

                            <option value="Activo"
                                <?php echo $medico["estado"] === "Activo" ? "selected" : ""; ?>>
                                Activo
                            </option>

                            <option value="Inactivo"
                                <?php echo $medico["estado"] === "Inactivo" ? "selected" : ""; ?>>
                                Inactivo
                            </option>

                        </select>

                    </div>

                    <!-- BOTÓN GUARDAR -->

                    <button type="submit"
                            name="accion"
                            value="guardar"
                            class="btn btn-hospital w-100">

                        <?php echo $editar
                            ? "Guardar cambios"
                            : "Registrar médico"; ?>

                    </button>

                    <!-- CANCELAR EDICIÓN -->

                    <?php if ($editar): ?>

                        <a href="medicos.php"
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

        <!-- LISTADO DE MÉDICOS -->

        <div class="col-lg-8">

            <div class="card tarjeta p-4">

                <h4 class="titulo mb-3">
                    Médicos registrados
                </h4>

                <!-- BUSCADOR -->

                <form method="GET"
                      class="row g-2 mb-3">

                    <div class="col-md-9">

                        <input type="text"
                               name="buscar"
                               class="form-control"
                               placeholder="Buscar por nombre, documento, registro o especialidad..."
                               value="<?php echo htmlspecialchars($busqueda); ?>">

                    </div>

                    <div class="col-md-3 d-grid">

                        <button type="submit"
                                class="btn btn-hospital">

                            Buscar

                        </button>

                    </div>

                </form>

                <!-- TABLA -->

                <div class="tabla-contenedor">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th>Médico</th>

                                <th>Documento</th>

                                <th>Registro médico</th>

                                <th>Especialidades</th>

                                <th>Estado</th>

                                <th>Acciones</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if (count($lista) > 0): ?>

                            <?php foreach ($lista as $fila): ?>

                                <tr>

                                    <!-- MÉDICO -->

                                    <td>

                                        <strong>

                                            <?php echo htmlspecialchars(
                                                $fila["nombres"] . " " . $fila["apellidos"]
                                            ); ?>

                                        </strong>

                                        <br>

                                        <small class="text-muted">

                                            <?php echo htmlspecialchars($fila["telefono"] ?? ""); ?>

                                        </small>

                                    </td>

                                    <!-- DOCUMENTO -->

                                    <td>

                                        <?php echo htmlspecialchars($fila["tipo_documento"]); ?>

                                        <br>

                                        <small class="text-muted">

                                            <?php echo htmlspecialchars($fila["documento"]); ?>

                                        </small>

                                    </td>

                                    <!-- REGISTRO -->

                                    <td>

                                        <?php echo htmlspecialchars($fila["registro_medico"]); ?>

                                    </td>

                                    <!-- ESPECIALIDADES -->

                                    <td>

                                        <?php echo htmlspecialchars(
                                            $fila["especialidades"] ?? "Sin especialidad"
                                        ); ?>

                                    </td>

                                    <!-- ESTADO -->

                                    <td>

                                        <?php if ($fila["estado"] === "Activo"): ?>

                                            <span class="badge bg-primary">
                                                Activo
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-secondary">
                                                Inactivo
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <!-- ACCIONES -->

                                    <td class="acciones">

                                        <a href="medicos.php?editar=<?php echo $fila["id_medico"]; ?>"
                                           class="btn btn-sm btn-outline-primary">

                                            Editar

                                        </a>

                                        <form method="POST"
                                              action="medicos.php"
                                              class="d-inline"
                                              onsubmit="return confirm('¿Estás seguro de que deseas eliminar este médico?');">

                                            <input type="hidden"
                                                   name="id_medico"
                                                   value="<?php echo $fila["id_medico"]; ?>">

                                            <button type="submit"
                                                    name="accion"
                                                    value="eliminar"
                                                    class="btn btn-sm btn-outline-secondary">

                                                Eliminar

                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="6"
                                    class="text-center text-muted py-4">

                                    No se encontraron médicos registrados.

                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

                <!-- RESULTADOS -->

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