<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require $_SERVER['DOCUMENT_ROOT'] . '/capstone-admin/vendor/autoload.php';

function sendApprovalNotification($student_email, $firstname, $lastname, $document_type, $request_id) {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 0;
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

        $mail->Body = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Document Request Approved</title>
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f4f4;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                <!-- Header -->
                <tr>
                    <td style="padding: 20px; text-align: center; background-color: #2e7d32; border-top-left-radius: 10px; border-top-right-radius: 10px;">
                        <img src="https://9b67-120-29-78-198.ngrok-free.app/capstone-admin/assets/images/logo.png" alt="BPC Logo" width="80" height="76" style="display: block; margin: 0 auto;">
                        <h2 style="color: #ffffff; margin: 10px 0 0; font-size: 24px;">Bulacan Polytechnic College Registrar</h2>
                    </td>
                </tr>
                <!-- Body -->
                <tr>
                    <td style="padding: 30px; text-align: center;">
                        <h3 style="color: #2e7d32; margin: 0 0 15px; font-size: 20px;">Document Request Approved</h3>
                        <p style="color: #333333; font-size: 16px; line-height: 1.5; margin: 0 0 20px;">
                            Hi ' . htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($lastname, ENT_QUOTES, 'UTF-8') . ',<br>
                            Your request for <strong>' . htmlspecialchars($document_type, ENT_QUOTES, 'UTF-8') . '</strong> (Request ID: ' . htmlspecialchars($request_id, ENT_QUOTES, 'UTF-8') . ') has been approved and is now <strong>In Process</strong>.
                        </p>
                        <p style="color: #333333; font-size: 16px; line-height: 1.5; margin: 0 0 20px;">
                            We will notify you once your document is ready for pickup.
                        </p>
                    </td>
                </tr>
                <!-- Footer -->
                <tr>
                    <td style="padding: 20px; text-align: center; background-color: #f4f4f4; border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
                        <p style="color: #666666; font-size: 12px; margin: 0;">
                            © ' . date('Y') . ' Bulacan Polytechnic College. All rights reserved.<br>
                            If you did not request this document, please contact us immediately.
                        </p>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ';

        $mail->AltBody = "Hi " . htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8') . " " . htmlspecialchars($lastname, ENT_QUOTES, 'UTF-8') . ",\n\nYour request for " . htmlspecialchars($document_type, ENT_QUOTES, 'UTF-8') . " (Request ID: " . htmlspecialchars($request_id, ENT_QUOTES, 'UTF-8') . ") has been approved and is now In Process.\n\nWe will notify you once your document is ready for pickup.\n\nThank you,\nBPC Registrar Team";

        $mail->send();
        error_log("Approval email successfully sent to $student_email for Request ID: $request_id");
        return ['success' => true];
    } catch (Exception $e) {
        $error_message = "Approval email failed for $student_email: {$mail->ErrorInfo}";
        error_log($error_message);
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

function sendPickupNotification($student_email, $firstname, $lastname, $document_type, $request_id, $qr_file = null) {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 0;
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

        if ($qr_file && file_exists($qr_file)) {
            $mail->addEmbeddedImage($qr_file, 'qrcode', "request_$request_id.png");
            $qr_code_html = '<p style="color: #333333; font-size: 16px; line-height: 1.5; margin: 0 0 20px;"><strong>Scan this QR code at pickup for quick verification:</strong></p>
                            <p><img src="cid:qrcode" alt="Pickup QR Code" style="width: 150px; height: 150px;"></p>
                            <p style="color: #333333; font-size: 16px; line-height: 1.5; margin: 0 0 20px;">If you can\'t access the QR code, simply provide your name and student ID at the Registrar\'s Office.</p>';
        } else {
            $qr_code_html = '<p style="color: #333333; font-size: 16px; line-height: 1.5; margin: 0 0 20px;">When you arrive, please provide your name and student ID to collect your document.</p>';
        }

        $mail->Body = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Document Ready for Pickup</title>
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f4f4;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                <!-- Header -->
                <tr>
                    <td style="padding: 20px; text-align: center; background-color: #2e7d32; border-top-left-radius: 10px; border-top-right-radius: 10px;">
                        <img src="https://9b67-120-29-78-198.ngrok-free.app/capstone-admin/assets/images/logo.png" alt="BPC Logo" width="80" height="76" style="display: block; margin: 0 auto;">
                        <h2 style="color: #ffffff; margin: 10px 0 0; font-size: 24px;">Bulacan Polytechnic College Registrar</h2>
                    </td>
                </tr>
                <!-- Body -->
                <tr>
                    <td style="padding: 30px; text-align: center;">
                        <h3 style="color: #2e7d32; margin: 0 0 15px; font-size: 20px;">Document Ready for Pickup</h3>
                        <p style="color: #333333; font-size: 16px; line-height: 1.5; margin: 0 0 20px;">
                            Hi ' . htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($lastname, ENT_QUOTES, 'UTF-8') . ',<br>
                            Your request for <strong>' . htmlspecialchars($document_type, ENT_QUOTES, 'UTF-8') . '</strong> (Request ID: ' . htmlspecialchars($request_id, ENT_QUOTES, 'UTF-8') . ') is now <strong>Ready to Pickup</strong>.
                        </p>
                        <p style="color: #333333; font-size: 16px; line-height: 1.5; margin: 0 0 20px;">
                            Please visit the Registrar\'s Office during business hours to collect your document.
                        </p>
                        ' . $qr_code_html . '
                    </td>
                </tr>
                <!-- Footer -->
                <tr>
                    <td style="padding: 20px; text-align: center; background-color: #f4f4f4; border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
                        <p style="color: #666666; font-size: 12px; margin: 0;">
                            © ' . date('Y') . ' Bulacan Polytechnic College. All rights reserved.<br>
                            If you did not request this document, please contact us immediately.
                        </p>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ';

        $mail->AltBody = "Hi " . htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8') . " " . htmlspecialchars($lastname, ENT_QUOTES, 'UTF-8') . ",\n\nYour request for " . htmlspecialchars($document_type, ENT_QUOTES, 'UTF-8') . " (Request ID: " . htmlspecialchars($request_id, ENT_QUOTES, 'UTF-8') . ") is now Ready to Pickup.\n\nPlease visit the Registrar's Office during business hours to collect your document.\n\n" . ($qr_file && file_exists($qr_file) ? "Scan the attached QR code at pickup for quick verification, or provide your name and student ID.\n\n" : "Provide your name and student ID at the Registrar's Office.\n\n") . "Thank you,\nBPC Registrar Team";

        $mail->send();
        error_log("Email successfully sent to $student_email for Request ID: $request_id");
        return ['success' => true];
    } catch (Exception $e) {
        $error_message = "Email failed for $student_email: {$mail->ErrorInfo}";
        error_log($error_message);
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

function sendRejectionNotification($student_email, $firstname, $lastname, $document_type, $request_id, $rejection_reason) {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 0;
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
        $mail->Subject = 'Document Request Rejected - Bulacan Polytechnic College Registrar';

        $mail->Body = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Document Request Rejected</title>
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f4f4;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                <!-- Header -->
                <tr>
                    <td style="padding: 20px; text-align: center; background-color: #2e7d32; border-top-left-radius: 10px; border-top-right-radius: 10px;">
                        <img src="https://9b67-120-29-78-198.ngrok-free.app/capstone-admin/assets/images/logo.png" alt="BPC Logo" width="80" height="76" style="display: block; margin: 0 auto;">
                        <h2 style="color: #ffffff; margin: 10px 0 0; font-size: 24px;">Bulacan Polytechnic College Registrar</h2>
                    </td>
                </tr>
                <!-- Body -->
                <tr>
                    <td style="padding: 30px; text-align: center;">
                        <h3 style="color: #2e7d32; margin: 0 0 15px; font-size: 20px;">Document Request Rejected</h3>
                        <p style="color: #333333; font-size: 16px; line-height: 1.5; margin: 0 0 20px;">
                            Hi ' . htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($lastname, ENT_QUOTES, 'UTF-8') . ',<br>
                            Your request for <strong>' . htmlspecialchars($document_type, ENT_QUOTES, 'UTF-8') . '</strong> (Request ID: ' . htmlspecialchars($request_id, ENT_QUOTES, 'UTF-8') . ') has been rejected.
                        </p>
                        <p style="color: #333333; font-size: 16px; line-height: 1.5; margin: 0 0 20px;">
                            <strong>Reason for Rejection:</strong> ' . htmlspecialchars($rejection_reason, ENT_QUOTES, 'UTF-8') . '
                        </p>
                        <p style="color: #333333; font-size: 16px; line-height: 1.5; margin: 0 0 20px;">
                            Please contact the Registrar\'s Office for further details or to resubmit your request.
                        </p>
                    </td>
                </tr>
                <!-- Footer -->
                <tr>
                    <td style="padding: 20px; text-align: center; background-color: #f4f4f4; border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
                        <p style="color: #666666; font-size: 12px; margin: 0;">
                            © ' . date('Y') . ' Bulacan Polytechnic College. All rights reserved.<br>
                            If you did not request this document, please contact us immediately.
                        </p>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ';

        $mail->AltBody = "Hi " . htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8') . " " . htmlspecialchars($lastname, ENT_QUOTES, 'UTF-8') . ",\n\nYour request for " . htmlspecialchars($document_type, ENT_QUOTES, 'UTF-8') . " (Request ID: " . htmlspecialchars($request_id, ENT_QUOTES, 'UTF-8') . ") has been rejected.\n\nReason for Rejection: " . htmlspecialchars($rejection_reason, ENT_QUOTES, 'UTF-8') . "\n\nPlease contact the Registrar's Office for further details or to resubmit your request.\n\nThank you,\nBPC Registrar Team";

        $mail->send();
        error_log("Rejection email successfully sent to $student_email for Request ID: $request_id");
        return ['success' => true];
    } catch (Exception $e) {
        $error_message = "Rejection email failed for $student_email: {$mail->ErrorInfo}";
        error_log($error_message);
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}
?>