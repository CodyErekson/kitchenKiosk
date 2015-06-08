<?php
// Extend the exception class specifically for database calls

namespace KitchenKiosk\Exception;

class DatabaseException extends \Exception {

        // want to make sure we don't screw up existing exception rules
        public function __construct($message, $level="30", $code = 0, Exception $previous = null) {
                parent::__construct($message, $code, $previous);

        }

}

?>
