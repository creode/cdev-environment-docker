<?php
namespace Cdev\Docker\Environment\Command\Container;

class Php extends Container
{
    const COMMAND_NAME = 'container:php:configure';
    const COMMAND_DESC = 'Configures the PHP container';
    const CONFIG_FILE = 'php.yml';
    const CONFIG_NODE = 'php';

    protected $_config = 
    [
        'active' => true,
        'container_name' => 'project_php',
        'relative_webroot_dir' => '',
        'ports' => [
            '80:80'
        ],
        'environment' => [
            'VIRTUAL_HOST' => '.project.docker'
        ],
        'volumes' => [
            ['../src:/var/www/html']
        ]
    ];

    private $_syncConfig = [
        'sync' => [
            'name' => 'project-website-code-sync',
            'default' => [
                'src' => '../src',
                'sync_userid' => 1000, # www-data
                'sync_strategy' => 'unison',
                'sync_excludes' => [
                    '.sass-cache',
                    'sass',
                    'sass-cache',
                    'bower.json',
                    'package.json',
                    'Gruntfile',
                    'bower_components',
                    'node_modules',
                    '.gitignore',
                    '.git',
                    '*.scss',
                    '*.sass'
                ]
            ]
        ],
        'volumes' => [
            ['syncname:/var/www/html:nocopy']
        ]
    ];

    protected function askQuestions()
    {
        $path = $this->_input->getOption('path');
        $src = $this->_input->getOption('src');
        $dockername = $this->_input->getOption('name');
        $dockerport = $this->_input->getOption('port');
        $volumeName = $this->_input->getOption('volume');

        // TODO: What if there are multiple sites? Can we setup multiple PHP containers
        // usage example will be Drupal sites where clearing cache doesn't do all sites
        $this->buildOrImage(
            '../vendor/creode/docker/images/php/7.0',
            'creode/php-apache:7.2',
            $this->_config,
            [   // builds
                '../vendor/creode/docker/images/php/7.0' => 'PHP 7.2',
                '../vendor/creode/docker/images/php/7.0' => 'PHP 7.1',
                '../vendor/creode/docker/images/php/7.0' => 'PHP 7.0',
                '../vendor/creode/docker/images/php/5.6' => 'PHP 5.6',
                '../vendor/creode/docker/images/php/5.6-ioncube' => 'PHP 5.6 with ionCube',
                '../vendor/creode/docker/images/php/5.3' => 'PHP 5.3'
            ],
            [   // images
                'creode/php-apache:7.2' => 'PHP 7.2',
                'creode/php-apache:7.1' => 'PHP 7.1',
                'creode/php-apache:7.0' => 'PHP 7.0',
                'creode/php-apache:5.6' => 'PHP 5.6',
                'creode/php-apache:5.6-ioncube' => 'PHP 5.6 with ionCube',
                'creode/php-apache:5.3' => 'PHP 5.3'
            ]
        );

        $this->_config['container_name'] = $dockername . '_php';

        $this->_config['ports'] = ['3' . $dockerport . ':80'];

        $this->_config['environment']['VIRTUAL_HOST'] = '.' . $dockername . '.docker';


        $useCustomWebroot = false;

        $this->askYesNoQuestion(
            'Use custom webroot',
            $useCustomWebroot
        );

        if ($useCustomWebroot) {
            $this->_editCustomWebroot();
        }

        $editEnvironmentVariables = false;

        $this->askYesNoQuestion(
            'Edit environment variables',
            $editEnvironmentVariables
        );

        if ($editEnvironmentVariables) {
            $this->_editEnvironmentVariables();
        }

        $this->_config['links'] = []; 

        if ($volumeName) {
            $this->_config['volumes'] = [$volumeName . ':/var/www/html:nocopy'];
        } else {
            $this->_config['volumes'] = ['../' . $src . ':/var/www/html'];
        }
    }

    private function _editEnvironmentVariables()
    {
        if (isset($this->_config['environment']) && count($this->_config['environment']) > 1) {
            $this->_removeEnvironmentVariables();
        }

        $this->_addEnvironmentVariables();
    }

    private function _removeEnvironmentVariables()
    {
        $removeEnvironmentVariables = false;

        $this->askYesNoQuestion(
            'Remove environment variables',
            $removeEnvironmentVariables
        );

        if (!$removeEnvironmentVariables) {
            return;
        }

        foreach($this->_config['environment'] as $varName => $value) {
            // don't let them remove the virtual host name
            if ($varName == 'VIRTUAL_HOST') {
                continue;
            }

            $removeEnvVar = false;

            $this->askYesNoQuestion(
                'Remove ' . $varName,
                $removeEnvVar
            );

            if ($removeEnvVar) {
                unset($this->_config['environment'][$varName]);
            }
        }
    }

    private function _addEnvironmentVariables()
    {
        $addNewEnvironmentVariable = false;

        $this->askYesNoQuestion(
            'Add new environment variable',
            $addNewEnvironmentVariable
        );

        if (!$addNewEnvironmentVariable) {
            return;
        }

        $this->askQuestion(
            'Environment Variable Name',
            $varName
        );

        $this->askQuestion(
            'Environment Variable Value',
            $value
        );

        $this->_config['environment'][$varName] = $value;

        // offer to add another
        $this->_addEnvironmentVariables();
    }

    private function _editCustomWebroot()
    {
        $this->askQuestion(
            'What is the webroot directory, relative to `src` directory (e.g. web)',
            $this->_config['relative_webroot_dir'],
            ''
        );
    }

    

}
