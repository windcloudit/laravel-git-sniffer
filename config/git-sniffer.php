<?php

return array(
    // run the commands only in this environment
    'env' => 'local',

    // pre-commit command
    'precommit_command' => 'php artisan git-sniffer:check',

    // full path for phpcs bin
    'phpcs_bin' => './vendor/bin/phpcs',
    
    // full path for phpbf bin
    'phpcbf_bin' => './vendor/bin/phpcbf',

    // code standard to verify
    'standard' => './phpcs.xml',

    // file encoding
    'encoding' => 'utf-8',

    // valid file extensions to verify on phpcs
    'phpcs_extensions' => [
        'php'
    ],

    // phpcs ignore list
    'phpcs_ignore' => [
        //laravel view blade templates
        './resources/views/*',
        './app/Console/Commands/*'

    ],

    // temp dir to staged files
    'temp' => '',

    // Eslint specific config

    // full path for eslint bin
    'eslint_bin' => '',

    // eslint config file
    'eslint_config' => '',

    // valid file extensions to verify on eslint
    'eslint_extensions' => [
        'js'
    ],

    // eslint ignore list
    // add the value of your temp folder to properly ignore files
    // ex: !.tmp_staging (on the first line of you ignore file)
    'eslint_ignore_path' => '',
);
