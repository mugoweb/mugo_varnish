<?php

class mugo_varnishInfo
{
    /**
     * info
     *
     * @access public
     * @return void
     */
    static function info()
    {
        return array( 'Name'      => "<a href='http://www.mugo.ca'>Mugo Varnish Purge</a>",
                      'Version'   => '1.0',
                      'Copyright' => "Copyright (C) <a href='http://www.mugo.ca'>Mugo Web</a>",
                      'License'   => 'GNU General Public License v2.0',
                    );
    }
}

?>