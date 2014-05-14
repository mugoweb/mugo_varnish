<?php 

/**
 * @author Mugo
 *
 */
class StaticCacheMugoVarnish implements ezpStaticCache
{
	private $pathPrefixModifier = '';
	private $uriTransformation = false;
	private $urlModifierMatch = '';
	private $urlModifierReplace = '';
	private $omitUrlPatterns = array();
	private $purgeSystemUrls = false;
	
	static private $cleanUpHandlerRegistered = false;
	static public $urlsToPurge = array();
	
	public function __construct()
	{
		$ini = eZINI::instance( 'mugo_varnish.ini' );
		
		if( $ini->hasVariable( 'PurgeUrlBuilder', 'PathPrefixModifier' ) )
		{
			$this->pathPrefixModifier = $ini->variable( 'PurgeUrlBuilder', 'PathPrefixModifier' );
		}

		if( $ini->hasVariable( 'PurgeUrlBuilder', 'UriTransformation' ) )
		{
			$this->uriTransformation = $ini->variable( 'PurgeUrlBuilder', 'UriTransformation' ) == 'enabled' ? true : false;
		}
		
		if( $ini->hasVariable( 'PurgeUrlBuilder', 'ModifierMatch' ) )
		{
			$this->urlModifierMatch   = $ini->variable( 'PurgeUrlBuilder', 'ModifierMatch' );
			$this->urlModifierReplace = $ini->variable( 'PurgeUrlBuilder', 'ModifierReplace' );
		}

		if( $ini->hasVariable( 'PurgeUrlBuilder', 'OmitUrlPatterns' ) )
		{
			$this->omitUrlPatterns = $ini->variable( 'PurgeUrlBuilder', 'OmitUrlPatterns' );
		}

		if( $ini->hasVariable( 'PurgeUrlBuilder', 'PurgeSystemURL' ) )
		{
			$this->purgeSystemUrls = $ini->variable( 'PurgeUrlBuilder', 'PurgeSystemURL' ) == 'enabled' ? true : false;
		}
		
		// Register Cleanup Hanlder to purge urls at the end of the request
		if( ! self::$cleanUpHandlerRegistered )
		{
			self::$cleanUpHandlerRegistered = true;
	        eZExecution::addCleanupHandler( array( 'MugoVarnishCleanUpHandler', 'purgeList' ) );
		}
	}
	
	public function generateAlwaysUpdatedCache( $quiet = false, $cli = false, $delay = true )
	{}
	
	public function generateNodeListCache( $nodeList )
	{
		eZDebug::accumulatorStart( 'StaticCacheMugo', '', 'StaticCacheMugo' );

		if( !empty( $nodeList ) )
		{
			foreach( $nodeList as $nodeId )
			{
				$urls = $this->nodeId2Urls( $nodeId );

				foreach( $urls as $url )
				{
					if( $this->pathPrefixModifier )
					{
						$this->applyPreFixModifier( $url );
					}
					
					$key = md5( $url );
					
					if( !isset( self::$urlsToPurge[ $key ] ) )
					{
						if( $this->uriTransformation )
						{
							eZURI::transformURI( $url );
						}
						
						if( $this->urlModifierMatch )
						{
							$url = preg_replace( $this->urlModifierMatch, $this->urlModifierReplace, $url );
						}
						
						// exclude URLs based on configured patterns
						if( !empty( $this->omitUrlPatterns ) )
						{
							$matched = false;
							foreach( $this->omitUrlPatterns as $pattern )
							{
								$matched = preg_match( $pattern, $purgeUrl );
							}
							
							// skip it and process next URL
							if( $matched ) continue;
						}
							
						// add final version of URL to list
						self::$urlsToPurge[ $key ] = $url;
					}
				}
			}
		}
		
		eZDebug::accumulatorStop( 'StaticCacheMugo', '', 'StaticCacheMugo' );
	}
	
	
	/**
	 * Purges all varnish cache
	 * 
	 */
	public function generateCache( $force = false, $quiet = false, $cli = false, $delay = true )
	{
		if( $force )
		{
			$purger = VarnishPurger::Instance();
			return $purger->purge( '.*', true );
		}
		else
		{
			// Not sure if that function ever gets called without force
		}
	}
	
	/**
	 * Make interface happy
	 * 
	 * @param type $url
	 * @param type $nodeID
	 * @param type $skipExisting
	 * @param type $delay
	 */
	public function cacheURL( $url, $nodeID = false, $skipExisting = false, $delay = true )
	{}

	/**
	 * Make interface happy
	 * 
	 * @param type $url
	 */
	public function removeURL( $url )
	{}
	
	/**
	 * Generates a user hash for the current user.
	 * 
	 * @param string $newSession
	 * @return string
	 */
	public function getUserHash( $newSession )
	{
		$userDetails = eZUser::currentUser()->getUserCache();
		return md5( serialize( array( $userDetails[ 'roles' ], $userDetails[ 'role_limitations' ] ) ) );
	}
	
	private function nodeId2Urls( $nodeId )
	{
		$return = array();
		
		$elements = eZURLAliasML::fetchByAction( 'eznode', $nodeId, false, false, true );
		
		foreach( $elements as $element )
		{
			$return[] = '/' . $element->getPath();
		}
		
		if( $this->purgeSystemUrls )
		{
			$return[] = '/content/view/full/' . $nodeId;
		}
		
		return $return;
	}
		
	private function applyPreFixModifier( &$url )
	{
		$reg = '/^' . preg_quote( $this->pathPrefixModifier, '/' ) . '/';
		$url = preg_replace( $reg, '', $url );
	}
	
	static function executeActions()
	{}
	
	/**
	 *
	 */
	static function purgeList()
	{
		$ini_varnish = eZINI::instance( 'mugo_varnish.ini' );
		$maxBanRequests = $ini_varnish->variable( 'VarnishSettings', 'MaxBanRequests' );
		
		// Calculate max limit
		$limit = count( self::$urlsToPurge );
		if( $maxBanRequests )
		{
			if( count( self::$urlsToPurge ) > $maxBanRequests )
			{
				$limit = $maxBanRequests;
		
				eZDebug::writeWarning( 'Maximal ban requests limit exceeded.', 'mugovarnish-general' );
			}
			else
			{
				$limit = count( self::$urlsToPurge );
			}
		}
		
		$urlsToPurgeLimited = array_slice( self::$urlsToPurge, 0, $limit );

		// Call purger to do the job
		$purger = VarnishPurger::Instance();
		if( $ini_varnish->variable( 'VarnishSettings', 'UseCurlMultiHandler' ) == 'enabled' )
		{
			$purger->purgeList( $urlsToPurgeLimited );
		}
		else
		{
			foreach( $urlsToPurgeLimited as $url )
			{
				$purger->purge( $url );
			}
		}
	}
}
