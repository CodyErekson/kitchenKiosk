<?php
    // Database handler class; requires a PDO database handle

namespace KitchenKiosk\Database;

use KitchenKiosk\Exception\DatabaseException;

class DB {

    public $DB; // Database handle
    public $error = array();

    //let's connect to the database here
    private function __construct(PDO $DB ) {
        $this->DB = $DB;
    }

    public function __call($name, $arguments) {
        throw new \BadMethodCallException("Method " . $name . " does not exist, " . print_r($arguments, 1));
    }

    /***** PUBLIC FUNCTIONS *******/

    public function loadConfig(){
        //load any config options stored in the database
        try {
            $sth = $this->dbh->prepare("SELECT * FROM config");
            $sth->execute();
            $result = $sth->fetchAll(\PDO::FETCH_ASSOC);
            $sth->closeCursor();
            return $result;
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage(), "20");
        }
    }

    public function getCredentialsByUsername($username){
        //get the info needed to validate login when we have a username
        try {
            $sth = $this->dbh->prepare("SELECT mid, username, email, password FROM user WHERE username=:username AND enabled='1'");
            $sth->bindValue(":username", $username);
            $sth->execute();
            $row = $sth->fetch(\PDO::FETCH_ASSOC);
            $sth->closeCursor();
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage(), "20");
        }
        return $row;
    }

    public function getCredentialsByEmail($email){
        //get the info needed to validate login when we have an email
        try {
            $sth = $this->dbh->prepare("SELECT mid, username, password FROM user WHERE email=:email AND enabled='1'");
            $sth->bindValue(":email", $email);
            $sth->execute();
            $row = $sth->fetch(\PDO::FETCH_ASSOC);
            $sth->closeCursor();
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage(), "20");
        }
        return $row;
    }

    public function getUserByUsername($username){
        //load general data for a user
        try {
            $sth = $this->dbh->prepare("SELECT mid, name, username, email FROM user WHERE username=:username");
            $sth->bindValue(":username", $username);
            $sth->execute();
            $row = $sth->fetch(\PDO::FETCH_ASSOC);
            $sth->closeCursor();
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage(), "20");
        }
        return $row;
    }

    public function getUserByEmail($email){
        //load general data for a user
        try {
            $sth = $this->dbh->prepare("SELECT mid, name, username, email FROM user WHERE email=:email");
            $sth->bindValue(":email", $email);
            $sth->execute();
            $row = $sth->fetch(\PDO::FETCH_ASSOC);
            $sth->closeCursor();
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage(), "20");
        }
        return $row;
    }

    public function getUserById($mid){
        //load general data for a user
        try {
            $sth = $this->dbh->prepare("SELECT mid, name, username, email FROM user WHERE mid=:mid");
            $sth->bindValue(":mid", $mid);
            $sth->execute();
            $row = $sth->fetch(\PDO::FETCH_ASSOC);
            $sth->closeCursor();
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage(), "20");
        }
        return $row;
    }

    public function insertUser($name, $username, $email, $password){
        //insert a new user
        try {
            $sth = $this->dbh->prepare("INSERT INTO user (name, username, email, password, created) VALUES(:name, :username, :email, :password, NOW())");
            $sth->bindValue(":name", $name);
            $sth->bindValue(":username", $username);
            $sth->bindValue(":email", $email);
            $sth->bindValue(":password", $password);
            $sth->execute();
            $sth->closeCursor();
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage(), "20");
        }
        return $this->dbh->lastInsertId();
    }

    public function getAllUsers(){
        //get all users
        try {
            $sth = $this->dbh->prepare("SELECT * FROM user");
            $sth->execute();
            $result = $sth->fetchAll(\PDO::FETCH_ASSOC);
            if ( count($result) == 0 ){
                return false;
            }
            $sth->closeCursor();
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage(), "20");
        }
        return $result;
    }

    public function updatePassword($username, $password, $email=false){
        //update the user's password
        try {
            if ( $email ){
                $sth = $this->dbh->prepare("UPDATE user SET password=:password WHERE email=:username");
            } else {
                $sth = $this->dbh->prepare("UPDATE user SET password=:password WHERE username=:username");
            }
            $sth->bindValue(":username", $username);
            $sth->bindValue(":password", $password);
            $sth->execute();
            $sth->closeCursor();
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage(), "20");
        }
        return true;
    }

    public function toggleUserStatus($mid, $enabled){
        //enable or disable a user
        try {
            $sth = $this->dbh->prepare("UPDATE user SET enabled=:enabled WHERE mid=:mid");
            $sth->bindValue(":enabled", $enabled);
            $sth->bindValue(":mid", $mid);
            $sth->execute();
            $sth->closeCursor();
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage(), "20");
        }
        return true;
    }

    public function createSession($mid, $ip, $token, $time){
        //insert a session token by member id; time is how long in hours session is valid
        //first delete all expired sessions belonging to this mid
        $this->purgeExpiredSession($mid);
        try {
            if ( $time == "1" ){
                $time = "1 HOUR";
            } else {
                $time .= " HOURS";
            }
            $sth = $this->dbh->prepare("INSERT INTO session (mid, ip, expires, token) VALUES(:mid, :ip, ( NOW() + INTERVAL " . $time . " ), :token)");
            $sth->bindValue(":mid", $mid);
            $sth->bindValue(":ip", $ip);
            $sth->bindValue(":token", $token);
            $sth->execute();
            $sth->closeCursor();
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage(), "20");
        }
        return $this->dbh->lastInsertId();
    }

    public function updateSession($token, $time){
        //update an existing session token by token; time is how long in hours session is valid
        try {
            if ( $time == "1" ){
                $time = "1 HOUR";
            } else {
                $time .= " HOURS";
            }
            $sth = $this->dbh->prepare("UPDATE session SET expires=( NOW() + INTERVAL " . $time . " ) WHERE token=:token");
            $sth->bindValue(":token", $token);
            $sth->execute();
            $sth->closeCursor();
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage(), "20");
        }
        return true;
    }

    public function validateToken($username, $token){
        //check if the token in the cookie is still valid for the given user
        try {
            $sth = $this->dbh->prepare("SELECT COUNT(s.*) AS count FROM session s JOIN user u USING (mid) WHERE u.username=:username AND s.token=:token AND s.expires>NOW()");
            $sth->bindValue(":username", $username);
            $sth->bindValue(":token", $token);
            $sth->execute();
            $row = $sth->fetch(\PDO::FETCH_ASSOC);
            if ( (int)$row['count'] != 1 ){
                return false;
            }
            $sth->closeCursor();
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage(), "20");
        }
        return true;
    }

    public function purgeExpiredSession($mid=null){
        //delete all of the expired sessions; optionally filter by user id
        try {
            if ( $mid ){
                $sth = $this->dbh->prepare("DELETE s, c FROM session s JOIN cookies c USING (mid) WHERE s.mid=:mid AND s.expires<NOW()");
                $sth->bindValue(":mid", $mid);
            } else {
                $sth = $this->dbh->prepare("DELETE s, c FROM session s JOIN cookies c USING (mid) WHERE s.expires<NOW()");
            }
            $sth->execute();
            $sth->closeCursor();
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage(), "20");
        }
        return true;
    }

    public function purgeSessionByUsername($username){
        //delete all sessions and cookies based upon username only
        try {
            $sth = $this->dbh->prepare(" DELETE s, c FROM session s JOIN cookies c USING (mid) JOIN user u USING (mid) WHERE u.username=:username");
            $sth->bindValue(":username", $username);
            $sth->execute();
            $sth->closeCursor();
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage(), "20");
        }
        return true;
    }

    public function saveCookie($mid, $cookie){
        //insert a cookie string by member id that will be saved in the database and used later as a one-time authentication string
        try {
            $sth = $this->dbh->prepare("INSERT INTO cookies (mid, cookie) VALUES(:mid, :cookie)");
            $sth->bindValue(":mid", $mid);
            $sth->bindValue(":cookie", $cookie);
            $sth->execute();
            $sth->closeCursor();
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage(), "20");
        }
        return $this->dbh->lastInsertId();
    }

    public function validateCookie($username, $cookie){
        //check if the cookie provided is still valid for the given user; if so we will allow them in, generate a new one and provide that
        try {
            $sth = $this->dbh->prepare("SELECT COUNT(c.*) AS count, c.mid AS mid FROM cookie c JOIN user u USING (mid) WHERE u.username=:username AND c.cookie=:cookie");
            $sth->bindValue(":username", $username);
            $sth->bindValue(":cookie", $cookie);
            $sth->execute();
            $row = $sth->fetch(\PDO::FETCH_ASSOC);
            if ( (int)$row['count'] != 1 ){
                return false;
            }
            $mid = $row['mid'];
            $sth->closeCursor();
            //we get here, it was valid
            $sth = $this->dbh->prepare("DELETE c.* FROM cookie c JOIN user u USING (mid) WHERE u.username=:username AND c.cookie=:cookie");
            $sth->bindValue(":username", $username);
            $sth->bindValue(":cookie", $cookie);
            $sth->execute();
            $sth->closeCursor();
            $nc = SecurityUtil::getSessionToken();
            $this->saveCookie($mid, $nc);
            $ret = json_encode(array("status" => "true", "cookie" => $nc));
        } catch ( \PDOException $e ){
            throw new DatabaseException(__CLASS__ . " " . __METHOD__ . " " . $e->getMessage(), "20");
        }
        return $ret;
    }

}
