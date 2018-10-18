<?php
namespace PlusB\PbSocial\Adapter;

interface SocialMediaAdapterInterface
{

    public function validateAdapterSettings($parameter);
    public function getResultFromApi();
}
