<?php
session_start();
header('Content-type: application/json');
include_once '../db_scripts/Models/Users.php';
include_once '../db_scripts/Models/Cinemas.php';
include_once('../db_scripts/keyrock_api.php');
include_once('../Utils/Random.php');
include_once('../Utils/Logs.php');

logger("-- In Edit Cinema");

// Check if User is logged in AND is an Admin
if (isset($_SESSION['login'])
    && $_SESSION['login'] === true
    && isset($_SESSION['user_role'])
    && $_SESSION['user_role'] === User::CINEMAOWNER)
{
    // User already logged in...
    logger("User: " . $_SESSION['user_username']);
    logger("Role: " . $_SESSION['user_role']);

    $data = json_decode(file_get_contents('php://input'), true);

    If (isset($data['cinema_id']))
    {
        $success_flag = Cinema::EditCinema($data['cinema_id'], $data['cinema_name'], $_SESSION['user_id']);
        echo json_encode($success_flag);
        exit();
    }
}

// If failed for any reason...
echo json_encode(false);


