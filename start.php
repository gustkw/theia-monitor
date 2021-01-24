<?php
require('PHPMailer/PHPMailer.php');
require('PHPMailer/SMTP.php');
require('PHPMailer/Exception.php');



// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
// Instantiation and passing `true` enables exceptions
function send_email( $config,$subject ,$body,$to,$headers ){
  $mail = new PHPMailer(true);
  try {
      //$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
      $mail->isSMTP();                                            // Send using SMTP
      $mail->Host       = $config['Host'];                    // Set the SMTP server to send through
      $mail->SMTPAuth   =   $config['SMTPAuth'];                                   // Enable SMTP authentication
      $mail->Username   =   $config['Username'];                     // SMTP username
      $mail->Password   = $config['Password'];                               // SMTP password
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
      $mail->Port       = $config['Port'];                       // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above
      //Recipients
      $mail->setFrom($config['sendFrom'], 'Alert');
      foreach ($to as $toEmail) {
        $mail->addAddress($toEmail);
      }

      //$mail->addCC('cc@example.com');
      $mail->isHTML(true);                                  // Set email format to HTML
      $mail->Subject = $subject;
      $mail->Body    =$body;
      $mail->send();
      echo 'Message has been sent';
  } catch (Exception $e) {
      echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
  }
}
