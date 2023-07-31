<?php

namespace Realtyna\Sync\Core;

/**
 * Manage Theme Providers
 * 
 * @final
 * @author Chris A  <chris.a@realtyna.net>
 * 
 * @version 1.0
 */
final class ThemeProviders
{

    /** 
     * Any new Theme Provider should be added here in this array
     * 
     * @static
     * @var array $providers contain theme providers classes 
     */
    static public $providers = [
        \Realtyna\Sync\Themes\Houzez\Core::class ,
        \Realtyna\Sync\Themes\WpResidence\Core::class ,
    ];

    /**
     * Get theme providers
     * 
     * @static
     * @author Chirs A <chris.a@realtyna.net>
     * 
     * @return array
     */
    static public function getProviders()
    {

        $providers = [];

        foreach( static::$providers as $theme ){

            if ( $theme ){

                $providers[] = [ "object" => $theme , 
                                 "name" => $theme::strtolowerThemeName() , 
                                 "url" => $theme::getThemeURL(),
                                 "active" => $theme::isActiveTheme() ];

            }

        }        
        
        return $providers;

    }
    
}
