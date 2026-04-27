<?php
include "bootstrap/init.php";
// use Hekmatinasser\Verta\Verta;
// echo(verta::now());
if (isset($_GET['delete_folder'])&& is_numeric($_GET['delete_folder'])) {
    $deletedCount = deleteFolder($_GET['delete_folder']);
    echo "$deletedCount Folders Succesfully Deleted";
}

if (isset($_GET['delete_task'])&& is_numeric($_GET['delete_task'])) {
    $deletedCount = deleteTask($_GET['delete_task']);
    echo "$deletedCount Tasks Succesfully Deleted";
}

$folders = getFolders();

$tasks = getTasks();
// dd($tasks);

include "views/view-index.php";
