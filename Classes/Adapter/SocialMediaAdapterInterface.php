<?php
namespace PlusB\PbSocial\Adapter;

interface SocialMediaAdapterInterface
{
    /**
     * todo: quickfix - but we better add a layer for adapter inbetween, here after "return $this" intance is not completet but existend (AM)
     */
    public function validateAdapterSettings($parameter);
    public function getResultFromApi();
}
