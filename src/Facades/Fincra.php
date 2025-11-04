<?php

namespace PrevailExcel\Fincra\Facades;

use Illuminate\Support\Facades\Facade;

class Fincra extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laravel-fincra';
    }
}