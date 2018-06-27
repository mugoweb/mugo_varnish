<?php 

/**
 * @author Mugo
 *
 */
class StaticCacheMugoVarnish implements ezpStaticCache
{
    /**
     * @var MugoVarnishBuilderInterface
     */
    private $builder;

    protected $settings;
    
    static private $cleanUpHandlerRegistered = false;
    static protected $banConditions = array();

    protected $useContentSettingsCacheThresholdValue;
    
    public function __construct()
    {
        $this->settings = eZINI::instance( 'mugo_varnish.ini' );

        if( $this->settings->hasVariable( 'VarnishSettings', 'UseCacheThresholdValue' ) )
        {
            $this->useContentSettingsCacheThresholdValue = $this->settings->variable( 'VarnishSettings', 'UseCacheThresholdValue' ) == 'enabled';
        }

        if( $this->settings->hasVariable( 'PurgeUrlBuilder', 'BuilderClass' ) )
        {
            $builderClass = $this->settings->variable( 'PurgeUrlBuilder', 'BuilderClass' );
            if (class_exists($builderClass))
            {
                $builder = new $builderClass();
                if ($builder instanceof MugoVarnishBuilderInterface)
                {
                    $this->builder = $builder;
                }
            }
        }

        // set default builder class if missing settings
        if ($this->builder === null)
        {
            $this->builder = new MugoVarnishPageUrlBuilder();
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
            $doClearNodeList = true;
            if ($this->useContentSettingsCacheThresholdValue){
                $cleanupValue = eZContentCache::calculateCleanupValue( count( $nodeList ) );
                $doClearNodeList = eZContentCache::inCleanupThresholdRange( $cleanupValue );
            }
            if ( $doClearNodeList )
            {
                foreach( $nodeList as $nodeId )
                {
                    self::$banConditions = array_merge(
                        self::$banConditions,
                        $this->builder->buildConditionForNodeIdCache( $nodeId )
                    );
                }
            }
            else
            {
                $this->generateCache(true);
            }
        }
        
        eZDebug::accumulatorStop( 'StaticCacheMugo', '' );
    }
    
    
    /**
     * Purges all varnish cache
     *
     * @param bool $force If true then it will create all static caches even if it is not outdated.
     * @param bool $quiet If true then the function will not output anything.
     * @param eZCLI|false $cli The eZCLI object or false if no output can be done.
     * @param bool $delay
     *
     * @return bool
     */
    public function generateCache( $force = false, $quiet = false, $cli = false, $delay = true )
    {
        if( $force )
        {
            self::$banConditions[] = $this->builder->buildConditionForAllCache();
        }
        else
        {
            // Not sure if that function ever gets called without force
        }

        return false;
    }
    
    /**
     * Make interface happy
     *
     * @param string $url The URL to cache, e.g /news
     * @param int|false $nodeID The ID of the node to cache, if supplied it will also cache content/view/full/xxx.
     * @param bool $skipExisting If true it will not unlink existing cache files.
     * @param bool $delay
     */
    public function cacheURL( $url, $nodeID = false, $skipExisting = false, $delay = true )
    {}

    /**
     * Make interface happy
     * 
     * @param string $url The URL for the current item, e.g /news
     */
    public function removeURL( $url )
    {}

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
    
    static function executeActions()
    {}


    public static function purgeList()
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
                $purger->purge( $condition );
            }
        }
    }

    /**
     * Only for backward compatibility
     *
     * @deprecated
     *
     * @param integer $urls
     * @return array
     */
    protected function urls2BanConditions( $urls )
    {
        $builder = new MugoVarnishPageUrlBuilder();

        return $builder->urls2BanConditions( $urls );
    }

    /**
     * Only for backward compatibility
     *
     * @deprecated
     *
     * @param string $nodeId
     * @return array
     */
    protected function nodeId2Urls( $nodeId )
    {
        $builder = new MugoVarnishPageUrlBuilder();

        return $builder->nodeId2Urls( $nodeId );
    }

    /**
     * Only for backward compatibility
     *
     * @deprecated
     *
     * @param array $urls
     * @return array
     */
    protected function transformUrls( $urls )
    {
        $builder = new MugoVarnishPageUrlBuilder();

        return $builder->transformUrls( $urls );
    }

    /**
     * Only for backward compatibility
     *
     * @deprecated
     *
     * @param $url
     */
    protected function applyPreFixModifier( &$url )
    {
        $builder = new MugoVarnishPageUrlBuilder();
        $builder->applyPreFixModifier( $url );
    }
}

