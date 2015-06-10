<?php

namespace KitchenKiosk\Utility;

//TODO -- replace phplist support with swiftmailer here

/*
 * Utility class intended to handle all outgoing and incoming communication; ie mail
 */
class Communication {

    private $config;
    public $mail; //PHPMailer object
    public $error;

    private $main;

    public function __construct() {
        //get the path to and include the PHPMailer autoloader
        //$this->main = Initialize::obtain();
        //$this->config = $this->main->config;
        require_once $this->config->get('directories.root') . "/api/vendor/phpmailer/phpmailer/PHPMailerAutoload.php";
        $this->mail = new \PHPMailer;
    }

    public function send($name, $address, $message){
        try {
            $this->mail->isSMTP();
            $this->mail->SMTPDebug = 1;
            $this->mail->Host = $this->config->get('email.host');
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $this->config->get('email.username');
            $this->mail->Password = $this->config->get('email.password');
            $this->mail->SMTPSecure = 'ssl';
            $this->mail->Port = $this->config->get('email.port');
            $this->mail->From = $address;
            $this->mail->FromName = $name;
            $this->mail->addAddress($this->config->get('email.address'));
            $this->mail->addReplyTo($address, $name);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Message sent from PSASH contact form.';
            $this->mail->Body = $message;
            $this->mail->AltBody = strip_tags($message);
            if(!$this->mail->send()) {
                $this->error = $this->mail->ErrorInfo;
                $this->main->log->addWarning("Email error: " . $this->mail->ErrorInfo);
                return false;
            } else {
                return true;
            }
        } catch ( phpmailerException $e ){
            $this->main->log->addWarning("phpmailerException thrown: " . $e->errorMessage());
            return false;
        }
    }
}

?>
