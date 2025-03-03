<?php

require 'config/function.php';

if(isset($_POST['loginBtn']))
{
    $emailInput = validate($_POST['email']);
    $passwordInput = validate($_POST['password']);

    $email = filter_var($emailInput, FILTER_SANITIZE_EMAIL);
    $password = filter_var($passwordInput, FILTER_SANITIZE_STRING);

    if($email != '' && $password != '')
    {
        // Use prepared statements to prevent SQL injection
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result && mysqli_num_rows($result) == 1)
        {
            $row = mysqli_fetch_assoc($result);
            
            // Plaintext password comparison
            if($password === $row['password'])
            {
                if($row['is_ban'] == 1)
                {
                    redirect('index.php', 'Your account has been banned. Please contact admin.');
                }

                $_SESSION['auth'] = true;
                $_SESSION['loggedInUserRole'] = $row['role'];
                $_SESSION['loggedInUser'] = [
                    'firstname' => $row['firstname'],
                    'lastname' => $row['lastname'],
                    'email' => $row['email']
                ];

                // Redirect based on role
                switch ($row['role']) {
                    case 'admin':
                        redirect('admin/index.php', 'Welcome Admin');
                        break;
                    case 'staff':
                        redirect('staff/index.php', 'Welcome Staff');
                        break;
                    case 'cashier':
                        redirect('cashier/index.php', 'Welcome Cashier');
                        break;
                    case 'student':
                    case 'alumni':
                        redirect('dashboard.php', 'Welcome to the Student Dashboard');
                        break;
                    default:
                        redirect('index.php', 'Invalid Role');
                }
            }
            else
            {
                redirect('index.php', 'Invalid email or password. Please try again.');
            }
        }
        else
        {
            redirect('index.php', 'Invalid email or password. Please try again.');
        }
    }
    else
    {
        redirect('index.php', 'All fields are mandatory.');
    }
}

?>
