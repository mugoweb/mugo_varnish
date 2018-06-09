<?php

class MugoVarnishPageUrlBuilder implements MugoVarnishBuilderInterface
{
    /**
     * @var eZINI
     */
    protected $settings;

    protected $pathPrefixModifier = '';

    protected $uriTransformation = false;

    protected $urlModifierMatch = '';

    protected $urlModifierReplace = '';

    protected $omitUrlPatterns = array();

    protected $purgeSystemUrls = false;

    public function __construct()
    {
        $this->settings = eZINI::instance( 'mugo_varnish.ini' );;
        if ($this->settings->hasVariable('PurgeUrlBuilder', 'PathPrefixModifier'))
        {
            $this->pathPrefixModifier = $this->settings->variable('PurgeUrlBuilder', 'PathPrefixModifier');
        }

        if ($this->settings->hasVariable('PurgeUrlBuilder', 'UriTransformation'))
        {
            $this->uriTransformation = $this->settings->variable('PurgeUrlBuilder', 'UriTransformation') == 'enabled' ? true : false;
        }

        if ($this->settings->hasVariable('PurgeUrlBuilder', 'ModifierMatch'))
        {
            $this->urlModifierMatch = $this->settings->variable('PurgeUrlBuilder', 'ModifierMatch');
            $this->urlModifierReplace = $this->settings->variable('PurgeUrlBuilder', 'ModifierReplace');
        }

        if ($this->settings->hasVariable('PurgeUrlBuilder', 'OmitUrlPatterns'))
        {
            $this->omitUrlPatterns = $this->settings->variable('PurgeUrlBuilder', 'OmitUrlPatterns');
        }

        if ($this->settings->hasVariable('PurgeUrlBuilder', 'PurgeSystemURL'))
        {
            $this->purgeSystemUrls = $this->settings->variable('PurgeUrlBuilder', 'PurgeSystemURL') == 'enabled' ? true : false;
        }

        return $this;
    }

    public function buildConditionForNodeIdCache($nodeId)
    {
        return $this->urls2BanConditions($this->nodeId2Urls($nodeId));
    }

    public function buildConditionForAllCache()
    {
        return '.*';
    }

    public function urls2BanConditions($urls)
    {
        $return = array();

        if (!empty($urls)) {
            foreach ($urls as $url)
            {
                $return[] = 'obj.http.X-Ban-Url ~ ^' . $url . '(/?\(.*)?$' . $this->getHostMatchingCondition();
            }
        }

        return $return;
    }

    protected function getHostMatchingCondition()
    {
        if ($this->settings->hasVariable('PurgeUrlBuilder', 'HostMatching'))
        {
            switch ($this->settings->variable('PurgeUrlBuilder', 'HostMatching'))
            {
                case 'SiteURL':
                    {
                        $ini = eZINI::instance();
                        $siteUrl = $ini->variable('SiteSettings', 'SiteURL');

                        if ($siteUrl) {
                            return ' && obj.http.X-Ban-Host ~ ' . preg_quote($siteUrl);
                        }
                    }
                    break;

                case 'RelatedSiteAccessList':
                    {

                    }
                    break;
            }
        }

        return false;
    }

    public function nodeId2Urls($nodeId)
    {
        /** @var eZURLAliasML[] $elements */
        $elements = eZURLAliasML::fetchByAction('eznode', $nodeId, false, false, true);

        $urls = array();
        foreach ($elements as $element)
        {
            $urls[] = '/' . $element->getPath();
        }

        if ($this->purgeSystemUrls) {
            $urls[] = '/content/view/full/' . $nodeId;
        }

        $transformedUrls = $this->transformUrls($urls);

        return $transformedUrls;
    }

    /**
     * @param array $urls
     *
     * @return array
     */
    public function transformUrls($urls)
    {
        foreach ($urls as $i => $url)
        {
            if ($this->pathPrefixModifier)
            {
                $this->applyPreFixModifier($url);
            }

            if ($this->uriTransformation)
            {
                eZURI::transformURI($url);
            }

            if ($this->urlModifierMatch)
            {
                $url = preg_replace($this->urlModifierMatch, $this->urlModifierReplace, $url);
            }

            // exclude URLs based on configured patterns - untested
            if (!empty($this->omitUrlPatterns))
            {
                foreach ($this->omitUrlPatterns as $pattern)
                {
                    if (preg_match($pattern, $url)) {
                        // skip it and process next URL
                        unset($urls[$i]);
                        continue 2;
                    }
                }
            }

            $urls[$i] = $url;
        }

        return $urls;
    }

    public function applyPreFixModifier(&$url)
    {
        $reg = '/^' . preg_quote($this->pathPrefixModifier, '/') . '/';
        $url = preg_replace($reg, '', $url);
    }
}
