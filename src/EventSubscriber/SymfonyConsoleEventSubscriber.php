<?php

namespace Reliv\Deploy\EventSubscriber;

use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArgvInput;
use Zend\Config\Config;

class SymfonyConsoleEventSubscriber extends EventSubscriberAbstract
{
    public static function getSubscribedEvents()
    {
        return array(
            'console.command'   => array('onConsoleCommand', 0),
            'console.terminate' => array('onConsoleTerminate', 0),
        );
    }

    public function onConsoleCommand(ConsoleCommandEvent $event)
    {
        $commandName = $event->getCommand()->getName();

        if ($commandName == 'status' || !$event->getInput() instanceof ArgvInput) {
            return;
        }

        $pidFile = $this->getPidFile();

        if (!$pidFile) {
            return;
        } elseif ($pidFile && !function_exists('posix_getpgid')) {
            trigger_error('Posix functions not installed.  Unable to use PID Locking file.', E_USER_WARNING);
            return;
        }

        if (file_exists($pidFile)) {

            $pidifo = posix_getpgid(file_get_contents($pidFile));

            if ($pidifo) {
                throw new \RuntimeException(
                    "Deploy is currently running.  Please stop all processes and remove lock file at: " . $pidFile
                );
            }

            unlink($pidFile);
        }

        file_put_contents($pidFile, getmypid());

    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event)
    {
        $pidFile = $this->getPidFile();

        if (!$pidFile) {
            throw new \RuntimeException("No Pid File configured.  Stopping Execution");
        }

        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
    }

    protected function getPidFile()
    {
        $config = $this->getConfigService()->getMainConfig();

        return $config->get('system', new Config(array()))
            ->get('pid', null);
    }
}
