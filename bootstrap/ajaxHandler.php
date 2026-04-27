<?php
include_once "init.php";
if (!isAjaxRequest()) {
    diepage("Invalid Request");
}

if (!isset($_POST['action']) || empty($_POST['action'])) {
    diepage("Invalid Action");
}

switch ($_POST['action']) {
    case 'newFolder':
        if (!isset($_POST['foldername'])|| strlen($_POST['foldername']) < 3) {
            echo ".نام فولدر باید بیشتر از 3 حرف باشد";
            die();
        }
        echo newFolders($_POST['foldername']);
        break;
    case 'newTask':
        # code...
        break;            
    default:
        diepage("Invalid Action");
        break;
}

// var_dump($_POST);