<?php

namespace KitchenKiosk\Utility;
use \KitchenKiosk\Initialize;
use \KitchenKiosk\Database\Common;

/*
 * Legacy class -- keeping for reference until JWT is fully implemented
 * All security/password related functions
 */
class Security {

    // These constants may be changed without breaking existing hashes.
    const PBKDF2_HASH_ALGORITHM = "sha256";
    const PBKDF2_ITERATIONS = "1000";
    const PBKDF2_SALT_BYTE_SIZE = "24";
    const PBKDF2_HASH_BYTE_SIZE = "24";

    const HASH_SECTIONS = "4";
    const HASH_ALGORITHM_INDEX = "0";
    const HASH_ITERATION_INDEX = "1";
    const HASH_SALT_INDEX = "2";
    const HASH_PBKDF2_INDEX = "3";

    public function create_hash($password) {
        // format: algorithm:iterations:salt:hash
        $salt = base64_encode(mcrypt_create_iv($this->PBKDF2_SALT_BYTE_SIZE, MCRYPT_DEV_URANDOM));
        return $this->PBKDF2_HASH_ALGORITHM . ":" . $this->PBKDF2_ITERATIONS . ":" .  $salt . ":" .
            base64_encode($this->pbkdf2(
                $this->PBKDF2_HASH_ALGORITHM,
                $password,
                $salt,
                $this->PBKDF2_ITERATIONS,
                $this->PBKDF2_HASH_BYTE_SIZE,
                true
            ));
    }

    public function validate_password($password, $correct_hash) {
        $params = explode(":", $correct_hash);
        if(count($params) < $this->HASH_SECTIONS)
           return false;
        $pbkdf2 = base64_decode($params[$this->HASH_PBKDF2_INDEX]);
        return $this->slow_equals(
            $pbkdf2,
            $this->pbkdf2(
                $params[$this->HASH_ALGORITHM_INDEX],
                $password,
                $params[$this->HASH_SALT_INDEX],
                (int)$params[$this->HASH_ITERATION_INDEX],
                strlen($pbkdf2),
                true
            )
        );
    }

    // Compares two strings $a and $b in length-constant time.
    public function slow_equals($a, $b) {
        $diff = strlen($a) ^ strlen($b);
        for($i = 0; $i < strlen($a) && $i < strlen($b); $i++) {
            $diff |= ord($a[$i]) ^ ord($b[$i]);
        }
        return $diff === 0;
    }

    /*
     * PBKDF2 key derivation function as defined by RSA's PKCS #5: https://www.ietf.org/rfc/rfc2898.txt
     * $algorithm - The hash algorithm to use. Recommended: SHA256
     * $password - The password.
     * $salt - A salt that is unique to the password.
     * $count - Iteration count. Higher is better, but slower. Recommended: At least 1000.
     * $key_length - The length of the derived key in bytes.
     * $raw_output - If true, the key is returned in raw binary format. Hex encoded otherwise.
     * Returns: A $key_length-byte key derived from the password and salt.
     *
     * Test vectors can be found here: https://www.ietf.org/rfc/rfc6070.txt
     *
     * This implementation of PBKDF2 was originally created by https://defuse.ca
     * With improvements by http://www.variations-of-shadow.com
     */
    public function pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output = false) {
       $algorithm = strtolower($algorithm);
       if(!in_array($algorithm, hash_algos(), true))
           trigger_error('PBKDF2 ERROR: Invalid hash algorithm.', E_USER_ERROR);
       if($count <= 0 || $key_length <= 0)
           trigger_error('PBKDF2 ERROR: Invalid parameters.', E_USER_ERROR);

       if (function_exists("hash_pbkdf2")) {
           // The output length is in NIBBLES (4-bits) if $raw_output is false!
           if (!$raw_output) {
               $key_length = $key_length * 2;
           }
           return hash_pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output);
       }

       $hash_length = strlen(hash($algorithm, "", true));
       $block_count = ceil($key_length / $hash_length);

       $output = "";
       for($i = 1; $i <= $block_count; $i++) {
           // $i encoded as 4 bytes, big endian.
           $last = $salt . pack("N", $i);
           // first iteration
           $last = $xorsum = hash_hmac($algorithm, $last, $password, true);
           // perform the other $count - 1 iterations
           for ($j = 1; $j < $count; $j++) {
               $xorsum ^= ($last = hash_hmac($algorithm, $last, $password, true));
           }
           $output .= $xorsum;
       }

       if($raw_output)
           return substr($output, 0, $key_length);
       else
           return bin2hex(substr($output, 0, $key_length));
    }

    public function redirectToLogin($loginPage, $currPage=true){
        //simple function that redirects visitor to a login page
        //optionally sets the current page as a parameter

        if ( $currPage ){
            if ( strlen($_SERVER['REQUEST_URI']) > 1 ) {
                $loginPage .= "?redirect=" . $_SERVER['REQUEST_URI'];
            } else {
                $loginPage .= "?redirect=" . $_SERVER['PHP_SELF'];
            }
        }
        header("Location: " . $loginPage);
    }

    public function getSessionToken(){
        //generate a random and cryptographically secure session token
        return bin2hex(openssl_random_pseudo_bytes(16));
    }

    public function register($name, $username, $email, $password, $verification){
        //validate user input, and then create a new user if it's all good, finally log them in
        $main = Initialize::obtain();
        $DB = DB::pass();
        if ( $password != $verification ){
            return json_encode(array("status" => "false", "error" => "Password verification does not match"));
        }
        if ( !filter_var($email, FILTER_VALIDATE_EMAIL) ){
            return json_encode(array("status" => "false", "error" => "Invalid email address provided"));
        }
        $name = ucfirst($name);
        $pw = $this->create_hash($password);
        try {
            $res = $DB->insertUser($name, $username, $email, $pw);
        } catch ( DatabaseException $e ){
            $main->logEvent("Unable to create new user: " . $e->getMessage, "database.log");
            return json_encode(array("status" => "false", "error" => "Unable to create new user."));
        }
        return $this->login($username, $password, false, $_SERVER['REMOTE_ADDR']);
    }

    public function changePassword($username, $password, $verification){
        //validate the new password, then change it in the database
        $main = Initialize::obtain();
        $DB = DB::pass();
        if ( $password != $verification ){
            return json_encode(array("status" => "false", "error" => "Password verification does not match"));
        }
        $pw = $this->create_hash($password);
        //determine if we have a username or email
        if ( filter_var($username, FILTER_VALIDATE_EMAIL) ){
            try {
                $res = $DB->updatePassword($username, $pw, true);
            } catch ( DatabaseException $e ){
                $main->logEvent("Unable to change password: " . $e->getMessage, "database.log");
                return json_encode(array("status" => "false", "error" => "Unable to change password."));
            }
        } else {
            try {
                $res = $DB->updatePassword($username, $pw, false);
            } catch ( DatabaseException $e ){
                $main->logEvent("Unable to change password: " . $e->getMessage, "database.log");
                return json_encode(array("status" => "false", "error" => "Unable to change password."));
            }
        }
        return $this->login($username, $password, false, $_SERVER['REMOTE_ADDR']);
    }

    public function login($username, $password, $remember, $ip=NULL) {
        //validate login credentials; if correct create and return session key
        $main = Initialize::obtain();
        $DB = DB::pass();
        if ( filter_var($username, FILTER_VALIDATE_EMAIL) ){
            $m = 'email';
            try {
                $creds = $DB->getCredentialsByEmail($username);
            } catch ( DatabaseException $e ){
                $main->logEvent("Unable to retrieve credentials: " . $e->getMessage, "database.log");
                return json_encode(array("status" => "false", "error" => "Invalid username or password"));
            }
        } else {
            $m = 'username';
            try {
                $creds = $DB->getCredentialsByUsername($username);
            } catch ( DatabaseException $e ){
                $main->logEvent("Unable to retrieve credentials: " . $e->getMessage, "database.log");
                return json_encode(array("status" => "false", "error" => "Invalid username or password"));
            }
        }
        if ( !isset($password) ){
            return json_encode(array("status" => "false", "error" => "Invalid username or password"));
        }
        if ( $this->validate_password($password, $creds['password']) ){
            //now get a session key and insert it into the session table
            $token = $this->getSessionToken();
            if ( $m == 'email' ){
                $u = $DB->getUserByEmail($username);
            } else {
                $u = $DB->getUserByUsername($username);
            }
            foreach($u as $k => $v){
                $_SESSION[$k] = $v;
            }
            $_SESSION['token'] = $token;
            $_SESSION['logged_in'] = true;
            //see if remember me checkbox is checked
            $c = NULL;
            if ( $remember == "true" ){
                $c = $creds['username'] . ":" . $this->getSessionToken();
                $DB->saveCookie($creds['mid'], $c);
            }
            if ( $ip == NULL ){
                $ip = $_SERVER['REMOTE_ADDR'];
            }
            try {
                $DB->createSession($creds['mid'], $ip, $token, "1");
                return json_encode(array("status" => "true", "token" => $token, "username" => $creds['username'], "remember" => $c));
            } catch ( DatabaseException $e ){
                $main->logEvent("Unable to create session: " . $e->getMessage, "database.log");
                return json_encode(array("status" => "false", "error" => "Invalid username or password"));
            }
        } else {
            return json_encode(array("status" => "false", "error" => "Invalid username or password"));
        }
    }

    public function debugLogin($ips) {
        //emulate an "admin" user login based upon a preconfigured IP address
        $main = Initialize::obtain();
        $DB = DB::pass();
        $splt = explode(",",$ips);
        if ( in_array($_SERVER['REMOTE_ADDR'], $splt) ){ //our IP is in the allowed list
            //login as "admin"
            //now get a session key and insert it into the session table
            $token = $this->getSessionToken();
            $u = $DB->getUserByUsername("admin");
            foreach($u as $k => $v){
                $_SESSION[$k] = $v;
            }
            $_SESSION['token'] = $token;
            $_SESSION['logged_in'] = true;
            $c = $u['username'] . ":" . $this->getSessionToken();
            $DB->saveCookie($u['mid'], $c);
            $ip = $_SERVER['REMOTE_ADDR'];
            try {
                $DB->createSession($u['mid'], $ip, $token, "1");
                setcookie("token", $token);
                setcookie("username", $u['username']);
                return true;
            } catch ( DatabaseException $e ){
                return false;
            }
        } else {
            return false;
        }
    }

    public function loginWithToken($username, $token) {
        //set session with username and provided session key
        $main = Initialize::obtain();
        $DB = DB::pass();
        try {
            $DB->updateSession($token, "1");
        } catch ( DatabaseException $e ){
            $main->logEvent("Unable to update session expiration time: " . $e->getMessage, "database.log");
        }
        $_SESSION['username'] = $username;
        $_SESSION['token'] = $token;
        $_SESSION['logged_in'] = true;
        return true;
    }

    public function logout() {
        //unset the key session variables explicitly, destroy it, and remove cookie
        $main = Initialize::obtain();
        $DB = DB::pass();
        try {
            $DB->purgeSessionByUsername($_SESSION['username']);
        } catch ( DatabaseException $e ){
            $main->logEvent("Unable to purge sessions: " . $e->getMessage, "database.log");
        }
        unset($_SESSION['username']);
        unset($_SESSION['token']);
        unset($_SESSION['logged_in']);
        session_destroy();
        setcookie('token', '', time() - 3600);
        setcookie('username', '', time() - 3600);
        setcookie('remember', '', time() - 3600);
        return true;
    }

}

?>
