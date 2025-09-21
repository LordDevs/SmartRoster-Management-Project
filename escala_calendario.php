<?php
// Enhanced interactive calendar with corrected backdrop overlap after saving
require_once 'config.php';
requireLogin();
$user = currentUser();
if (!$user || !in_array($user['role'], ['admin','manager'])) {
    header('Location: dashboard.php');
    exit();
}
$employeesList = [];
$storeId = $user['store_id'] ?? null;
if ($user['role'] === 'manager') {
    $stmt = $pdo->prepare('SELECT id, name FROM employees WHERE store_id = ? ORDER BY name');
    $stmt->execute([$storeId]);
    $employeesList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $employeesList = $pdo->query('SELECT id, name FROM employees ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
}
// Carrega turnos
$events = [];
try {
    if ($user['role'] === 'manager') {
        $q = $pdo->prepare('SELECT s.id,s.employee_id,e.name as employee_name,s.date,s.start_time,s.end_time FROM shifts s JOIN employees e ON s.employee_id=e.id WHERE e.store_id=?');
        $q->execute([$storeId]);
    } else {
        $q = $pdo->query('SELECT s.id,s.employee_id,e.name as employee_name,s.date,s.start_time,s.end_time FROM shifts s JOIN employees e ON s.employee_id=e.id');
    }
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $events[] = [
            'id' => $row['id'],
            'title' => $row['employee_name'],
            'start' => $row['date'].'T'.$row['start_time'],
            'end' => $row['date'].'T'.$row['end_time'],
            'extendedProps' => [ 'employee_id' => $row['employee_id'] ],
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
    <title>Interactive Calendar – Escala Hillbillys</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.css">
    <style>body{padding-top:60px;}</style>
</head>
<body>
<?php
    $activePage = 'calendario';
    require_once __DIR__ . '/navbar.php';
?>
<div class="container">
  <h3 class="mb-3">Interactive Calendar</h3>
  <div id="calendar"></div>
</div>
<!-- Shift modal -->
<div class="modal fade" id="shiftModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Shift</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="shiftForm">
          <input type="hidden" id="shift-id" name="shift_id" />
          <div class="mb-3">
            <label class="form-label">Date</label>
            <input type="date" id="shift-date" name="date" class="form-control" readonly required>
          </div>
          <div class="mb-3">
            <label class="form-label">Start time</label>
            <input type="time" id="shift-start" name="start_time" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">End time</label>
            <input type="time" id="shift-end" name="end_time" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Employee</label>
            <select id="shift-employee" name="employee_id" class="form-select" required>
              <option value="">Select…</option>
              <?php foreach ($employeesList as $emp): ?>
                <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="save-btn">Save</button>
      </div>
    </div>
  </div>
</div>
<!-- Delete modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirm Deletion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="delete-info"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="delete-btn">Delete</button>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.7/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/interaction@6.1.9/index.global.min.js"></script>
<script>
// Remove backdrop leftover
function clearBackdrops() {
  document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
}
async function submitShift(calendar) {
  const id    = document.getElementById('shift-id').value;
  const emp   = document.getElementById('shift-employee').value;
  const date  = document.getElementById('shift-date').value;
  const start = document.getElementById('shift-start').value;
  const end   = document.getElementById('shift-end').value;
  const fd    = new FormData();
  if (id) fd.append('id', id);
  fd.append('employee_id', emp);
  fd.append('date', date);
  fd.append('start_time', start);
  fd.append('end_time', end);
  const url   = id ? 'update_shift.php' : 'create_shift.php';
  try {
    const res = await fetch(url, { method:'POST', body: fd });
    const data = await res.json();
    if (!data.success) {
      alert(data.message || 'Error saving.');
    }
    // Regardless of success or failure, close the modal and clear the backdrop
    bootstrap.Modal.getInstance(document.getElementById('shiftModal')).hide();
    clearBackdrops();
    calendar.refetchEvents();
  } catch (e) {
    alert('Communication failed.');
    bootstrap.Modal.getInstance(document.getElementById('shiftModal')).hide();
    clearBackdrops();
  }
}
async function removeShift(calendar, shiftId) {
  const fd = new FormData();
  fd.append('id', shiftId);
  try {
    const res = await fetch('delete_shift.php', { method:'POST', body: fd });
    const data = await res.json();
    if (!data.success) alert(data.message || 'Error deleting.');
    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
    clearBackdrops();
    calendar.refetchEvents();
  } catch (e) {
    alert('Communication failed.');
    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
    clearBackdrops();
  }
}
document.addEventListener('DOMContentLoaded', function() {
  const calendarEl = document.getElementById('calendar');
  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,timeGridDay' },
    locale: 'en-gb',
    selectable: <?php echo ($user['role'] === 'admin' || $user['role'] === 'manager') ? 'true' : 'false'; ?>,
    editable: <?php echo ($user['role'] === 'admin' || $user['role'] === 'manager') ? 'true' : 'false'; ?>,
    events: <?php echo json_encode($events, JSON_UNESCAPED_UNICODE); ?>,
    eventTimeFormat: { hour:'2-digit', minute:'2-digit', meridiem: false },
    select: function(info) {
      document.getElementById('shift-id').value = '';
      document.getElementById('shift-date').value = info.startStr.substring(0,10);
      const st = info.startStr.substring(11,16) || '09:00';
      let et = '';
      if (info.endStr) et = info.endStr.substring(11,16);
      if (!et || et === '00:00') et = '17:00';
      document.getElementById('shift-start').value = st;
      document.getElementById('shift-end').value   = et;
      document.getElementById('shift-employee').value = '';
      new bootstrap.Modal(document.getElementById('shiftModal')).show();
    },
    dateClick: function(info) {
      document.getElementById('shift-id').value = '';
      document.getElementById('shift-date').value = info.dateStr;
      document.getElementById('shift-start').value = '09:00';
      document.getElementById('shift-end').value   = '17:00';
      document.getElementById('shift-employee').value = '';
      new bootstrap.Modal(document.getElementById('shiftModal')).show();
    },
    eventClick: function(info) {
      <?php if ($user['role'] === 'admin' || $user['role'] === 'manager'): ?>
      const ev = info.event;
      document.getElementById('shift-id').value = ev.id;
      document.getElementById('shift-date').value = ev.start.toISOString().slice(0,10);
      document.getElementById('shift-start').value = ev.start.toTimeString().slice(0,5);
      document.getElementById('shift-end').value   = ev.end ? ev.end.toTimeString().slice(0,5) : '';
      if (ev.extendedProps && ev.extendedProps.employee_id) {
        document.getElementById('shift-employee').value = ev.extendedProps.employee_id;
      }
      // configure deletion
      document.getElementById('delete-info').textContent = `${ev.title} - ${ev.start.toLocaleString()}`;
      document.getElementById('delete-btn').onclick = function() { removeShift(calendar, ev.id); };
      new bootstrap.Modal(document.getElementById('deleteModal')).show();
      <?php endif; ?>
    },
    eventDrop: function(info) {
      <?php if ($user['role'] === 'admin' || $user['role'] === 'manager'): ?>
      (async function() {
        const ev = info.event;
        const fd = new FormData();
        fd.append('id', ev.id);
        fd.append('date', ev.start.toISOString().slice(0,10));
        fd.append('start_time', ev.start.toTimeString().slice(0,5));
        fd.append('end_time', ev.end ? ev.end.toTimeString().slice(0,5) : ev.start.toTimeString().slice(0,5));
        const res = await fetch('update_shift.php', { method:'POST', body: fd });
        const data = await res.json();
        if (!data.success) {
          alert(data.message || 'Error moving shift');
          info.revert();
        }
      })();
      <?php else: ?>
      info.revert();
      <?php endif; ?>
    },
    eventResize: function(info) {
      <?php if ($user['role'] === 'admin' || $user['role'] === 'manager'): ?>
      (async function() {
        const ev = info.event;
        const fd = new FormData();
        fd.append('id', ev.id);
        fd.append('date', ev.start.toISOString().slice(0,10));
        fd.append('start_time', ev.start.toTimeString().slice(0,5));
        fd.append('end_time', ev.end ? ev.end.toTimeString().slice(0,5) : ev.start.toTimeString().slice(0,5));
        const res = await fetch('update_shift.php', { method:'POST', body: fd });
        const data = await res.json();
        if (!data.success) {
          alert(data.message || 'Error resizing shift');
          info.revert();
        }
      })();
      <?php else: ?>
      info.revert();
      <?php endif; ?>
    }
  });
  calendar.render();
  document.getElementById('save-btn').addEventListener('click', function() {
    submitShift(calendar);
  });
});
</script>
</body>
</html>
