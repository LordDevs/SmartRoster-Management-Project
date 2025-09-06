<?php
require_once 'config.php';
require_once 'notifications_helpers.php';
requirePrivileged();
header('Content-Type: application/json');
$user=currentUser(); $role=$user['role']; $storeId=$user['store_id']??null;
$shiftId=$_POST['id']??null;
if(!$shiftId){ echo json_encode(['success'=>false,'message'=>'ID do turno não fornecido.']); exit(); }
try{
  $stmtShift=$pdo->prepare('SELECT s.id,s.employee_id,s.date,s.start_time,s.end_time,e.name AS employee_name,e.store_id,st.name AS store_name FROM shifts s JOIN employees e ON s.employee_id=e.id LEFT JOIN stores st ON st.id=e.store_id WHERE s.id=?');
  $stmtShift->execute([$shiftId]); $shift=$stmtShift->fetch(PDO::FETCH_ASSOC);
  if(!$shift){ echo json_encode(['success'=>false,'message'=>'Turno não encontrado.']); exit(); }
  if($role==='manager' && $storeId!==null && (int)$shift['store_id']!==(int)$storeId){ echo json_encode(['success'=>false,'message'=>'Você não tem permissão para excluir este turno.']); exit(); }
  $pdo->prepare('DELETE FROM shifts WHERE id=?')->execute([$shiftId]);
  $msg=formatShiftMsg($shift['date'],$shift['start_time'],$shift['end_time'],$shift['store_name']??null,'Turno cancelado'); addNotification($pdo,(int)$shift['employee_id'],$msg,'shift_deleted');
  echo json_encode(['success'=>true]);
}catch(Exception $e){ echo json_encode(['success'=>false,'message'=>'Erro: '.$e->getMessage()]); }
