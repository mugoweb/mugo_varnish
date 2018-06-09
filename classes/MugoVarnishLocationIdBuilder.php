<?php

class MugoVarnishLocationIdBuilder extends MugoVarnishPageUrlBuilder
{
    public function buildConditionForNodeIdCache($nodeId)
    {
        return ['obj.http.X-Location-Id ~ ' . (int)$nodeId . $this->getHostMatchingCondition()];
    }

    public function buildConditionForAllCache()
    {
        return 'obj.http.X-Ban-Url ~ ^/.*' . $this->getHostMatchingCondition();
    }
}
