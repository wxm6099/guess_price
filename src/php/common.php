<?php

/* get guess price config items */
if ( ! function_exists('get_gp_config') ) 
{
    function &get_gp_config()
    {
        static $_config;
        if (empty($_config))
        {
            $config_path = APPPATH.'config/GuessPrice_config.php';

            if(file_exists($config_path))
            {
                $_config = include($config_path);
            }
            else
            {
                $_config = array();
                print($config_path.' not exits');
            }
        }

        return $_config;
    }
}
else
{
    echo 'get_gp_config function_exists.<br>';
}
