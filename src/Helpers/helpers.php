<?php

if (!function_exists('fincra')) {
    /**
     * Get Fincra instance
     *
     * @return \PrevailExcel\Fincra\Fincra
     */
    function fincra()
    {
        return app('laravel-fincra');
    }
}