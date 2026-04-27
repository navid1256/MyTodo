<?php
defined('BASE_PATH') OR die("Permision Denied !");

/****Folder Function ***/
function newFolders($foldername){
    global $pdo;
    $current_user_id = getCurrentUserId();
    $sql = "INSERT INTO folder (name,user_id) VALUES (:foldername,:user_id);";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':foldername'=>$foldername,':user_id'=>$current_user_id]);
    return $stmt->rowCount();
}

function deleteFolder($folder_id) {
    global $pdo;
    $sql = "delete from folder where id= $folder_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->rowCount();
}

// function getCurrentUserId(){
//     return 1;
// }
function getFolders(){
    global $pdo;
    $current_user_id = getCurrentUserId();
    $sql = "select * from folders where user_id= $current_user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_OBJ);
    return $records;
}

/***Tasks Function***/
function deleteTask($task_id){
    global $pdo;
    $sql = "delete from tasks where id= $task_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->rowCount();
}

function addTasks(){
    return 1;
}
function getTasks(){
    global $pdo;
    $folder = $_GET['folder_id'] ?? null;
    $folderCondition = '';
    if(isset($folder) and is_numeric($folder)){
        $folderCondition = " and folder_id=$folder";
    }

    $current_user_id = getCurrentUserId();
    $sql = "select * from tasks where user_id= $current_user_id $folderCondition";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_OBJ);
    return $records;
}