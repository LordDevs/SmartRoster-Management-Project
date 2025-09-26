<?php

function getWeeklyMaxHours($employee_id): float {
    global $pdo;
    $stmt = $pdo->prepare('SELECT weekly_max_hours FROM employees WHERE id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['weekly_max_hours'] ?? 40.0;
}

function getHourlyRate($employee_id): float {
    global $pdo;
    $stmt = $pdo->prepare('SELECT hourly_rate FROM employees WHERE id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['hourly_rate'] ?? 0.0;
}

function getEmployeeHoursWorked($employee_id, $weekStart, $weekEnd): float {
    global $pdo;
    $start = $weekStart->format('Y-m-d H:i:s');
    $end   = $weekEnd->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare("
        SELECT date, start_time, end_time
        FROM shifts
        WHERE employee_id = ?
          AND datetime(date || ' ' || start_time) >= ?
          AND datetime(date || ' ' || end_time) <= ?
    ");
    $stmt->execute([$employee_id, $start, $end]);
    $hours = 0.0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $shiftStart = new DateTime($row['date'] . ' ' . $row['start_time'], new DateTimeZone('Europe/Dublin'));
        $shiftEnd   = new DateTime($row['date'] . ' ' . $row['end_time'],   new DateTimeZone('Europe/Dublin'));
        $hours     += max(0, ($shiftEnd->getTimestamp() - $shiftStart->getTimestamp()) / 3600);
    }
    return $hours;
}

function validate_shift($employee_id, $start, $end): ?string {
    global $pdo;
    $s = $start->format('Y-m-d H:i:s');
    $e = $end->format('Y-m-d H:i:s');

    // Prevent overlap with existing shifts
    $stmt = $pdo->prepare("
        SELECT date, start_time, end_time
        FROM shifts
        WHERE employee_id = ?
          AND (
            (datetime(date || ' ' || start_time) < ? AND datetime(date || ' ' || end_time) > ?)
            OR
            (datetime(date || ' ' || start_time) >= ? AND datetime(date || ' ' || start_time) < ?)
          )
    ");
    $stmt->execute([$employee_id, $e, $s, $s, $e]);
    if ($stmt->fetch()) {
        return 'The shift overlaps an existing shift.';
    }

    // Preferences for the weekday
    $dayOfWeek = (int)$start->format('w'); // 0=Sunday
    $stmt = $pdo->prepare('SELECT available_start, available_end, max_hours_per_day, min_rest_hours FROM employee_preferences WHERE employee_id = ? AND day_of_week = ?');
    $stmt->execute([$employee_id, $dayOfWeek]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $avail_start  = $row['available_start'];
        $avail_end    = $row['available_end'];
        $max_hours_day = $row['max_hours_per_day'];
        $min_rest     = $row['min_rest_hours'];

        if ($avail_start && $start->format('H:i:s') < $avail_start) {
            return 'Shift start before available time.';
        }
        if ($avail_end && $end->format('H:i:s') > $avail_end) {
            return 'Shift end after available time.';
        }
        // Maximum hours per day
        $date = $start->format('Y-m-d');
        $stmt2 = $pdo->prepare('SELECT date, start_time, end_time FROM shifts WHERE employee_id = ? AND date = ?');
        $stmt2->execute([$employee_id, $date]);
        $workedToday = 0.0;
        while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            $shiftStart = new DateTime($r['date'].' '.$r['start_time'], new DateTimeZone('Europe/Dublin'));
            $shiftEnd   = new DateTime($r['date'].' '.$r['end_time'],   new DateTimeZone('Europe/Dublin'));
            $workedToday += ($shiftEnd->getTimestamp() - $shiftStart->getTimestamp()) / 3600;
        }
        $newHours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
        if ($max_hours_day && ($workedToday + $newHours) > $max_hours_day) {
            return 'Would exceed the allowed daily hours.';
        }
        // Minimum rest between shifts
        if ($min_rest) {
            $stmt3 = $pdo->prepare("
                SELECT date, end_time
                FROM shifts
                WHERE employee_id = ?
                  AND datetime(date || ' ' || end_time) <= ?
                ORDER BY date DESC, end_time DESC
                LIMIT 1
            ");
            $stmt3->execute([$employee_id, $s]);
            if ($row3 = $stmt3->fetch(PDO::FETCH_ASSOC)) {
                $lastEnd = new DateTime($row3['date'].' '.$row3['end_time'], new DateTimeZone('Europe/Dublin'));
                $hoursSinceLast = ($start->getTimestamp() - $lastEnd->getTimestamp()) / 3600;
                if ($hoursSinceLast < $min_rest) {
                    return 'Insufficient rest since the last shift.';
                }
            }
        }
    }

    // Weekly maximum hours
    $weekStart = (clone $start)->modify('monday this week')->setTime(0, 0, 0);
    $weekEnd   = (clone $weekStart)->modify('+7 days');
    $hoursWorked   = getEmployeeHoursWorked($employee_id, $weekStart, $weekEnd);
    $weeklyMax     = getWeeklyMaxHours($employee_id);
    $newShiftHours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
    if ($hoursWorked + $newShiftHours > $weeklyMax) {
        return 'Would exceed the weekly hour limit.';
    }
    return null;
}