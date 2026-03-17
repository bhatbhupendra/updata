<?php
session_start();
include '../config/db.php';

if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT redirect_url FROM notifications WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$row){
    die("Notification not found.");
}

/* mark read */

$stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

$url = trim($row['redirect_url']);

if($url){
    header("Location: ".$url);
    exit();
}

echo "No redirect URL.";