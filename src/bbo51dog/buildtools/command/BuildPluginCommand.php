<?php

namespace bbo51dog\buildtools\command;

use bbo51dog\buildtools\BuildToolsException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use function bbo51dog\buildtools\script\buildPlugin;

class BuildPluginCommand extends Command {

    private string $buildsDir;

    private string $pluginsDir;

    private string $virionsDir;

    public function __construct(string $buildsDir, string $pluginsDir, string $virionsDir) {
        parent::__construct("buildplugin", "Make plugin phar file", "/buildplugin {plugin_name}", ["bp"]);
        $this->setPermission("buildtools.command.buildplugin");
        $this->buildsDir = $buildsDir;
        $this->pluginsDir = $pluginsDir;
        $this->virionsDir = $virionsDir;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if (!$this->testPermission($sender)) {
            return;
        }
        if (empty($args[0])) {
            $sender->sendMessage($this->getUsage());
            return;
        }
        $pluginName = $args[0];
        try {
            buildPlugin($pluginName, $this->pluginsDir, $this->buildsDir, $this->virionsDir);
        } catch (BuildToolsException $e) {
            $sender->sendMessage($e->getMessage());
            return;
        }
        $sender->sendMessage("Building $pluginName...");
        $sender->sendMessage("Plugin \"$pluginName\" has been successfully builded");
    }
}