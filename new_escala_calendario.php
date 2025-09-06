<?php
// escala_calendario.php – visualização de escalas em formato de calendário com interatividade
// Gerentes veem apenas turnos da sua loja; administradores veem todos os turnos.
require_once 'config.php';

// Verifica login e função do usuário
requireLogin();
$user = currentUser();
// Permitido apenas para administradores ou gerentes
if (!$user || !in_array($user['role'], ['admin', 'manager'])) {
    header('Location: dashboard.php');
    exit();
}

// Recupera lista de funcionários conforme o papel do usuário
$employeesList = [];
// Store ID para gerentes
$storeId = $user['store_id'] ?? null;
if ($user['role'] === 'manager') {
    $stmtEmp = $pdo->prepare('SELECT id, name FROM employees WHERE store_id = ? ORDER BY name');
    $stmtEmp->execute([$storeId]);
    $employeesList = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);
} else {
    $employeesList = $pdo->query('SELECT id, name FROM employees ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
}

// Carrega turnos como eventos para o calendário
$events = [];
try {
    if ($user['role'] === 'manager') {
        $stmt = $pdo->prepare('SELECT s.id, s.employee_id, e.name AS employee_name, s.date, s.start_time, s.end_time
                               FROM shifts s
                               JOIN employees e ON s.employee_id = e.id
                               WHERE e.store_id = ?');
        $stmt->execute([$storeId]);
    } else {
        $stmt = $pdo->query('SELECT s.id, s.employee_id, e.name AS employee_name, s.date, s.start_time, s.end_time
                             FROM shifts s
                             JOIN employees e ON s.employee_id = e.id');
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $events[] = [
            'id'    => $row['id'],
            'title' => $row['employee_name'],
            'start' => $row['date'] . 'T' . $row['start_time'],
            'end'   => $row['date'] . 'T' . $row['end_time'],
        ];
    }
} catch (Exception $e) {
    $events = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Calendário de Escalas – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.css">
    <style>
        body { padding-top: 60px; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <?php if ($user['role'] === 'admin'): ?>
            <a class="navbar-brand" href="dashboard.php">Escala Hillbillys</a>
            <?php else: ?>
            <a class="navbar-brand" href="manager_dashboard.php">Escala Hillbillys – Gerente</a>
            <?php endif; ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="usuarios_listar.php">Funcionários</a></li>
                    <li class="nav-item"><a class="nav-link" href="escala_listar.php">Escalas</a></li>
                    <li class="nav-item"><a class="nav-link active" href="escala_calendario.php">Calendário</a></li>
                    <li class="nav-item"><a class="nav-link" href="ponto_listar.php">Pontos</a></li>
                    <li class="nav-item"><a class="nav-link" href="relatorios.php">Relatórios</a></li>
                    <li class="nav-item"><a class="nav-link" href="desempenho.php">Desempenho</a></li>
                    <li class="nav-item"><a class="nav-link" href="loja_gerenciar.php">Loja</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <h3 class="mb-4">Calendário de Escalas</h3>
        <div id="calendar"></div>
        <!-- Modal para criação de turno -->
        <div class="modal fade" id="shiftModal" tabindex="-1" aria-labelledby="shiftModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="shiftModalLabel">Criar Turno</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <form id="shiftForm">
                            <div class="mb-3">
                                <label class="form-label">Data</label>
                                <input type="date" class="form-control" id="modal-date" name="date" required readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Horário de Início</label>
                                <input type="time" class="form-control" id="modal-start" name="start_time" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Horário de Término</label>
                                <input type="time" class="form-control" id="modal-end" name="end_time" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Funcionário</label>
                                <select class="form-select" id="modal-employee" name="employee_id" required>
                                    <option value="">Selecione…</option>
                                    <?php foreach ($employeesList as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="saveShiftBtn">Salvar Turno</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal de exclusão -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Excluir Turno</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <p>Tem certeza de que deseja excluir este turno?</p>
                        <p id="delete-info" class="fw-bold"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Excluir</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.7/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
    <!-- FullCalendar e plug-in de interação -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/interaction@6.1.9/index.global.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            locale: 'pt-br',
            selectable: <?php echo ($user['role'] === 'admin' || $user['role'] === 'manager') ? 'true' : 'false'; ?>,
            editable: false,
            events: <?php echo json_encode($events, JSON_UNESCAPED_UNICODE); ?>,
            eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false },
            // Seleção de intervalo
            select: function(info) {
                document.getElementById('modal-date').value = info.startStr.substring(0, 10);
                var startTime = info.startStr.substring(11, 16);
                var endTime   = info.endStr ? info.endStr.substring(11, 16) : '';
                if (!startTime) startTime = '09:00';
                if (!endTime || endTime === '00:00') endTime = '17:00';
                document.getElementById('modal-start').value = startTime;
                document.getElementById('modal-end').value   = endTime;
                document.getElementById('modal-employee').value = '';
                var modal = new bootstrap.Modal(document.getElementById('shiftModal'));
                modal.show();
            },
            // Clique em data
            dateClick: function(info) {
                document.getElementById('modal-date').value = info.dateStr;
                document.getElementById('modal-start').value = '09:00';
                document.getElementById('modal-end').value   = '17:00';
                document.getElementById('modal-employee').value = '';
                var modal = new bootstrap.Modal(document.getElementById('shiftModal'));
                modal.show();
            },
            // Clique em evento para excluir
            eventClick: function(info) {
                <?php if ($user['role'] === 'admin' || $user['role'] === 'manager'): ?>
                document.getElementById('delete-info').textContent = info.event.title + ' - ' + info.event.start.toLocaleString();
                document.getElementById('confirmDeleteBtn').dataset.shiftId = info.event.id;
                var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                deleteModal.show();
                <?php endif; ?>
            }
        });
        calendar.render();
        // Salvar turno
        document.getElementById('saveShiftBtn').addEventListener('click', function() {
            var form = document.getElementById('shiftForm');
            var formData = new FormData(form);
            fetch('create_shift.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    calendar.addEvent({
                        id: data.shift.id,
                        title: data.shift.employee_name,
                        start: data.shift.date + 'T' + data.shift.start_time,
                        end: data.shift.date + 'T' + data.shift.end_time
                    });
                    var mod = bootstrap.Modal.getInstance(document.getElementById('shiftModal'));
                    mod.hide();
                } else {
                    alert(data.message || 'Erro ao salvar turno.');
                }
            })
            .catch(err => { console.error(err); alert('Falha na comunicação.'); });
        });
        // Excluir turno
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            var shiftId = this.dataset.shiftId;
            var fd = new FormData();
            fd.append('id', shiftId);
            fetch('delete_shift.php', {
                method: 'POST',
                body: fd
            })
            .then(resp => resp.json())
            .then(data => {
                if (data.success) {
                    var event = calendar.getEventById(shiftId);
                    if (event) event.remove();
                    var dm = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                    dm.hide();
                } else {
                    alert(data.message || 'Erro ao excluir turno.');
                }
            })
            .catch(err => { console.error(err); alert('Falha na comunicação.'); });
        });
    });
    </script>
</body>
</html>
