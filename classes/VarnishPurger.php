<?php 

class VarnishPurger
{
    /**
     * @return VarnishPurger
     */
    public static function Instance()
    {
        static $inst = null;
        if( $inst == null )
        {
            $inst = new VarnishPurger();
        }

        return $inst;
    }

    const CURL_DEBUG_OUTPUT_FILE = 'var/log/curldebug.log';
    const PURGES_LOG_FILE        = 'mugo_varnish_purges.log';
    
    private $debug = false;
    private $logPurges = false;
    private $varnishServers = array();
    private $baseCurlOptions = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PURGE',
        CURLOPT_HEADER         => true ,
        CURLOPT_NOBODY         => true,
        CURLOPT_CONNECTTIMEOUT => 1,
    );
    
    private function __construct()
    {
        $ini_varnish = eZINI::instance( 'mugo_varnish.ini' );
        $this->debug          = $ini_varnish->variable( 'VarnishSettings', 'DebugCurl' ) == 'enabled';
        $this->logPurges      = $ini_varnish->variable( 'VarnishSettings', 'LogPurges' ) == 'enabled';
        $this->varnishServers = $ini_varnish->variable( 'VarnishSettings', 'VarnishServers' );
        
        // override connection timeout
        if( $ini_varnish->variable( 'VarnishSettings', 'ConnectionTimeout' ) > -1 )
        {
            $this->baseCurlOptions[ CURLOPT_CONNECTTIMEOUT ] = $ini_varnish->variable( 'VarnishSettings', 'ConnectionTimeout' );
        }
        
        // make sure the log file exits
        if( !file_exists( self::CURL_DEBUG_OUTPUT_FILE ) )
        {
            eZFile::create( self::CURL_DEBUG_OUTPUT_FILE );
        }
    }
    
    private function __clone() {}
    
    /**
     * Purges a given list of conditions
     *
     * @param array $conditions
     * @return bool
     */
    public function purgeList( $conditions )
    {
        $curlRequests = array();
        
        if( !empty( $conditions ) && !empty( $this->varnishServers ) )
        {
            if( $this->debug )
            {
                $this->baseCurlOptions[ CURLOPT_VERBOSE ] = true;
                $this->baseCurlOptions[ CURLOPT_STDERR ] =
                    fopen( self::CURL_DEBUG_OUTPUT_FILE, 'a' );
            }
            
            foreach( $conditions as $i => $condition )
            {
                if( $condition )
                {
                    foreach( $this->varnishServers as $server_i => $varnishServer )
                    {
                        // build curl options
                        $options = $this->baseCurlOptions;
                        $options[ CURLOPT_HTTPHEADER ] = array( 'X-Ban-Condition:' . $condition );

                        // add curl request
                        $key = $server_i . '_' . $i;
                        $curlRequests[ $key ] = curl_init( $varnishServer );
                        curl_setopt_array( $curlRequests[ $key ], $options );
                    }
                }
                else
                {
                    eZDebug::writeError( 'Invalid ban request. URL: "' . $condition . '"', 'mugovarnish-general' );
                }
            }
                        
            if( !empty( $curlRequests ) )
            {
                // build the multi-curl handle
                $mh = curl_multi_init();
                
                // adding requests
                foreach( $curlRequests as $request )
                {
                    curl_multi_add_handle( $mh, $request );
                }

                // execute requests - not sure if I have to wait for it
                $running = null;
                do
                {
                    curl_multi_exec( $mh, $running );
                }
                while( $running );
                
                // close handles
                foreach( $curlRequests as $request )
                {
                    curl_multi_remove_handle( $mh, $request );
                }
                curl_multi_close( $mh );
                
                eZDebug::writeNotice( 'List of URLs purged.', 'mugovarnish-general' );

                $this->writePurgeLog( $conditions );
            }
            else
            {
                eZDebug::writeNotice( 'No URLs to purge.', 'mugovarnish-general' );
            }
            
            if( isset( $this->baseCurlOptions[ CURLOPT_STDERR ] ) )
            {
                fclose( $this->baseCurlOptions[ CURLOPT_STDERR ] );
            }
        }
        else
        {
            eZDebug::writeNotice( 'Nothing to purge: no URLs provided.', 'mugovarnish-general' );
        }
        
        return true;
    }

    /**
     * Purges a single URL or purges a given regular expression. If provided URL is a regular expression
     * make sure to submit $regular_expression = true
     *
     * @param $condition
     * @return bool
     */
    public function purge( $condition )
    {
        $return = true;

        if( !$condition )
        {
            eZDebug::writeError( 'Invalid ban request. URL: "' . $condition . '"', 'mugovarnish-general' );
            return false;
        }
        
        $curlOptionList = $this->baseCurlOptions;

        if( $this->debug )
        {
            $curlOptionList[ CURLOPT_VERBOSE ] = true;
            $curlOptionList[ CURLOPT_STDERR ]  = fopen( self::CURL_DEBUG_OUTPUT_FILE, 'a' );
        }

        if( !empty( $this->varnishServers ) )
        {
            foreach( $this->varnishServers as $varnishServer )
            {
                $curlHandler = curl_init( $varnishServer );
    
                $curlOptionList[ CURLOPT_HTTPHEADER ] = array( 'X-Ban-Condition:' . $condition );
                
                curl_setopt_array( $curlHandler, $curlOptionList );
          
                $result = curl_exec( $curlHandler );

                if( curl_errno( $curlHandler ) )
                {
                    $return = false;
                    
                    eZDebug::writeError( 'Curl Error: ' . curl_error( $curlHandler ) . ' ('. curl_errno( $curlHandler ) . ')' . ' Options: '. print_r( $curlOptionList, true ), 'mugovarnish-general' );
                }
            
                eZDebug::writeDebug( 'Purge:'. $condition . '". Success: ' . $return, 'mugovarnish-general' );

                if( $return !== false )
                {
                    $this->writePurgeLog( array( $condition ) );
                }

                curl_close( $curlHandler );
            }
        }
        
        if( $curlOptionList[ CURLOPT_STDERR ] !== false )
        {
            fclose( $curlOptionList[ CURLOPT_STDERR ] );
        }

        return $return;
    }

    private function writePurgeLog( array $conditions )
    {
        if( $this->logPurges )
        {
            foreach( $conditions as $condition )
            {
                $msg = 'Ban OK: ' . $condition;
                eZLog::write( $msg, self::PURGES_LOG_FILE );
            }
        }
    }
}
