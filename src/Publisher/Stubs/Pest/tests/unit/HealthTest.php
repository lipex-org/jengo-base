<?php

use Config\App;
use Tests\Support\Libraries\ConfigReader;

describe('Health Test', function() {
    it('has APPPATH defined', function() {
        expect(defined('APPPATH'))->toBeTrue();
    });

    it('has baseURL set in .env or app/Config/App.php', function() {
        $validation = service('validation');

        $env = false;

        // Check the baseURL in .env
        if (is_file(HOMEPATH . '.env')) {
            $env = preg_grep('/^app\.baseURL = ./', file(HOMEPATH . '.env')) !== false;
        }

        if ($env) {
            // BaseURL in .env is a valid URL?
            // phpunit.dist.xml sets app.baseURL in $_SERVER
            // So if you set app.baseURL in .env, it takes precedence
            $config = new App();
            expect($validation->check($config->baseURL, 'valid_url'))->toBeTrue();
        }

        // Get the baseURL in app/Config/App.php
        // You can't use Config\App, because phpunit.dist.xml sets app.baseURL
        $reader = new ConfigReader();

        // BaseURL in app/Config/App.php is a valid URL?
        expect($validation->check($reader->baseURL, 'valid_url'))->toBeTrue();
    });
});
