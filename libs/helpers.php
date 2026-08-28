<?php
defined('BASE_PATH') or die("Permision Denied !");
function getCurrentUrl()
{
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

function getApplicationTimezone(): DateTimeZone
{
    return new DateTimeZone('Asia/Tehran');
}

function getClientTimezone(): DateTimeZone
{
    $timezoneName = isset($_COOKIE['mytodo_timezone']) && is_string($_COOKIE['mytodo_timezone'])
        ? trim($_COOKIE['mytodo_timezone'])
        : '';

    if ($timezoneName !== '' && strlen($timezoneName) <= 100) {
        try {
            return new DateTimeZone($timezoneName);
        } catch (Exception $exception) {
            // Fall back to the application's timezone for invalid cookie values.
        }
    }

    return getApplicationTimezone();
}

