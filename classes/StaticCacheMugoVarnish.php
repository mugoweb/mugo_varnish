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
    protected $settings;
    
    static private $cleanUpHandlerRegistered = false;
    static protected $banConditions = array();
    
    public function __construct()
    {
        $this->settings = eZINI::instance( 'mugo_varnish.ini' );
        
        if( $this->settings->hasVariable( 'PurgeUrlBuilder', 'PathPrefixModifier' ) )
        {
            $this->pathPrefixModifier = $this->settings->variable( 'PurgeUrlBuilder', 'PathPrefixModifier' );
        }

        if( $this->settings->hasVariable( 'PurgeUrlBuilder', 'UriTransformation' ) )
        {
            $this->uriTransformation = $this->settings->variable( 'PurgeUrlBuilder', 'UriTransformation' ) == 'enabled' ? true : false;
        }
        
        if( $this->settings->hasVariable( 'PurgeUrlBuilder', 'ModifierMatch' ) )
        {
            $this->urlModifierMatch   = $this->settings->variable( 'PurgeUrlBuilder', 'ModifierMatch' );
            $this->urlModifierReplace = $this->settings->variable( 'PurgeUrlBuilder', 'ModifierReplace' );
        }

        if( $this->settings->hasVariable( 'PurgeUrlBuilder', 'OmitUrlPatterns' ) )
        {
            $this->omitUrlPatterns = $this->settings->variable( 'PurgeUrlBuilder', 'OmitUrlPatterns' );
        }

        if( $this->settings->hasVariable( 'PurgeUrlBuilder', 'PurgeSystemURL' ) )
        {
            $this->purgeSystemUrls = $this->settings->variable( 'PurgeUrlBuilder', 'PurgeSystemURL' ) == 'enabled' ? true : false;
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

    /**
     * @param array $nodeList
     * @return bool|void
     */
    public function generateNodeListCache( $nodeList )
    {
        eZDebug::accumulatorStart( 'StaticCacheMugo', '', 'StaticCacheMugo' );

        if( !empty( $nodeList ) )
        {
            foreach( $nodeList as $nodeId )
            {
                self::$banConditions = array_merge(
                    self::$banConditions,
                    $this->urls2BanConditions( $this->nodeId2Urls( $nodeId ) )
                );
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
     * @param integer $urls
     * @return array
     */
    protected function urls2BanConditions( $urls )
    {
        $return = array();

        if( !empty( $urls ) )
        {
            foreach( $urls as $url )
            {
                $condition = 'obj.http.X-Ban-Url ~ ^'. $url .'(/?\(.*)?$';

                if( $this->settings->hasVariable( 'PurgeUrlBuilder', 'HostMatching' ) )
                {
                    switch( $this->settings->variable( 'PurgeUrlBuilder', 'HostMatching' ) )
                    {
                        case 'SiteURL':
                        {
                            $ini = eZINI::instance();
                            $siteUrl = $ini->variable( 'SiteSettings', 'SiteURL' );

                            if( $siteUrl )
                            {
                                $condition .= ' && obj.http.X-Ban-Host ~ ' . preg_quote( $siteUrl );
                            }
                        }
                        break;

                        case 'RelatedSiteAccessList':
                        {

                        }
                        break;
                    }
                }

                $return[] = $condition;
            }
        }

        return $return;
    }

    /**
     * Generates a user hash for the current user.
     * 
     * @return string
     */
    public function getUserHash()
    {
        $ini = eZINI::instance( 'mugo_varnish.ini' );
        $userDetails = eZUser::currentUser()->getUserCache();
        $hashArray = array( $userDetails[ 'roles' ], $userDetails[ 'role_limitations' ] );
        
        // To increase security, we can also use a once-daily timestamp to build the user hash
        if( $ini->variable( 'VarnishSettings', 'AppendTimestampToUserHash' ) == 'enabled' )
        {
            $hashArray[] = strtotime( 'today' );
        }
        return md5( serialize( $hashArray ) );
    }

    /**
     * @param string $nodeId
     * @return array
     */
    protected function nodeId2Urls( $nodeId )
    {
        $elements = eZURLAliasML::fetchByAction( 'eznode', $nodeId, false, false, true );

        $urls = array();
        foreach( $elements as $element )
        {
            $urls[] = '/' . $element->getPath();
        }
        
        if( $this->purgeSystemUrls )
        {
            $urls[] = '/content/view/full/' . $nodeId;
        }

        $transformedUrls = $this->transformUrls( $urls );

        return $transformedUrls;
    }

    /**
     * @param array $urls
     * @return array
     */
    protected function transformUrls( $urls )
    {
        foreach( $urls as $i => $url )
        {
            if( $this->pathPrefixModifier )
            {
                $this->applyPreFixModifier( $url );
            }

            if( $this->uriTransformation )
            {
                eZURI::transformURI( $url );
            }

            if( $this->urlModifierMatch )
            {
                $url = preg_replace( $this->urlModifierMatch, $this->urlModifierReplace, $url );
            }

            // exclude URLs based on configured patterns - untested
            if( !empty( $this->omitUrlPatterns ) )
            {
                foreach( $this->omitUrlPatterns as $pattern )
                {
                    if( preg_match( $pattern, $url ) )
                    {
                        // skip it and process next URL
                        unset( $urls[ $i ] );
                        continue 2;
                    }
                }
            }

            $urls[ $i ] = $url;
        }

        return $urls;
    }

    protected function applyPreFixModifier( &$url )
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
        $limit = count( self::$banConditions );
        if( $maxBanRequests )
        {
            if( $limit > $maxBanRequests )
            {
                $limit = $maxBanRequests;
        
                eZDebug::writeWarning( 'Maximal ban requests limit exceeded.', 'mugovarnish-general' );
            }
        }
        
        $banConditionsLimited = array_slice( self::$banConditions, 0, $limit );

        // Call purger to do the job
        $purger = VarnishPurger::Instance();
        if( $ini_varnish->variable( 'VarnishSettings', 'UseCurlMultiHandler' ) == 'enabled' )
        {
            $purger->purgeList( $banConditionsLimited );
        }
        else
        {
            foreach( $banConditionsLimited as $condition )
            {
                $purger->purge( $condition, true );
            }
        }
    }
}
