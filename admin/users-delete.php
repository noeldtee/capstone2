<?php
include ('../config/function.php');

$paraResult = checkParamId('id');
if(is_numeric($paraResult)){

    $userID = validate($paraResult);
    $user = getById('users', $userID);
    
    if($user['status'] == 200)
    {
        $userDeleteRes = deleteQuery('users', $userID);
        
        if($userDeleteRes)
        {
            redirect('users.php', 'User deleted successfully');
        }
        else
        {
            redirect('users.php', 'Something went wrong.');
        }
    }
    else
    {
        redirect('users.php', $paraResult);
    }

}else{
    redirect('users.php', $paraResult);
}

?>