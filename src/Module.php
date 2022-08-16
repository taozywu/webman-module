<?php

namespace Ifui\WebmanModule;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Webman\Bootstrap;
use Webman\Config;
use Workerman\Worker;
use const PHP_EOL;

class Module implements Bootstrap
{
    /**
     * The self instance.
     *
     * @var Module|null
     */
    public static ?Module $instance = null;

    /**
     * All $applications with scan market folder.
     *
     * @var array
     */
    public array $applications = [];

    /**
     * The Filesystem instance.
     *
     * @var Filesystem
     */
    public Filesystem $filesystem;

    /**
     * The Worker instance.
     *
     * @var Worker|null
     */
    public ?Worker $worker;

    /**
     * Start Container Server.
     *
     * @param Worker $worker
     * @return void
     * @throws FileNotFoundException
     */
    public static function start($worker)
    {
        require_once __DIR__ . '/helpers.php';

        $market = self::getInstance();
        $market->filesystem = new Filesystem();
        $market->worker = $worker;

        $market->reload();
        $market->boot();
    }

    /**
     * Get current class instance.
     *
     * @return Module
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new Module();
        }
        return self::$instance;
    }

    /**
     * Reload module.json.
     *
     * @return void
     * @throws FileNotFoundException
     */
    public function reload()
    {
        $jsonPaths = glob(module_path() . '/**/module.json');
        foreach ($jsonPaths as $path) {
            $this->applications[] = json_decode($this->filesystem->get($path), true);
        }
    }

    /**
     * Start activity containers.
     *
     * @return void
     */
    public function boot()
    {
        $namespace = Config::get('plugin.ifui.webman-module.app.namespace', 'plugin');
        $activities = $this->getActivity();
        foreach ($activities as $activity) {
            $moduleName = $activity['name'];
            $className = "{$namespace}\\$moduleName\app\providers\AppServerProvider";
            if (class_exists($className)) {
                if (!is_null($this->worker) && $this->worker->count == 1) {
                    Worker::safeEcho("<n><g>[INFO]</g> 应用模块 ${moduleName} 已启动.</n>" . PHP_EOL);
                }
                with(new $className($this->worker))->boot();
            }
        }
    }

    /**
     * Get activity applications.
     *
     * @return array
     */
    public function getActivity()
    {
        return array_filter($this->applications, fn($item) => $item['activity']) ?? [];
    }

    /**
     * Get applications.
     *
     * @return array
     */
    public function getApplications()
    {
        return $this->applications;
    }

    /**
     * Find the container of name.
     *
     * @param $name
     * @return array
     */
    public function find($name)
    {
        foreach ($this->applications as $application) {
            if ($application['name'] == $name) {
                return $application;
            }
        }
        return [];
    }
}