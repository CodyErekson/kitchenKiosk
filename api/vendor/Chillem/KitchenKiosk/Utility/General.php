<?php
//general functions and such

namespace KitchenKiosk\Utility;

class General {

    /**
     *  Determine the suffix to append to the end of a number for display purposes
     *
     *  @param int $number Input number
     *
     *  @return string Number with ordinal suffix appended
     */
    public function ordinal_suffix($number){
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
     * @param int $length Length of output string
     * @param string $chars Character pool to draw from
     *
     * @return string
     */
    public function rand_string( $length=10, $chars="abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789" ) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $size = strlen( $chars );
        $str = "";
        for( $i = 0; $i < $length; $i++ ) {
            $str .= $chars[ rand( 0, $size - 1 ) ];
        }
        return $str;
    }

}
