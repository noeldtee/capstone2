<?php
session_start();
require 'dbcon.php';

function validate($inputData)
{
    global $conn;

    if (is_array($inputData)) {
        $validatedArray = array_map(function($item) use ($conn) {
            return trim(mysqli_real_escape_string($conn, $item));
        }, $inputData);
        return $validatedArray;
    }

    return trim(mysqli_real_escape_string($conn, $inputData));
}

function logoutSession(){
    session_start(); // Ensure session is active
    $_SESSION = []; // Clear all session data
    session_unset(); // Unset session variables
    session_destroy(); // Destroy session
}

function redirect($url, $status)
{
    $_SESSION['status'] = $status;
    header("Location: $url");
    exit();
}

function alertMessage()
{
    if(isset($_SESSION['status']))
    {
        echo '<div class="alert alert-success">
            <h4>'.$_SESSION['status'].'</h4>
        </div>';
        unset($_SESSION['status']); 
    }
}

function checkParamId($paramType)
{
    if(isset($_GET[$paramType]) && !empty($_GET[$paramType]))
    {
        return $_GET[$paramType];
    }
    return 'No id found';
}

function getAll($tableName)
{
    global $conn;

    $table = validate($tableName);
    $query = "SELECT * FROM `$table`";
    $result = mysqli_query($conn, $query);
    return $result;
}

function getById($tableName, $id)
{
    global $conn;

    $table = validate($tableName);
    $id = validate($id);

    $query = "SELECT * FROM `$table` WHERE id = '$id' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($result) {
        if (mysqli_num_rows($result) == 1) {
            $row = mysqli_fetch_assoc($result);
            return [
                'status' => 200,
                'message' => 'Fetched Data Successfully',
                'data' => $row
            ];
        } else {
            return [
                'status' => 404,
                'message' => 'No Data Record'
            ];
        }
    } else {
        return [
            'status' => 500,
            'message' => 'Something went wrong'
        ];
    }
}

function deleteQuery($tableName, $id)
{
    global $conn;

    $table = validate($tableName);
    $id = validate($id);

    $query = "DELETE FROM `$table` WHERE id = '$id' LIMIT 1";
    $result = mysqli_query($conn, $query);
    return $result;
}
?>
