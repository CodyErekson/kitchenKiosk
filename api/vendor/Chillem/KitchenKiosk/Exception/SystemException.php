<?php
// Format the message and pass into cLogger

namespace KitchenKiosk\Exception;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class SystemException extends \Exception {

        // want to make sure we don't screw up existing exception rules
        public function __construct($message, $level="30", $code = 0, Exception $previous = null) {
                parent::__construct($message, $code, $previous);

        }

}

?>
