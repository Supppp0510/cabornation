<?php
session_start();
include "../php/config.php"; 

if(!isset($_SESSION['admin_username'])){echo"unauthorized";exit;}

if(!isset($conn)||$conn->connect_error){echo"error: Database connection failed: ".$conn->connect_error;exit;}

if($_SERVER["REQUEST_METHOD"]=="POST"){

if(!isset($_POST['id'])||!isset($_POST['status'])){echo"invalid: Missing ID or Status";exit;}

$id=intval($_POST['id']);
$status=$_POST['status'];

if(empty($status)||$id<=0){echo"invalid: Status or ID is invalid";exit;}

$stmt=$conn->prepare("UPDATE tournaments SET status=? WHERE id=?");

if(!$stmt){echo"error: Prepare failed: (".$conn->errno.") ".$conn->error;$conn->close();exit;}

$stmt->bind_param("si",$status,$id);

if($stmt->execute()){
if($stmt->affected_rows>0){
echo"success";
}else{
echo"success_no_change"; 
}
}else{
echo"error: Execute failed: (".$stmt->errno.") ".$stmt->error;
}

$stmt->close();
$conn->close();
}
?>