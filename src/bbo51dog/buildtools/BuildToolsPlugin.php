<?php

namespace bbo51dog\buildtools;

use bbo51dog\buildtools\command\BuildPluginCommand;
use pocketmine\plugin\PluginBase;

class BuildToolsPlugin extends PluginBase {

    protected function onLoad(): void {
        require_once __DIR__ . "/script/BuildScript.php";
    }

    protected function onEnable(): void {
        $this->prepareDirectory($buildsDir = $this->getDataFolder() . "builds/");
        $this->prepareDirectory($pluginsDir = $this->getDataFolder() . "plugins/");
        $this->prepareDirectory($virionsDir = $this->getDataFolder() . "virions/");
        $this->getServer()->getCommandMap()->register("buildtools", new BuildPluginCommand($buildsDir, $pluginsDir, $virionsDir));
    }

    private function prepareDirectory(string $dir) {
        if (!is_dir($dir)) {
            mkdir($dir);
        }
    }
}