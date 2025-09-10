<?php

/**
 * Recupera o limite máximo de horas semanais para um funcionário.
 *
 * @param int     $funcionario_id
 * @param mysqli  $conn
 * @return float
 */
function getWeeklyMaxHours($funcionario_id, $conn) {
    $stmt = $conn->prepare('SELECT weekly_max_hours FROM funcionarios WHERE id = ?');
    $stmt->bind_param('i', $funcionario_id);
    $stmt->execute();
    $stmt->bind_result($max);
    $stmt->fetch();
    $stmt->close();
    return $max ?: 40;
}

/**
 * Recupera a taxa horária (salário por hora) de um funcionário.
 *
 * @param int     $funcionario_id
 * @param mysqli  $conn
 * @return float
 */
function getHourlyRate($funcionario_id, $conn) {
    $stmt = $conn->prepare('SELECT hourly_rate FROM funcionarios WHERE id = ?');
    $stmt->bind_param('i', $funcionario_id);
    $stmt->execute();
    $stmt->bind_result($rate);
    $stmt->fetch();
    $stmt->close();
    return $rate ?: 0;
}

/**
 * Calcula quantas horas um funcionário trabalhou em um intervalo de datas.
 *
 * Assume que existe uma tabela `escalas` (ou `turnos`) contendo colunas `data`,
 * `hora_inicio`, `hora_fim` e `funcionario_id`. Ajuste os nomes de campos
 * conforme o seu banco.
 *
 * @param int        $funcionario_id
 * @param DateTime   $weekStart
 * @param DateTime   $weekEnd
 * @param mysqli     $conn
 * @return float     Número de horas trabalhadas
 */
function getEmployeeHoursWorked($funcionario_id, $weekStart, $weekEnd, $conn) {
    $start = $weekStart->format('Y-m-d H:i:s');
    $end   = $weekEnd->format('Y-m-d H:i:s');
    $hours = 0.0;

    // Ajuste a consulta abaixo para refletir os nomes exatos das colunas e tabela
    $query = 'SELECT data, hora_inicio, hora_fim FROM escalas WHERE funcionario_id = ? AND CONCAT(data, " ", hora_inicio) >= ? AND CONCAT(data, " ", hora_fim) <= ?';
    $stmt  = $conn->prepare($query);
    $stmt->bind_param('iss', $funcionario_id, $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $shiftStart = new DateTime($row['data'].' '.$row['hora_inicio'], new DateTimeZone('Europe/Dublin'));
        $shiftEnd   = new DateTime($row['data'].' '.$row['hora_fim'],   new DateTimeZone('Europe/Dublin'));
        $diff       = $shiftEnd->getTimestamp() - $shiftStart->getTimestamp();
        $hours     += $diff / 3600;
    }
    $stmt->close();
    return $hours;
}

/**
 * Valida um turno proposto em relação às preferências e restrições do funcionário.
 *
 * Retorna uma string com mensagem de erro caso o turno seja inválido ou null
 * se estiver tudo ok. Esta função deve ser chamada antes de inserir ou atualizar
 * turnos no banco.
 *
 * @param int       $funcionario_id
 * @param DateTime  $start  Data/hora de início do turno
 * @param DateTime  $end    Data/hora de término do turno
 * @param mysqli    $conn
 * @return string|null
 */
function validate_shift($funcionario_id, $start, $end, $conn) {
    // Impede sobreposição com turnos existentes
    $s = $start->format('Y-m-d H:i:s');
    $e = $end->format('Y-m-d H:i:s');
    $query = 'SELECT data, hora_inicio, hora_fim FROM escalas WHERE funcionario_id = ? AND ((CONCAT(data, " ", hora_inicio) < ? AND CONCAT(data, " ", hora_fim) > ?) OR (CONCAT(data, " ", hora_inicio) >= ? AND CONCAT(data, " ", hora_inicio) < ?))';
    $stmt  = $conn->prepare($query);
    $stmt->bind_param('issss', $funcionario_id, $e, $s, $s, $e);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return 'O turno se sobrepõe a outro turno existente.';
    }
    $stmt->close();

    // Preferências para o dia da semana
    $dayOfWeek = (int)$start->format('w'); // 0=domingo
    $stmt = $conn->prepare('SELECT available_start, available_end, max_hours_per_day, min_rest_hours FROM employee_preferences WHERE funcionario_id = ? AND day_of_week = ?');
    $stmt->bind_param('ii', $funcionario_id, $dayOfWeek);
    $stmt->execute();
    $stmt->bind_result($avail_start, $avail_end, $max_hours_day, $min_rest);
    if ($stmt->fetch()) {
        // Verifica janelas de disponibilidade
        if ($avail_start && $start->format('H:i:s') < $avail_start) {
            return 'Início do turno antes do horário disponível.';
        }
        if ($avail_end && $end->format('H:i:s') > $avail_end) {
            return 'Fim do turno após o horário disponível.';
        }
        // Máximo de horas por dia
        $stmt2 = $conn->prepare('SELECT data, hora_inicio, hora_fim FROM escalas WHERE funcionario_id = ? AND data = ?');
        $date = $start->format('Y-m-d');
        $stmt2->bind_param('is', $funcionario_id, $date);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $workedToday = 0.0;
        while ($r = $result2->fetch_assoc()) {
            $shiftStart = new DateTime($r['data'].' '.$r['hora_inicio'], new DateTimeZone('Europe/Dublin'));
            $shiftEnd   = new DateTime($r['data'].' '.$r['hora_fim'],   new DateTimeZone('Europe/Dublin'));
            $workedToday += ($shiftEnd->getTimestamp() - $shiftStart->getTimestamp()) / 3600;
        }
        $stmt2->close();
        $newHours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
        if ($max_hours_day && ($workedToday + $newHours) > $max_hours_day) {
            return 'Excederia o máximo de horas permitidas por dia.';
        }
        // Intervalo mínimo entre turnos
        if ($min_rest) {
            $stmt3 = $conn->prepare('SELECT data, hora_inicio, hora_fim FROM escalas WHERE funcionario_id = ? ORDER BY data DESC, hora_fim DESC LIMIT 1');
            $stmt3->bind_param('i', $funcionario_id);
            $stmt3->execute();
            $result3 = $stmt3->get_result();
            if ($row3 = $result3->fetch_assoc()) {
                $lastEnd = new DateTime($row3['data'].' '.$row3['hora_fim'], new DateTimeZone('Europe/Dublin'));
                $hoursSinceLast = ($start->getTimestamp() - $lastEnd->getTimestamp()) / 3600;
                if ($hoursSinceLast < $min_rest) {
                    return 'Intervalo insuficiente desde o último turno.';
                }
            }
            $stmt3->close();
        }
    }
    $stmt->close();

    // Máximo de horas por semana
    $weekStart = clone $start;
    $weekStart->modify('monday this week');
    $weekStart->setTime(0, 0, 0);
    $weekEnd = clone $weekStart;
    $weekEnd->modify('+7 days');
    $hoursWorked    = getEmployeeHoursWorked($funcionario_id, $weekStart, $weekEnd, $conn);
    $weeklyMax      = getWeeklyMaxHours($funcionario_id, $conn);
    $newShiftHours  = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
    if ($hoursWorked + $newShiftHours > $weeklyMax) {
        return 'Excederia o total de horas permitidas na semana.';
    }
    return null;
}