<?php

require 'config/function.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
    <?php
            if(isset($page_title)){
                echo "$page_title";
            } 
        ?>
    </title>

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
</head>
<body style="background-image: url(assets/images/bpc.png);">
