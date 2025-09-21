<?php
// escala_calendario.php – interactive calendar view for shifts
// Managers see only shifts for their store; administrators see every shift.
require_once 'config.php';

// Validate user login and role
requireLogin();
$user = currentUser();
// Allow only administrators or managers
if (!$user || !in_array($user['role'], ['admin', 'manager'])) {
    header('Location: dashboard.php');
    exit();
}

// Fetch employee list according to the user role
$employeesList = [];
// Store ID for managers
$storeId = $user['store_id'] ?? null;
if ($user['role'] === 'manager') {
    $stmtEmp = $pdo->prepare('SELECT id, name FROM employees WHERE store_id = ? ORDER BY name');
    $stmtEmp->execute([$storeId]);
    $employeesList = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);
} else {
    $employeesList = $pdo->query('SELECT id, name FROM employees ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
}

// Load shifts as calendar events
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Shift Calendar – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.css">
    <style>
        body { padding-top: 60px; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php
        $activePage = 'calendario';
        require_once __DIR__ . '/navbar.php';
    ?>

    <div class="container">
        <h3 class="mb-4">Shift Calendar</h3>
        <div id="calendar"></div>
        <!-- Modal for shift creation -->
        <div class="modal fade" id="shiftModal" tabindex="-1" aria-labelledby="shiftModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="shiftModalLabel">Create Shift</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="shiftForm">
                            <div class="mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" id="modal-date" name="date" required readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="modal-start" name="start_time" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">End Time</label>
                                <input type="time" class="form-control" id="modal-end" name="end_time" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Employee</label>
                                <select class="form-select" id="modal-employee" name="employee_id" required>
                                    <option value="">Select…</option>
                                    <?php foreach ($employeesList as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveShiftBtn">Save Shift</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Deletion modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Delete Shift</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this shift?</p>
                        <p id="delete-info" class="fw-bold"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.7/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
    <!-- FullCalendar and interaction plugin -->
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
            locale: 'en-gb',
            selectable: <?php echo ($user['role'] === 'admin' || $user['role'] === 'manager') ? 'true' : 'false'; ?>,
            editable: false,
            events: <?php echo json_encode($events, JSON_UNESCAPED_UNICODE); ?>,
            eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false },
            // Range selection
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
            // Date click
            dateClick: function(info) {
                document.getElementById('modal-date').value = info.dateStr;
                document.getElementById('modal-start').value = '09:00';
                document.getElementById('modal-end').value   = '17:00';
                document.getElementById('modal-employee').value = '';
                var modal = new bootstrap.Modal(document.getElementById('shiftModal'));
                modal.show();
            },
            // Event click to delete
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
        // Save shift
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
                    alert(data.message || 'Error saving shift.');
                }
            })
            .catch(err => { console.error(err); alert('Communication failed.'); });
        });
        // Delete shift
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
                    alert(data.message || 'Error deleting shift.');
                }
            })
            .catch(err => { console.error(err); alert('Communication failed.'); });
        });
    });
    </script>
</body>
</html>
