<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/Database.php';

if (!isset($_SESSION['user'])) { echo json_encode(['success'=>false,'error'=>'Not authenticated']); exit; }
if (!isset($_FILES['return_photo']) || !isset($_POST['item_id'])) { echo json_encode(['success'=>false,'error'=>'Missing file or item_id']); exit; }

$itemId = (int)$_POST['item_id'];
$file = $_FILES['return_photo'];

if ($file['error']!==UPLOAD_ERR_OK) { echo json_encode(['success'=>false,'error'=>'Upload error']); exit; }

$allowedTypes=['image/jpeg','image/png','image/jpg','image/webp'];
if (!in_array(mime_content_type($file['tmp_name']),$allowedTypes)) { echo json_encode(['success'=>false,'error'=>'Invalid file type']); exit; }
if ($file['size']>5*1024*1024) { echo json_encode(['success'=>false,'error'=>'File too large']); exit; }

try {
    $db=new Database(); $conn=$db->getConnection();
    $ext=pathinfo($file['name'],PATHINFO_EXTENSION);
    $filename='return_'.time().'_'.uniqid().'.'.$ext;
    $uploadDir=__DIR__.'/../uploads/returns/'; if(!is_dir($uploadDir)) mkdir($uploadDir,0755,true);
    $dest=$uploadDir.$filename;
    if(!move_uploaded_file($file['tmp_name'],$dest)) throw new Exception('Move failed');

    $stmt=$conn->prepare("INSERT INTO return_photos (borrowing_item_id, filename, uploaded_at) VALUES (?, ?, NOW())");
    $stmt->execute([$itemId,$filename]);
    $photoId=$conn->lastInsertId();
    echo json_encode(['success'=>true,'id'=>$photoId,'filename'=>$filename]);
} catch(Exception $e){
    if(isset($dest) && file_exists($dest)) unlink($dest);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
