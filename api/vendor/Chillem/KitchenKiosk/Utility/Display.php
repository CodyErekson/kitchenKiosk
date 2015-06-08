<?php
//functions used to generate page display elements

namespace KitchenKiosk\Utility;

class Display {

    /**
     * Clear the cli screen
     * 
     * @status 405 Method Not Allowed
     */
    public function cls() {
        array_map(create_function('$a', 'print chr($a);'), array(27, 91, 72, 27, 91, 50, 74));
    }

    /**
     * Get a cli color code by name
     *
     * @param string $color
     *
     * @return string
     */
    public function color($color){
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
     * Create a thumbnail display
     *
     * @param string $picture Path to image file
     *
     * @param string $caption Optional caption
     *
     * @return string
     */
    public function displayThumbnail($picture, $caption=false){
        //create a thumbnail display
        $main = Initialize::obtain();
        $DB = DB::pass();
        $ppath = $main->config->get('directories', 'pictures');

        $ret = "<div class=\"col-lg-3 col-md-6 hero-feature\">
                <div class=\"thumbnail\">
                    <img class=\"frnt-thmb\" src=\"" . $ppath . "/" . $picture['generated'] . "\" title=\"" . $picture['filename'] . "\" data-toggle=\"lightbox\" data-remote=\"" . $ppath . "/" . $picture['generated'] . "\" />";
        if ( $caption ){
            $ret .= "   <div class=\"caption\">
                        <h3>" . $caption . "</h3>";
        }
        $ret .= "   </div>
                </div>
            </div>";
        return $ret;
    }

}
