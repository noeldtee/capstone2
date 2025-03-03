<?php
require '../config/function.php';

if(isset($_POST['saveUser']))
{
    $firstname = validate($_POST['firstname']);
    $lastname = validate($_POST['lastname']);
    $email = validate($_POST['email']);
    $password = validate($_POST['password']);
    $role = validate($_POST['role']);
    $is_ban = isset($_POST['is_ban']) == true ? 1 : 0;

    if($firstname != '' || $lastname != '' || $email != '' || $password != '')
    {
        $query = "INSERT INTO users (firstname, lastname, email, password, role, is_ban) 
                VALUES ('$firstname', '$lastname', '$email', '$password', '$role', '$is_ban')";
        $result = mysqli_query($conn, $query);

        if($result)
        {
            redirect('users-create.php', 'User added successfully');
        }
        else
        {
            redirect('users.php', 'Failed to add user');
        }
    }
    else
    {
        redirect('users-create.php', 'Please fill all the fields');
    }
}

if(isset($_POST['updateUser']))
{
    $firstname = validate($_POST['firstname']);
    $lastname = validate($_POST['lastname']);
    $email = validate($_POST['email']);
    $password = validate($_POST['password']);
    $role = validate($_POST['role']);
    $is_ban = isset($_POST['is_ban']) == true ? 1 : 0;

    $userID =  validate($_POST['userID']);
    $user = getById('users', $userID);
    if($user['status'] != 200)
    {
        redirect('users-edit.php?id='.$userID, 'No such id found');
    }

    if($firstname != '' || $lastname != '' || $email != '' || $password != '')
    {
        $query = "UPDATE users SET 
        firstname = '$firstname', 
        lastname = '$lastname', 
        email= '$email', 
        password= '$password', 
        role = '$role',
        is_ban = '$is_ban'
        WHERE id = '$userID' ";

        $result = mysqli_query($conn, $query);

        if($result)
        {
            redirect('users.php', 'User updated successfully');
        }
        else
        {
            redirect('users-edit.php', 'Failed to update user');
        }
    }
    else
    {
        redirect('users-edit.php', 'Please fill all the fields');
    }
}

?>