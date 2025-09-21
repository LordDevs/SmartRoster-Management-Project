<?php
require_once 'config.php';
require_once 'notifications_helpers.php';
requireLogin();
header('Content-Type: application/json');
$userId=(int)$_SESSION['user_id']; ensureNotificationsTable($pdo);
$method=$_SERVER['REQUEST_METHOD']; $action=$_GET['action'] ?? $_POST['action'] ?? 'count';
try{
  if($method==='GET'){
    if($action==='count'){
      $st=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND status="unread"'); $st->execute([$userId]);
      echo json_encode(['success'=>true,'count'=>(int)$st->fetchColumn()]); exit();
    } elseif($action==='list'){
      $limit=isset($_GET['limit'])?(int)$_GET['limit']:10; if($limit<1||$limit>50)$limit=10;
      $st=$pdo->prepare('SELECT id,message,type,status,created_at FROM notifications WHERE user_id=? ORDER BY (CASE WHEN status="unread" THEN 0 ELSE 1 END), created_at DESC LIMIT ?');
      $st->execute([$userId,$limit]); echo json_encode(['success'=>true,'items'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit();
    }
  } elseif($method==='POST'){
    if($action==='mark_read'){
      $id=isset($_POST['id'])?(int)$_POST['id']:0; if($id<=0){ echo json_encode(['success'=>false,'message'=>'Invalid ID.']); exit(); }
      $st=$pdo->prepare('UPDATE notifications SET status="read" WHERE id=? AND user_id=?'); $st->execute([$id,$userId]); echo json_encode(['success'=>true]); exit();
    } elseif($action==='mark_all_read'){
      $st=$pdo->prepare('UPDATE notifications SET status="read" WHERE user_id=? AND status="unread"'); $st->execute([$userId]); echo json_encode(['success'=>true]); exit();
    }
  }
  echo json_encode(['success'=>false,'message'=>'Unsupported action.']);
}catch(Exception $e){ echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]); }
