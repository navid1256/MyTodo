<?php
defined('BASE_PATH') OR die("Permision Denied !");
function getCurrentUrl(){
    return 1;
}

function isAjaxRequest(){
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' ) {
        return true;
    }
    return false;
}

function diepage($msg){
    echo $msg;
    die();
}

function dd($var){
    echo "<pre style='color: red;
    position: relative;
    z-index: 999;
    padding: 10px;
    margin: 10px;
    border-radius: 10px;
    background: white;' >";
    var_dump($var);
    echo "</pre>";
}

