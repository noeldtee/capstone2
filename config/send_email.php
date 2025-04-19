<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require $_SERVER['DOCUMENT_ROOT'] . '/capstone-admin/vendor/autoload.php';

function sendPickupNotification($student_email, $firstname, $lastname, $document_type, $request_id, $qr_file = null) {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 0; // Set to SMTP::DEBUG_SERVER for debugging, 0 for production
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug [$level]: $str");
        };

        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Host = 'smtp.gmail.com';
        $mail->Username = 'bpcregistrar75@gmail.com';
        $mail->Password = 'nkei hmzy qpwn wzch';
        $mail->SMTPSecure = "tls";
        $mail->Port = 587;

        $mail->setFrom('bpcregistrar75@gmail.com', 'BPC Registrar');
        $mail->addAddress($student_email);
        $mail->isHTML(true);
        $mail->Subject = 'Document Ready for Pickup - Bulacan Polytechnic College Registrar';

        // Embed QR code if provided
        if ($qr_file && file_exists($qr_file)) {
            $mail->addEmbeddedImage($qr_file, 'qrcode', "request_$request_id.png");
            $qr_code_html = '<p><strong>Scan this QR code at pickup for quick verification:</strong></p>
                            <p><img src="cid:qrcode" alt="Pickup QR Code" style="width: 150px; height: 150px;"></p>
                            <p>If you can\'t access the QR code, simply provide your name and student ID at the Registrar\'s Office.</p>';
        } else {
            $qr_code_html = '<p>When you arrive, please provide your name and student ID to collect your document.</p>';
        }

        $mail->Body = "
            <h2>Document Ready for Pickup</h2>
            <p>Hi " . htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8') . " " . htmlspecialchars($lastname, ENT_QUOTES, 'UTF-8') . ",</p>
            <p>Your request for <strong>" . htmlspecialchars($document_type, ENT_QUOTES, 'UTF-8') . "</strong> (Request ID: " . htmlspecialchars($request_id, ENT_QUOTES, 'UTF-8') . ") is now <strong>Ready to Pickup</strong>.</p>
            <p>Please visit the Registrar's Office during business hours to collect your document.</p>
            $qr_code_html
            <p>Thank you,<br>BPC Registrar Team</p>
        ";

        $mail->send();
        error_log("Email successfully sent to $student_email for Request ID: $request_id");
        return ['success' => true];
    } catch (Exception $e) {
        $error_message = "Email failed for $student_email: {$mail->ErrorInfo}";
        error_log($error_message);
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

function sendApprovalNotification($student_email, $firstname, $lastname, $document_type, $request_id) {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 0; // Set to SMTP::DEBUG_SERVER for debugging, 0 for production
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug [$level]: $str");
        };

        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Host = 'smtp.gmail.com';
        $mail->Username = 'bpcregistrar75@gmail.com';
        $mail->Password = 'nkei hmzy qpwn wzch';
        $mail->SMTPSecure = "tls";
        $mail->Port = 587;

        $mail->setFrom('bpcregistrar75@gmail.com', 'BPC Registrar');
        $mail->addAddress($student_email);
        $mail->isHTML(true);
        $mail->Subject = 'Document Request Approved - Bulacan Polytechnic College Registrar';
        $mail->Body = "
            <h2>Document Request Approved</h2>
            <p>Hi " . htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8') . " " . htmlspecialchars($lastname, ENT_QUOTES, 'UTF-8') . ",</p>
            <p>Your request for <strong>" . htmlspecialchars($document_type, ENT_QUOTES, 'UTF-8') . "</strong> (Request ID: " . htmlspecialchars($request_id, ENT_QUOTES, 'UTF-8') . ") has been <strong>approved</strong> and is now <strong>In Process</strong>.</p>
            <p>We will notify you once your document is ready for pickup.</p>
            <p>Thank you,<br>BPC Registrar Team</p>
        ";

        $mail->send();
        error_log("Approval email successfully sent to $student_email for Request ID: $request_id");
        return ['success' => true];
    } catch (Exception $e) {
        $error_message = "Approval email failed for $student_email: {$mail->ErrorInfo}";
        error_log($error_message);
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}
?>