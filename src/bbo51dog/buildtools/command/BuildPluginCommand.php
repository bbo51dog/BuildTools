<?php

namespace bbo51dog\buildtools\command;

use Phar;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class BuildPluginCommand extends Command {

    private string $buildsDir;

    private string $pluginsDir;

    public function __construct(string $buildsDir, string $pluginsDir) {
        parent::__construct("buildplugin", "Make plugin phar file", "/buildplugin {plugin_name}", ["bp"]);
        $this->setPermission("buildtools.command.buildplugin");
        $this->buildsDir = $buildsDir;
        $this->pluginsDir = $pluginsDir;
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
        $pluginDir = $this->pluginsDir . $pluginName . "/";
        if (!is_dir($pluginDir) || !is_file($pluginDir . "/plugin.yml")) {
            $sender->sendMessage("Invalid plugin name \"$pluginName\"");
            return;
        }
        $pharName = $this->buildsDir . $pluginName . ".phar";
        if (file_exists($pharName)) {
            Phar::unlinkArchive($pharName);
        }
        $sender->sendMessage("Building $pluginName...");
        $phar = new Phar($pharName);
        $phar->setStub('<?php __HALT_COMPILER();');
        $phar->setSignatureAlgorithm(Phar::SHA1);
        $phar->startBuffering();
        $phar->addFile($pluginDir . "plugin.yml", "plugin.yml");
        $this->addFiles($phar, $pluginDir . "src", "src");
        if (is_dir($pluginDir . "resources")) {
            $this->addFiles($phar, $pluginDir . "resources", "resources");
        }
        if (file_exists($pluginDir . "pmbuild.json")) {
            $buildConfig = json_decode(file_get_contents($pluginDir . "pmbuild.json"), true);
            if (!empty($buildConfig["stub"])) {
                $stubFile = $buildConfig["stub"];
            }
        } elseif (file_exists($pluginDir . ".poggit.yml")) {
            $poggitYaml = yaml_parse_file($pluginDir . ".poggit.yml");
            if (!empty($poggitYaml["projects"][$pluginName])) {
                $projectManifest = $poggitYaml["projects"][$pluginName];
                if (isset($projectManifest["stub"])) {
                    $stubFile = $projectManifest["stub"];
                }
            }
        }
        $phar->setStub('<?php __HALT_COMPILER();');
        if (isset($stubFile)) {
            if ($stubFile[0] === "/") {
                $stubPath = substr($stubFile, 1);
                if (is_file($pluginDir . $stubPath)) {
                    $phar->addFile($pluginDir . $stubPath, "stub.php");
                    $phar->setStub('<?php require "phar://" . __FILE__ . "/stub.php"; __HALT_COMPILER();');
                }
            } else {
                if (is_file($pluginDir . $stubFile)) {
                    $phar->addFile($pluginDir . $stubFile, $stubFile);
                    $phar->setStub('<?php require "phar://" . __FILE__ . "/" . ' . var_export($stubFile, true) . '; __HALT_COMPILER();');
                }
            }
        }
        $phar->compressFiles(Phar::GZ);
        $phar->stopBuffering();
        $sender->sendMessage("Plugin \"$pluginName\" has been successfully builded");
    }

    private function addFiles(Phar $phar, string $dir, string $localName) {
        $localName = rtrim($localName, "/\\") . "/";
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
            if (!is_file($file)) {
                continue;
            }
            $localPath = $localName . str_replace("\\", "/", ltrim(substr($file, strlen($dir)), "/\\"));
            $phar->addFile($file, $localPath);
        }
    }
}