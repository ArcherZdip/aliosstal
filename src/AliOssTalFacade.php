<?php

namespace ArcherZdip\AliOssTal;

use Illuminate\Support\Facades\Facade;

class AliOssTalFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'aliosstal';
    }
}