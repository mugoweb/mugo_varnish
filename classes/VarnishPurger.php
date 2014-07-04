<?php 

class VarnishPurger
{
	public static function Instance()
	{
		static $inst = null;
		if( $inst == null )
				$inst = new VarnishPurger();
		return $inst;
	}

	const CURL_DEBUG_OUTPUT_FILE = 'var/log/curldebug.log';
	
	private $debug = false;
	private $varnishServers = array();
	private $baseCurlOptions = array(
	                              CURLOPT_RETURNTRANSFER => true,
	                              CURLOPT_CUSTOMREQUEST  => 'PURGE',
	                              CURLOPT_HEADER         => true ,
	                              CURLOPT_NOBODY         => true,
	                              CURLOPT_CONNECTTIMEOUT => 1
	                            );
	
	private function __construct()
	{
		$ini_varnish = eZINI::instance( 'mugo_varnish.ini' );
		$this->debug               = $ini_varnish->variable( 'VarnishSettings', 'DebugCurl' ) == 'enabled';
		$this->varnishServers      = $ini_varnish->variable( 'VarnishSettings', 'VarnishServers' );
		
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
	
	/**
	 * Purges a given list of URLs
	 * 
	 */
	public function purgeList( $urls )
	{
		$curlRequests = array();
		
		if( !empty( $urls ) && !empty( $this->varnishServers ) )
		{
			foreach( $urls as $url_i => $url )
			{
				if( $url )
				{
					foreach( $this->varnishServers as $server_i => $varnishServer )
					{
						// build curl options
						$options = $this->baseCurlOptions;
						$options[ CURLOPT_HTTPHEADER ] = array( 'X-Purge-Url' . ': ' . $url );
						
						// add curl request
						$key = $server_i . '_' . $url_i;
						$curlRequests[ $key ] = curl_init( $varnishServer );
						curl_setopt_array( $curlRequests[ $key ], $options );
					}
				}
				else
				{
					eZDebug::writeError( 'Invalid ban request. URL: "' . $url . '"', 'mugovarnish-general' );
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
			}
			else
			{
				eZDebug::writeNotice( 'No URLs to purge.', 'mugovarnish-general' );
			}
		}
		else
		{
			eZDebug::writeNotice( 'No URLs provided.', 'mugovarnish-general' );
		}
		
		return true;
	}
	
	/**
	 * Purges a single URL or purges a given regular expression. If provided URL is a regular expression
	 * make sure to submit $regular_expression = true
	 */
	public function purge( $url, $regular_expression = false )
	{
		$return = true;

		if( !$url )
		{
			eZDebug::writeError( 'Invalid ban request. URL: "' . $url . '"', 'mugovarnish-general' );
			return false;
		}
		
		$http_header = $regular_expression ? 'X-Purge-Reg' : 'X-Purge-Url';
		
		$curlOptionList = $this->baseCurlOptions;

		$fd = false;
		if( $this->debug )
		{
			$fd = fopen( self::CURL_DEBUG_OUTPUT_FILE, 'w' );
			$curlOptionList[ CURLOPT_VERBOSE ] = true;
			$curlOptionList[ CURLOPT_STDERR ]  = $fd;
		}

		if( !empty( $this->varnishServers ) )
		{
			foreach( $this->varnishServers as $varnishServer )
			{
				$curlHandler = curl_init( $varnishServer );
	
				$curlOptionList[ CURLOPT_HTTPHEADER ] = array( $http_header . ': ' . $url );
				
				curl_setopt_array( $curlHandler, $curlOptionList );
		  
				$result = curl_exec( $curlHandler );

				if( curl_errno( $curlHandler ) )
				{
					$return = false;
					
					eZDebug::writeError( 'Curl Error: ' . curl_error( $curlHandler ) . ' ('. curl_errno( $curlHandler ) . ')' . ' Options: '. print_r( $curlOptionList, true ), 'mugovarnish-general' );
				}
			
				eZDebug::writeNotice( 'Purge as '. $http_header .': "' . $url . '". Success: ' . $return, 'mugovarnish-general' );

				curl_close( $curlHandler );
			}
		}
		
		if( $fd !== false )
		{
			fclose( $fd );
		}

		return $return;;
	}
}