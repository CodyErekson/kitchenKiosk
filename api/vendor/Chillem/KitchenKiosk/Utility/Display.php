<?php

namespace KitchenKiosk\Utility;

/*
 * Utility methods used to generate page or CLI display elements
 *
 * @class Display
 *
 */
class Display {

    /**
     * Clear the cli screen
     *
     * Return command line string that will clear the screen when printed; cli only
     * 
     * @access private
     *
     * @status 405 Method Not Allowed
     */
    public function cls() {
        array_map(create_function('$a', 'print chr($a);'), array(27, 91, 72, 27, 91, 50, 74));
    }

    /**
     * Get a cli color code by name
     *
     * Return string containing command line formatting functions to change text color and weight
     *
     * @param string $color Accepts: gray, green, yellow, blue, purple, white, bold, reset
     *
     * @return string
     */
    public function cliFormat($color){
        $map = [
            'gray'   => "\033[37m",
            'green'  => "\033[32m",
            'yellow' => "\033[93m",
            'blue'   => "\033[94m",
            'purple' => "\033[95m",
            'white'  => "\033[97m",
            'bold'   => "\033[1m",
            'reset'  => "\033[0m",
        ];
        if ( array_key_exists($color, $map) ){
            return $map[$color];
        }
        return false;
    }

    /**
     * Append ordinal suffix to number
     *
     * Determine the ordinal suffix (st, nd, th, etc) to append to the end of a number for display purposes
     *
     * @param int $number Input number
     *
     * @return string Number with ordinal suffix appended
     */
    public function ordinalSuffix($number){
        $ones = $number % 10;
        $tens = (int)floor( $number / 10 ) % 10;
        if ( $tens == 1 ) {
            $suff = "th";
        } else {
            switch ($ones){
                case 1:
                    $suff = "st";
                    break;
                case 2:
                    $suff = "nd";
                    break;
                case 3:
                    $suff = "rd";
                    break;
                default:
                    $suff = "th";
            }
        }
        return $number . $suff;
    }

    /**
     * Generate a random string
     *
     * Return a randomly generated string of the requested length using the allowed characters
     *
     * @param int $length Length of output string
     *
     * @param string $chars Character pool to draw from
     *
     * @return string
     */
    public function generateString( $length=10, $chars="abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789" ) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $size = strlen( $chars );
        $str = "";
        for( $i = 0; $i < $length; $i++ ) {
            $str .= $chars[ rand( 0, $size - 1 ) ];
        }
        return $str;
    }

}
