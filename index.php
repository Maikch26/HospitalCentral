<?php
date_default_timezone_set("America/Bogota");
require_once "config/conexion.php";

// Estadísticas generales
$totalPacientes = (int) $conexion->query("SELECT COUNT(*) FROM paciente")->fetchColumn();
$totalMedicos = (int) $conexion->query("SELECT COUNT(*) FROM medico")->fetchColumn();
$totalCitas = (int) $conexion->query("SELECT COUNT(*) FROM cita")->fetchColumn();
$totalConsultorios = (int) $conexion->query("SELECT COUNT(*) FROM consultorio")->fetchColumn();

// Próximas citas pendientes o confirmadas
$sqlCitas = "
    SELECT
        c.fecha_cita,
        c.hora_cita,
        c.estado,
        p.nombres AS paciente_nombre,
        p.apellidos AS paciente_apellido,
        m.nombres AS medico_nombre,
        m.apellidos AS medico_apellido,
        e.nombre_especialidad
    FROM cita c
    INNER JOIN paciente p ON c.id_paciente = p.id_paciente
    INNER JOIN medico m ON c.id_medico = m.id_medico
    INNER JOIN especialidad e ON c.id_especialidad = e.id_especialidad
    WHERE TIMESTAMP(c.fecha_cita, c.hora_cita) >= NOW()
      AND c.estado IN ('Programada', 'Confirmada')
    ORDER BY c.fecha_cita ASC, c.hora_cita ASC
    LIMIT 6
";
$citas = $conexion->query($sqlCitas)->fetchAll(PDO::FETCH_ASSOC);

// Citas agrupadas por estado para la gráfica
$sqlEstados = "
    SELECT estado, COUNT(*) AS cantidad
    FROM cita
    GROUP BY estado
    ORDER BY estado
";
$estados = $conexion->query($sqlEstados)->fetchAll(PDO::FETCH_ASSOC);

$nombresEstados = [];
$cantidadesEstados = [];

foreach ($estados as $estado) {
    $nombresEstados[] = $estado["estado"];
    $cantidadesEstados[] = (int) $estado["cantidad"];
}

function limpiar($dato) {
    return htmlspecialchars((string)($dato ?? ""), ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel principal | Hospital Central de Neiva</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/estilos.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-shell">

    <aside class="sidebar">
        <a href="index.php" class="brand">
            <span class="brand-mark">+</span>
            <span class="brand-copy">
                <strong>Hospital Central</strong>
                <small>NEIVA · HUILA</small>
            </span>
        </a>

        <div class="nav-label">GENERAL</div>
        <nav class="nav-menu">
            <a href="index.php" class="nav-link active">
                <span class="nav-icon">▦</span><span>Panel principal</span>
            </a>
            <a href="#proximas-citas" class="nav-link">
                <span class="nav-icon">▣</span><span>Próximas citas</span>
            </a>
            <a href="#estadisticas" class="nav-link">
                <span class="nav-icon">▥</span><span>Estadísticas</span>
            </a>
        </nav>

        <div class="nav-label modules-label">GESTIÓN HOSPITALARIA</div>
        <nav class="nav-menu">
            <a href="modules/pacientes.php" class="nav-link">
                <span class="nav-icon">♙</span><span>Pacientes</span>
            </a>
            <a href="modules/medicos.php" class="nav-link">
                <span class="nav-icon">✚</span><span>Médicos</span>
            </a>
            <a href="modules/eps.php" class="nav-link">
                <span class="nav-icon">▣</span><span>EPS</span>
            </a>
            <a href="modules/especialidades.php" class="nav-link">
                <span class="nav-icon">✚</span><span>Especialidades</span>
            </a>
            <a href="modules/consultorios.php" class="nav-link">
                <span class="nav-icon">⌂</span><span>Consultorios</span>
            </a>
            <a href="modules/turnos.php" class="nav-link">
                <span class="nav-icon">◷</span><span>Turnos médicos</span>
            </a>
            <a href="modules/citas.php" class="nav-link">
                <span class="nav-icon">▤</span><span>Citas médicas</span>
            </a>
            <a href="modules/diagnosticos.php" class="nav-link">
                <span class="nav-icon">♡</span><span>Diagnósticos</span>
            </a>
            <a href="modules/consultas.php" class="nav-link">
                <span class="nav-icon">⌕</span><span>Consultas SQL</span>
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="connection-indicator"><span></span> Sistema conectado</div>
            <small>Hospital Central de Neiva</small>
            <small class="sidebar-version">Sistema de gestión · Proyecto académico</small>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">HOSPITAL CENTRAL <span>/</span> PANEL PRINCIPAL</div>
            <div class="profile-chip">
                <div class="profile-avatar">A</div>
                <div>
                    <strong>Administrador</strong>
                    <small>Panel de gestión</small>
                </div>
                <span class="profile-chevron">⌄</span>
            </div>
        </header>

        <section class="welcome-section">
            <div>
                <div class="eyebrow"><span class="eyebrow-line"></span> GESTIÓN HOSPITALARIA</div>
                <h1>Panel principal</h1>
                <p>Bienvenido al centro de control del Hospital Central de Neiva.</p>
            </div>
            <div class="date-card">
                <span class="date-icon">▦</span>
                <div>
                    <small>FECHA DE HOY</small>
                    <strong><?php echo date("d/m/Y"); ?></strong>
                </div>
            </div>
        </section>

        <section class="stats-section" id="resumen">
            <div class="section-heading">
                <div>
                    <h2>Resumen general</h2>
                    <p>Indicadores principales del hospital</p>
                </div>
                <span class="live-label"><span></span> Datos en tiempo real</span>
            </div>

            <div class="stats-grid">
                <article class="stat-card">
                    <div class="stat-top">
                        <span class="stat-icon patients-icon">♙</span>
                        <span class="stat-caption">REGISTRO</span>
                    </div>
                    <div class="stat-number"><?php echo number_format($totalPacientes); ?></div>
                    <div class="stat-title">Pacientes registrados</div>
                    <div class="stat-foot">Personas en la base de datos</div>
                </article>

                <article class="stat-card">
                    <div class="stat-top">
                        <span class="stat-icon doctors-icon">✚</span>
                        <span class="stat-caption">PERSONAL</span>
                    </div>
                    <div class="stat-number"><?php echo number_format($totalMedicos); ?></div>
                    <div class="stat-title">Médicos</div>
                    <div class="stat-foot">Profesionales registrados</div>
                </article>

                <article class="stat-card">
                    <div class="stat-top">
                        <span class="stat-icon appointments-icon">▣</span>
                        <span class="stat-caption">AGENDA</span>
                    </div>
                    <div class="stat-number"><?php echo number_format($totalCitas); ?></div>
                    <div class="stat-title">Citas médicas</div>
                    <div class="stat-foot">Citas registradas en el sistema</div>
                </article>

                <article class="stat-card">
                    <div class="stat-top">
                        <span class="stat-icon offices-icon">⌂</span>
                        <span class="stat-caption">INSTALACIONES</span>
                    </div>
                    <div class="stat-number"><?php echo number_format($totalConsultorios); ?></div>
                    <div class="stat-title">Consultorios</div>
                    <div class="stat-foot">Espacios de atención registrados</div>
                </article>
            </div>
        </section>

        <section class="content-grid">
            <article class="surface-card appointments-card" id="proximas-citas">
                <div class="card-heading">
                    <div>
                        <div class="eyebrow">AGENDA</div>
                        <h2>Próximas citas</h2>
                        <p>Pacientes con citas pendientes o confirmadas</p>
                    </div>
                    <a href="modules/citas.php" class="text-link">Ver citas <span>→</span></a>
                </div>

                <div class="table-wrap">
                    <table class="appointments-table">
                        <thead>
                            <tr>
                                <th>Paciente</th>
                                <th>Médico y especialidad</th>
                                <th>Fecha y hora</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($citas) > 0): ?>
                            <?php foreach ($citas as $cita): ?>
                                <?php
                                    $estadoClase = strtolower(str_replace(" ", "-", $cita["estado"]));
                                    $fechaCita = date("d/m/Y", strtotime($cita["fecha_cita"]));
                                    $horaCita = date("h:i A", strtotime($cita["hora_cita"]));
                                ?>
                                <tr>
                                    <td>
                                        <div class="person-cell">
                                            <span class="person-avatar"><?php echo limpiar(mb_substr($cita["paciente_nombre"], 0, 1, "UTF-8")); ?></span>
                                            <strong><?php echo limpiar($cita["paciente_nombre"] . " " . $cita["paciente_apellido"]); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <strong class="doctor-name"><?php echo limpiar($cita["medico_nombre"] . " " . $cita["medico_apellido"]); ?></strong>
                                        <small class="cell-subtitle"><?php echo limpiar($cita["nombre_especialidad"]); ?></small>
                                    </td>
                                    <td>
                                        <strong class="date-value"><?php echo limpiar($fechaCita); ?></strong>
                                        <small class="cell-subtitle"><?php echo limpiar($horaCita); ?></small>
                                    </td>
                                    <td><span class="status-pill <?php echo limpiar($estadoClase); ?>"><?php echo limpiar($cita["estado"]); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="empty-state">
                                    <span>▣</span>
                                    <strong>No hay próximas citas</strong>
                                    <small>Cuando registres nuevas citas pendientes o confirmadas, aparecerán aquí.</small>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="surface-card chart-card" id="estadisticas">
                <div class="card-heading">
                    <div>
                        <div class="eyebrow">ANÁLISIS</div>
                        <h2>Estado de las citas</h2>
                        <p>Distribución de las citas registradas</p>
                    </div>
                </div>
                <div class="chart-area">
                    <canvas id="graficaCitas"></canvas>
                </div>
                <div class="chart-note">
                    <span class="chart-note-dot"></span>
                    <span>Resumen actualizado desde la base de datos</span>
                </div>
            </article>
        </section>

        <footer class="page-footer">
            <strong>Hospital Central de Neiva</strong>
            <span>Sistema de Gestión Hospitalaria · Proyecto académico</span>
        </footer>
    </main>
</div>

<script>
const nombresEstados = <?php echo json_encode($nombresEstados, JSON_UNESCAPED_UNICODE); ?>;
const cantidadesEstados = <?php echo json_encode($cantidadesEstados); ?>;

document.addEventListener("DOMContentLoaded", function () {
    const canvas = document.getElementById("graficaCitas");
    if (!canvas) return;

    if (typeof Chart === "undefined") {
        canvas.parentElement.innerHTML =
            '<p class="chart-error">No se pudo cargar la gráfica. Verifica tu conexión a Internet.</p>';
        return;
    }

    new Chart(canvas, {
        type: "doughnut",
        data: {
            labels: nombresEstados,
            datasets: [{
                data: cantidadesEstados,
                backgroundColor: ["#3978D5", "#20A58B", "#E9A23B", "#D95D70", "#929CAD"],
                borderColor: "#FFFFFF",
                borderWidth: 4,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: "69%",
            plugins: {
                legend: {
                    position: "bottom",
                    labels: {
                        usePointStyle: true,
                        pointStyle: "circle",
                        padding: 18,
                        color: "#65758B",
                        font: { family: "Inter", size: 11 }
                    }
                },
                tooltip: { padding: 12, cornerRadius: 8 }
            }
        }
    });
});
</script>
</body>
</html>
