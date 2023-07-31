<?php

namespace Realtyna\Sync\Core;

/**
 * Abstract theme structure
 * 
 * @abstract
 * @author Chris A <chris.a@realtyna.net>
 * 
 * @version 1.0
 */
abstract class Theme
{

    /** 
     * @var string Theme Name 
     * @static
     */
    static public $name;

    
    /** 
     * @var string Theme URL
     * @static
     */
    static public $url;

    /**
     * @var string Theme Requirements 
     * @static
     * @abstract
     */
    static public $requirements;

    /**
     * Get Theme Name entered in class
     * @author Chris A <chris.a@realtyna.net>
     *
     * @return string
     */
    static public function getThemeName()
    {
        
        return static::$name;

    }

    /**
     * Get Theme URL entered in class
     * @author Chris A <chris.a@realtyna.net>
     *
     * @return string
     */
    static public function getThemeURL()
    {
        
        return static::$url;

    }

    /**
     * Get Theme Name entered in class in lowercase
     * @author Chris A <chris.a@realtyna.net>
     *
     * @return string|bool
     */
    static public function strtolowerThemeName()
    {

        return static::getThemeName() ? \strtolower( static::getThemeName() ) : false ;

    }

    /**
     * Get Current Active Theme Name in Wordpress
     * @author Chris A <chris.a@realtyna.net>
     *
     * @return string
     */
    static public function getCurrentTheme()
    {

        if ( function_exists( 'wp_get_theme' ) ){

            $wpTheme = wp_get_theme();
                        
            return $wpTheme->get( 'Name' ) ;

        }

        return '';

    }

    /**
     * Get lowercase of Current Active Theme Name in Wordpress
     * @author Chris A <chris.a@realtyna.net>
     *
     * @return string|bool
     */
    static public function strtolowerCurrentTheme()
    {

        return static::getCurrentTheme() ? \strtolower( static::getCurrentTheme() ) : false ;

    }

    /**
     * Check if the theme is active
     * @author Chris A <chris.a@realtyna.net>
     *
     * @return string
     */
    static public function isActiveTheme()
    {

        return \substr( static::strtolowerCurrentTheme() , 0 , \strlen( static::strtolowerThemeName() ) ) == static::strtolowerThemeName()  ;

    }

}