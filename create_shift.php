<?php
require_once 'config.php';
require_once 'notifications_helpers.php';
requirePrivileged();
header('Content-Type: application/json');
$user = currentUser();
$role = $user['role'];
$storeId = $user['store_id'] ?? null;
$employeeId = $_POST['employee_id'] ?? null;
$date       = $_POST['date'] ?? null;
$startTime  = $_POST['start_time'] ?? null;
$endTime    = $_POST['end_time'] ?? null;
if (!$employeeId || !$date || !$startTime || !$endTime) { echo json_encode(['success'=>false,'message'=>'Parâmetros incompletos.']); exit(); }
try {
  $stmtEmp=$pdo->prepare('SELECT e.id,e.name,e.store_id,s.name AS store_name FROM employees e LEFT JOIN stores s ON s.id=e.store_id WHERE e.id=?');
  $stmtEmp->execute([$employeeId]); $emp=$stmtEmp->fetch(PDO::FETCH_ASSOC);
  if(!$emp){ echo json_encode(['success'=>false,'message'=>'Funcionário não encontrado.']); exit(); }
  if($role==='manager' && $storeId!==null && (int)$emp['store_id']!==(int)$storeId){ echo json_encode(['success'=>false,'message'=>'Sem permissão para atribuir turnos a este funcionário.']); exit(); }
  $check=$pdo->prepare('SELECT COUNT(*) FROM shifts WHERE employee_id=? AND date=?'); $check->execute([$employeeId,$date]);
  if($check->fetchColumn()>0){ echo json_encode(['success'=>false,'message'=>'Já existe um turno para este funcionário nesta data.']); exit(); }
  $dow=date('w',strtotime($date));
  $pref=$pdo->prepare('SELECT available_start_time, available_end_time FROM employee_preferences WHERE employee_id=? AND day_of_week=?');
  $pref->execute([$employeeId,$dow]);
  if($p=$pref->fetch(PDO::FETCH_ASSOC)){
    if(strcmp($startTime,$p['available_start_time'])<0 || strcmp($endTime,$p['available_end_time'])>0){
      echo json_encode(['success'=>false,'message'=>'Horário fora da preferência deste funcionário.']); exit();
    }
  }
  $ins=$pdo->prepare('INSERT INTO shifts (employee_id,date,start_time,end_time) VALUES (?,?,?,?)');
  $ins->execute([$employeeId,$date,$startTime,$endTime]); $shiftId=$pdo->lastInsertId();
  $msg=formatShiftMsg($date,$startTime,$endTime,$emp['store_name']??null,'Turno atribuído'); addNotification($pdo,(int)$employeeId,$msg,'shift_created');
  echo json_encode(['success'=>true,'shift'=>['id'=>$shiftId,'employee_name'=>$emp['name'],'employee_id'=>$emp['id'],'date'=>$date,'start_time'=>$startTime,'end_time'=>$endTime]]);
}catch(Exception $e){ echo json_encode(['success'=>false,'message'=>'Erro: '.$e->getMessage()]); }
