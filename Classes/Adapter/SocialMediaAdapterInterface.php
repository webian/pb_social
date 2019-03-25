<?php
namespace PlusB\PbSocial\Adapter;

interface SocialMediaAdapterInterface
{
    /**
     * todo: quick fix - but we'd better add a layer for adapter in between, here after "return $this" instance is not completed but existing (AM)
     */
    public function validateAdapterSettings($parameter);
    public function getResultFromApi();
}
