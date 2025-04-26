<?php
require_once 'vendor/autoload.php'; // Path to Composer autoload
use Twilio\Rest\Client;

// Your Twilio credentials
$sid = "AC93317c16ab24251d856012fe13fafd3b"; // From Twilio dashboard
$token = "c6b606e33a993fdc02bcc961efdaa496"; // From Twilio dashboard
$twilio_number = "+17755082867"; // Your Twilio phone number
$recipient_number = "+63 997 218 1003"; // User's phone number
$message = "Hello! This is a test SMS from your capstone project.";

try {
    $client = new Client($sid, $token);
    $client->messages->create(
        $recipient_number,
        [
            'from' => $twilio_number,
            'body' => $message
        ]
    );
    echo "SMS sent successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>