<?php

interface MugoVarnishBuilderInterface
{
    /**
     * @param $nodeId
     *
     * @return string[]
     */
    public function buildConditionForNodeIdCache($nodeId);

    /**
     * @return string
     */
    public function buildConditionForAllCache();
}
