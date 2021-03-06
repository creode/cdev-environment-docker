<?php

namespace Cdev\Docker\Environment;

use Creode\Cdev\Config;
use Cdev\Docker\Environment\System\Compose\Compose;
use Cdev\Docker\Environment\System\Docker as SystemDocker;
use Cdev\Docker\Environment\System\Sync\Sync;
use Creode\Environment\Environment;
use Creode\Framework\Framework;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Yaml\Yaml;



class Docker extends Environment
{
    const NAME = 'docker';
    const LABEL = 'Docker';
    const COMMAND_NAMESPACE = 'docker';
    
    /**
     * @var SystemDocker
     */
    private $_docker;

    /**
     * @var Compose
     */
    private $_compose;

    /**
     * @var Sync
     */
    private $_sync;

    /**
     * @var ConsoleLogger
     */
    private $_logger;

    /**
     * @var Config
     */
    private $_config;

    /**
     *  @var InputInterface
     */
    private $_input;

    /**
     * @var boolean
     */
    private $_usingDockerSync = false;

    /**
     * @var string
     */
    private $_networkName;

    /**
     * @param SystemDocker $docker
     * @param Compose $compose 
     * @param Sync $sync 
     * @param Framework $framework
     * @param Config $config
     * @return null
     */
    public function __construct(
        SystemDocker $docker,
        Compose $compose,
        Sync $sync,
        Framework $framework,
        Config $config
    ) {
        $this->_docker = $docker;
        $this->_compose = $compose;
        $this->_sync = $sync;
        $this->_framework = $framework;
        $this->_config = $config;

        $conf = $this->_config->get('docker', false);

        $this->_networkName = isset($conf['name']) ? $conf['name'] : 'unknown';
        $this->_compose->setNetwork($this->_networkName);

        $this->_usingDockerSync = isset($conf['sync']['active']) && $conf['sync']['active'];
    }

    /**
     * Sets the inputs
     * @param InputInterface $input 
     * @return type
     */
    public function input(InputInterface $input)
    {
        $this->_input = $input;
    }

    public function start()
    {
        $this->logTitle('Starting dev environment...');

        $path = $this->_input->getOption('path');
        $build = $this->_input->getOption('build');
        $update = $this->_input->getOption('update');
        

        if ($this->_usingDockerSync) {
            $this->_sync->start($path);
        }

        if ($update) {
            $this->_compose->pullImages($path);
        }

        $this->_compose->up($path, $build);
    }

    public function watch()
    {
        $this->logTitle('Watching dev environment logs...');

        $path = $this->_input->getOption('path');

        $this->_compose->logs($path);
    }

    public function stop()
    {
        $this->logTitle('Stopping dev environment...');

        $path = $this->_input->getOption('path');

        $this->_compose->stop($path);
 
        if ($this->_usingDockerSync) {
            $this->_sync->stop($path);
        }
    }

    public function nuke()
    {
        $this->logTitle('Nuking dev environment...');

        $path = $this->_input->getOption('path');

        $this->_compose->stop($path);
        $this->_compose->rm($path);

        if ($this->_usingDockerSync) {
            $this->_sync->clean($path);
        }

        $this->cleanup();
    }

    public function status()
    {
        $this->logTitle('Environment status');

        $path = $this->_input->getOption('path');

        $this->_compose->ps($path);
 
        if ($this->_usingDockerSync) {
            $this->_sync->listSyncPoints($path);
        }
    }

    public function cleanup()
    {
        $this->logTitle('Cleaning up Docker leftovers...');

        $path = $this->_input->getOption('path');

        $this->_docker->cleanup($path);
    }

    public function ssh()
    {
        $this->logTitle('Connecting to server...');

        $path = $this->_input->getOption('path');
        $user = $this->_input->getOption('user');

        $this->logMessage("Connecting as $user");

        $this->_compose->ssh($path, $user);
    }

    public function dbConnect()
    {
        $this->logTitle('Connecting to database...');

        $path = $this->_input->getOption('path');
        $database = $this->_input->getOption('database');
        $user = $this->_input->getOption('user');
        $password = $this->_input->getOption('password');

        $this->logMessage("Connecting to $database as $user");

        $this->_compose->dbConnect($path, $database, $user, $password);
    }

    /**
     * Runs a command on the docker-compose php container
     * @param array $command 
     * @param bool $elevatePermissions 
     * @return null
     */
    public function runCommand(array $command = array(), $elevatePermissions = false)
    {
        $path = $this->_input->getOption('path');

        $command = array_merge(
            [
                'exec',
                '--user=' . ($elevatePermissions ? 'root' : 'www-data'),
                'php'
            ],
            $command
        );
        
        $this->_compose->runCmd(
            $path,
            $command
        );
    }


    /**
     * Returns docker compose system object
     * @return Compose
     */
    public function getCompose()
    {
        return $this->_compose;
    }

    /**
     * Returns docker sync system object
     * @return Sync
     */
    public function getSync()
    {
        return $this->_sync;
    }
}
