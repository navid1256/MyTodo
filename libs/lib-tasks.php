<?php
defined('BASE_PATH') OR die("Permision Denied !");

/****Folder Function ***/
function newFolders(string $foldername){
    global $pdo;
    $current_user_id = getCurrentUserId();
    $sql = "INSERT INTO folder (name,user_id) VALUES (:foldername,:user_id);";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':foldername'=>$foldername,':user_id'=>$current_user_id]);
    return $stmt->rowCount();
}

function deleteFolder(int $folder_id) {
    global $pdo;
    $sql = "DELETE FROM folders WHERE id = :folder_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':folder_id' => $folder_id]);
    return $stmt->rowCount();
}

// function getCurrentUserId(){
//     return 1;
// }
function getFolders(){
    global $pdo;
    $current_user_id = getCurrentUserId();
    $sql = "SELECT * FROM folders WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $current_user_id]);
    $records = $stmt->fetchAll(PDO::FETCH_OBJ);
    return $records;
}

/***Tasks Function***/
function deleteTask(int $task_id){
    global $pdo;
    $sql = "DELETE FROM tasks WHERE id = :task_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':task_id' => $task_id]);
    return $stmt->rowCount();
}

function addTask(string $taskTitle,int $folderId){
    global $pdo;
    $current_user_id = getCurrentUserId();
    $sql = "INSERT INTO `tasks` (title,user_id,folder_id) VALUES (:title,:user_id,:folder_id);";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':title' => $taskTitle, ':user_id' => $current_user_id, ':folder_id' => $folderId]);
    return $stmt->rowCount();
}
function getTasks(){
    global $pdo;
    $folder = $_GET['folder_id'] ?? null;
    $current_user_id = getCurrentUserId();
    
    if(isset($folder) && is_numeric($folder)){
        $sql = "SELECT * FROM tasks WHERE user_id = :user_id AND folder_id = :folder_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $current_user_id, ':folder_id' => $folder]);
    } else {
        $sql = "SELECT * FROM tasks WHERE user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $current_user_id]);
    }
    
    $records = $stmt->fetchAll(PDO::FETCH_OBJ);
    return $records;
}