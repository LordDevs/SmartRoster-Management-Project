<?php
require_once 'config.php';
require_once 'notifications_helpers.php';
requirePrivileged();
header('Content-Type: application/json');
$user=currentUser(); $role=$user['role']; $storeId=$user['store_id']??null;
$id=$_POST['id']??null; $employee=$_POST['employee_id']??null; $date=$_POST['date']??null; $startTime=$_POST['start_time']??null; $endTime=$_POST['end_time']??null;
if(!$id){ echo json_encode(['success'=>false,'message'=>'ID do turno ausente.']); exit(); }
try{
  $q=$pdo->prepare('SELECT s.*,e.store_id,e.name as emp_name,st.name as store_name FROM shifts s JOIN employees e ON s.employee_id=e.id LEFT JOIN stores st ON st.id=e.store_id WHERE s.id=?');
  $q->execute([$id]); $shift=$q->fetch(PDO::FETCH_ASSOC);
  if(!$shift){ echo json_encode(['success'=>false,'message'=>'Turno não encontrado.']); exit(); }
  if($role==='manager' && $storeId!==null && (int)$shift['store_id']!==(int)$storeId){ echo json_encode(['success'=>false,'message'=>'Sem permissão para alterar este turno.']); exit(); }
  $newEmployee=$employee?:$shift['employee_id']; $newDate=$date?:$shift['date']; $newStart=$startTime?:$shift['start_time']; $newEnd=$endTime?:$shift['end_time'];
  if($employee){
    $qe=$pdo->prepare('SELECT id,store_id,name FROM employees WHERE id=?'); $qe->execute([$employee]); $emp=$qe->fetch(PDO::FETCH_ASSOC);
    if(!$emp){ echo json_encode(['success'=>false,'message'=>'Funcionário inválido.']); exit(); }
    if($role==='manager' && $storeId!==null && (int)$emp['store_id']!==(int)$storeId){ echo json_encode(['success'=>false,'message'=>'Sem permissão para atribuir este funcionário.']); exit(); }
  }
  $chk=$pdo->prepare('SELECT COUNT(*) FROM shifts WHERE employee_id=? AND date=? AND id<>?'); $chk->execute([$newEmployee,$newDate,$id]);
  if($chk->fetchColumn()>0){ echo json_encode(['success'=>false,'message'=>'Já existe um turno deste funcionário nesta data.']); exit(); }
  $dow=date('w',strtotime($newDate));
  $pref=$pdo->prepare('SELECT available_start_time, available_end_time FROM employee_preferences WHERE employee_id=? AND day_of_week=?');
  $pref->execute([$newEmployee,$dow]);
  if($p=$pref->fetch(PDO::FETCH_ASSOC)){
    if(strcmp($newStart,$p['available_start_time'])<0 || strcmp($newEnd,$p['available_end_time'])>0){
      echo json_encode(['success'=>false,'message'=>'Novo horário está fora da preferência do funcionário.']); exit();
    }
  }
  $up=$pdo->prepare('UPDATE shifts SET employee_id=?, date=?, start_time=?, end_time=? WHERE id=?');
  $up->execute([$newEmployee,$newDate,$newStart,$newEnd,$id]);
  $msg=formatShiftMsg($newDate,$newStart,$newEnd,$shift['store_name']??null,'Turno atualizado'); addNotification($pdo,(int)$newEmployee,$msg,'shift_updated');
  echo json_encode(['success'=>true]);
}catch(Exception $e){ echo json_encode(['success'=>false,'message'=>'Erro: '.$e->getMessage()]); }
