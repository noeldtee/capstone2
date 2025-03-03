<?php

if (isset($_SESSION['auth']))
{
    if(isset($_SESSION['loggedInUserRole'])){
        $role = validate($_SESSION['loggedInUserRole']);
        $email = validate($_SESSION['loggedInUser']['email']);        

        $query = "SELECT * FROM users WHERE email='$email' AND role='$role' LIMIT 1";
        $result = mysqli_query($conn, $query);

        if($result){
            if(mysqli_num_rows($result) == 0){

                logoutSession();
                redirect('../index.php', 'Unauthorized Access');
            }
            else{
                $row = mysqli_fetch_assoc($result);
                if($row['role'] != 'admin'){
                    logoutSession();
                    redirect('../index.php', 'Access Denied');
                }
            }
        }else{
            
            logoutSession();
            redirect('../index.php', 'Something went wrong');
        }
    }
    else{
        redirect('../index.php', 'Login to continue...');
    }
}

?>