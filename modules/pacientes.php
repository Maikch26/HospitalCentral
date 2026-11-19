
<?php
date_default_timezone_set("America/Bogota");
require_once "../config/conexion.php";

$mensaje = "";
$tipoMensaje = "";
$pacienteEditar = null;

// Obtener las EPS activas
$eps = $conexion->query(
    "SELECT id_eps, nombre_eps
     FROM eps
     WHERE estado = 'Activa'
     ORDER BY nombre_eps"
)->fetchAll();

// Guardar o actualizar paciente
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar"])) {
    try {
        $id = (int)($_POST["id_paciente"] ?? 0);
        $idEps = (int)($_POST["id_eps"] ?? 0);
        $tipoDocumento = $_POST["tipo_documento"] ?? "";
        $documento = trim($_POST["documento"] ?? "");
        $nombres = trim($_POST["nombres"] ?? "");
        $apellidos = trim($_POST["apellidos"] ?? "");
        $fechaNacimiento = $_POST["fecha_nacimiento"] ?? "";
        $genero = $_POST["genero"] ?? "";
        $telefono = trim($_POST["telefono"] ?? "");
        $correo = trim($_POST["correo"] ?? "");
        $direccion = trim($_POST["direccion"] ?? "");

        $tiposValidos = ["CC", "TI", "CE", "PA", "RC"];
        $generosValidos = ["Masculino", "Femenino", "Otro"];

        if (
            $idEps <= 0 ||
            !in_array($tipoDocumento, $tiposValidos, true) ||
            !in_array($genero, $generosValidos, true) ||
            $documento === "" ||
            $nombres === "" ||
            $apellidos === "" ||
            $fechaNacimiento === ""
        ) {
            throw new Exception("Completa correctamente los campos obligatorios.");
        }

        if ($correo !== "" && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El correo electrónico no tiene un formato válido.");
        }

        if ($id > 0) {
            $sql = "UPDATE paciente SET
                        id_eps = ?,
                        tipo_documento = ?,
                        documento = ?,
                        nombres = ?,
                        apellidos = ?,
                        fecha_nacimiento = ?,
                        genero = ?,
                        telefono = ?,
                        correo = ?,
                        direccion = ?
                    WHERE id_paciente = ?";

            $consulta = $conexion->prepare($sql);
            $consulta->execute([
                $idEps, $tipoDocumento, $documento,
                $nombres, $apellidos, $fechaNacimiento,
                $genero, $telefono ?: null,
                $correo ?: null, $direccion ?: null, $id
            ]);

            $mensaje = "Paciente actualizado correctamente.";
        } else {
            $sql = "INSERT INTO paciente (
                        id_eps, tipo_documento, documento,
                        nombres, apellidos, fecha_nacimiento,
                        genero, telefono, correo, direccion
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $consulta = $conexion->prepare($sql);
            $consulta->execute([
                $idEps, $tipoDocumento, $documento,
                $nombres, $apellidos, $fechaNacimiento,
                $genero, $telefono ?: null,
                $correo ?: null, $direccion ?: null
            ]);

            $mensaje = "Paciente registrado correctamente. Su historia clínica fue creada automáticamente.";
        }

        $tipoMensaje = "exito";

    } catch (Exception $error) {
        $mensaje = $error->getMessage();
        $tipoMensaje = "error";

        if ($error instanceof PDOException && $error->getCode() == "23000") {
            $mensaje = "El documento ya está registrado o existe un problema con la EPS seleccionada.";
        }
    }
}

// Consultar paciente para editar
if (isset($_GET["editar"])) {
    $consulta = $conexion->prepare(
        "SELECT * FROM paciente WHERE id_paciente = ?"
    );
    $consulta->execute([(int)$_GET["editar"]]);
    $pacienteEditar = $consulta->fetch();

    if (!$pacienteEditar) {
        $mensaje = "No se encontró el paciente solicitado.";
        $tipoMensaje = "error";
    }
}

// Buscar pacientes
$busqueda = trim($_GET["buscar"] ?? "");

$sqlPacientes = "
    SELECT
        p.*,
        e.nombre_eps,
        h.numero_historia
    FROM paciente p
    INNER JOIN eps e ON p.id_eps = e.id_eps
    LEFT JOIN historia_clinica h ON p.id_paciente = h.id_paciente
";

if ($busqueda !== "") {
    $sqlPacientes .= "
        WHERE p.nombres LIKE ?
        OR p.apellidos LIKE ?
        OR p.documento LIKE ?
    ";
}

$sqlPacientes .= " ORDER BY p.id_paciente DESC";

$consulta = $conexion->prepare($sqlPacientes);

if ($busqueda !== "") {
    $termino = "%" . $busqueda . "%";
    $consulta->execute([$termino, $termino, $termino]);
} else {
    $consulta->execute();
}

$pacientes = $consulta->fetchAll();

function limpiar($dato) {
    return htmlspecialchars((string)$dato, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pacientes | Hospital Central de Neiva</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f3f6fa;
            color: #253247;
            padding: 30px;
        }

        .contenedor {
            max-width: 1250px;
            margin: auto;
        }

        .encabezado {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 28px;
        }

        .encabezado h1 {
            font-size: 28px;
            color: #172b4d;
            margin: 8px 0;
        }

        .subtitulo {
            color: #168c91;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .encabezado p {
            color: #718096;
        }

        .boton {
            display: inline-block;
            border: none;
            border-radius: 8px;
            padding: 11px 17px;
            background: #168c91;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .boton:hover {
            background: #11767b;
        }

        .boton-secundario {
            background: #e8edf4;
            color: #34445c;
        }

        .panel {
            background: white;
            border-radius: 13px;
            padding: 25px;
            margin-bottom: 24px;
            box-shadow: 0 3px 15px rgba(30, 50, 80, .05);
        }

        .panel h2 {
            color: #172b4d;
            font-size: 19px;
            margin-bottom: 20px;
        }

        .formulario {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 17px;
        }

        .campo {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .campo label {
            font-size: 13px;
            font-weight: bold;
            color: #46566e;
        }

        .campo input,
        .campo select {
            width: 100%;
            padding: 11px;
            border: 1px solid #dce3ec;
            border-radius: 7px;
            outline: none;
            background: white;
            color: #253247;
        }

        .campo input:focus,
        .campo select:focus {
            border-color: #168c91;
        }

        .campo-completo {
            grid-column: 1 / -1;
        }

        .acciones {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .mensaje {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .exito {
            background: #e5f7ef;
            color: #13754d;
        }

        .error {
            background: #fdebec;
            color: #a52b37;
        }

        .busqueda {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .busqueda input {
            flex: 1;
            padding: 11px;
            border: 1px solid #dce3ec;
            border-radius: 7px;
        }

        .tabla-contenedor {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th {
            background: #f2f5f9;
            color: #52627a;
            font-size: 12px;
            text-align: left;
            padding: 14px;
        }

        td {
            padding: 14px;
            border-bottom: 1px solid #edf0f4;
            font-size: 13px;
        }

        .editar {
            color: #168c91;
            text-decoration: none;
            font-weight: bold;
        }

        .total {
            color: #718096;
            font-size: 13px;
            margin-bottom: 14px;
        }

        footer {
            text-align: center;
            color: #8793a5;
            font-size: 12px;
            padding: 20px;
        }

        @media(max-width: 800px) {
            body {
                padding: 15px;
            }

            .formulario {
                grid-template-columns: 1fr 1fr;
            }

            .encabezado {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media(max-width: 520px) {
            .formulario {
                grid-template-columns: 1fr;
            }

            .campo-completo {
                grid-column: auto;
            }
        }
    </style>
</head>

<body>

<div class="contenedor">

    <header class="encabezado">
        <div>
            <span class="subtitulo">GESTIÓN HOSPITALARIA</span>
            <h1>Gestión de pacientes</h1>
            <p>Registro y administración de pacientes del hospital.</p>
        </div>

        <a href="../index.php" class="boton boton-secundario">
            ← Volver al panel
        </a>
    </header>

    <?php if ($mensaje !== ""): ?>
        <div class="mensaje <?php echo $tipoMensaje; ?>">
            <?php echo limpiar($mensaje); ?>
        </div>
    <?php endif; ?>

    <section class="panel">
        <h2>
            <?php echo $pacienteEditar ? "Editar paciente" : "Registrar nuevo paciente"; ?>
        </h2>

        <form method="POST" class="formulario">

            <input type="hidden" name="id_paciente"
                   value="<?php echo limpiar($pacienteEditar["id_paciente"] ?? ""); ?>">

            <div class="campo">
                <label>Tipo de documento *</label>
                <select name="tipo_documento" required>
                    <option value="">Seleccione</option>
                    <?php foreach (["CC", "TI", "CE", "PA", "RC"] as $tipo): ?>
                        <option value="<?php echo $tipo; ?>"
                            <?php echo (($pacienteEditar["tipo_documento"] ?? "") === $tipo) ? "selected" : ""; ?>>
                            <?php echo $tipo; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label>Número de documento *</label>
                <input type="text" name="documento" required
                    value="<?php echo limpiar($pacienteEditar["documento"] ?? ""); ?>">
            </div>

            <div class="campo">
                <label>EPS *</label>
                <select name="id_eps" required>
                    <option value="">Seleccione una EPS</option>
                    <?php foreach ($eps as $entidad): ?>
                        <option value="<?php echo $entidad["id_eps"]; ?>"
                            <?php echo ((int)($pacienteEditar["id_eps"] ?? 0) === (int)$entidad["id_eps"]) ? "selected" : ""; ?>>
                            <?php echo limpiar($entidad["nombre_eps"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label>Nombres *</label>
                <input type="text" name="nombres" required
                    value="<?php echo limpiar($pacienteEditar["nombres"] ?? ""); ?>">
            </div>

            <div class="campo">
                <label>Apellidos *</label>
                <input type="text" name="apellidos" required
                    value="<?php echo limpiar($pacienteEditar["apellidos"] ?? ""); ?>">
            </div>

            <div class="campo">
                <label>Fecha de nacimiento *</label>
                <input type="date" name="fecha_nacimiento" required
                    value="<?php echo limpiar($pacienteEditar["fecha_nacimiento"] ?? ""); ?>">
            </div>

            <div class="campo">
                <label>Género *</label>
                <select name="genero" required>
                    <option value="">Seleccione</option>
                    <?php foreach (["Masculino", "Femenino", "Otro"] as $genero): ?>
                        <option value="<?php echo $genero; ?>"
                            <?php echo (($pacienteEditar["genero"] ?? "") === $genero) ? "selected" : ""; ?>>
                            <?php echo $genero; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label>Teléfono</label>
                <input type="text" name="telefono"
                    value="<?php echo limpiar($pacienteEditar["telefono"] ?? ""); ?>">
            </div>

            <div class="campo">
                <label>Correo electrónico</label>
                <input type="email" name="correo"
                    value="<?php echo limpiar($pacienteEditar["correo"] ?? ""); ?>">
            </div>

            <div class="campo campo-completo">
                <label>Dirección</label>
                <input type="text" name="direccion"
                    value="<?php echo limpiar($pacienteEditar["direccion"] ?? ""); ?>">
            </div>

            <div class="campo-completo acciones">
                <button type="submit" name="guardar" class="boton">
                    <?php echo $pacienteEditar ? "Guardar cambios" : "Registrar paciente"; ?>
                </button>

                <?php if ($pacienteEditar): ?>
                    <a href="pacientes.php" class="boton boton-secundario">
                        Cancelar edición
                    </a>
                <?php endif; ?>
            </div>

        </form>
    </section>

    <section class="panel">
        <h2>Pacientes registrados</h2>

        <form method="GET" class="busqueda">
            <input type="text" name="buscar"
                placeholder="Buscar por nombre, apellido o documento..."
                value="<?php echo limpiar($busqueda); ?>">

            <button type="submit" class="boton">Buscar</button>
            <a href="pacientes.php" class="boton boton-secundario">Limpiar</a>
        </form>

        <p class="total">
            Total de pacientes encontrados: <?php echo count($pacientes); ?>
        </p>

        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Paciente</th>
                        <th>EPS</th>
                        <th>Historia clínica</th>
                        <th>Teléfono</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($pacientes) > 0): ?>
                        <?php foreach ($pacientes as $paciente): ?>
                            <tr>
                                <td>
                                    <?php echo limpiar($paciente["tipo_documento"] . " " . $paciente["documento"]); ?>
                                </td>

                                <td>
                                    <strong>
                                        <?php echo limpiar($paciente["nombres"] . " " . $paciente["apellidos"]); ?>
                                    </strong>
                                </td>

                                <td><?php echo limpiar($paciente["nombre_eps"]); ?></td>

                                <td><?php echo limpiar($paciente["numero_historia"] ?? "Sin historia"); ?></td>

                                <td><?php echo limpiar($paciente["telefono"] ?? "No registrado"); ?></td>

                                <td>
                                    <a class="editar"
                                       href="pacientes.php?editar=<?php echo (int)$paciente["id_paciente"]; ?>">
                                        Editar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No se encontraron pacientes.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <footer>
        Hospital Central de Neiva · Sistema de Gestión Hospitalaria
    </footer>

</div>

</body>
</html>
