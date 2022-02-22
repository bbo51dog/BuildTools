<?php

require_once __DIR__ . "/bbo51dog/buildtools/BuildToolsException.php";

use bbo51dog\buildtools\BuildToolsException;

/**
 * @throws BuildToolsException
 */
function buildPlugin(string $pluginName, string $importDir, string $exportDir) {
    $pharName = $exportDir . $pluginName . ".phar";
    $importPluginDir = $importDir . $pluginName . "/";
    if (!is_dir($importPluginDir) || !is_file($importPluginDir . "/plugin.yml")) {
        throw new BuildToolsException("Invalid plugin name \"$pluginName\"");
    }
    if (file_exists($pharName)) {
        try {
            Phar::unlinkArchive($pharName);
        } catch (PharException $e) {
            throw new BuildToolsException("Unlinking existed phar file failed");
        }
    }
    $phar = new Phar($pharName);
    $phar->setStub('<?php __HALT_COMPILER();');
    $phar->setSignatureAlgorithm(Phar::SHA1);
    $phar->startBuffering();
    $phar->addFile($importPluginDir . "plugin.yml", "plugin.yml");
    addFilesToPhar($phar, $importPluginDir . "src", "src");
    if (is_dir($importPluginDir . "resources")) {
        addFilesToPhar($phar, $importPluginDir . "resources", "resources");
    }
    if (file_exists($importPluginDir . "pmbuild.json")) {
        $buildConfig = json_decode(file_get_contents($importPluginDir . "pmbuild.json"), true);
        if (!empty($buildConfig["stub"])) {
            $stubFile = $buildConfig["stub"];
        }
    } elseif (file_exists($importPluginDir . ".poggit.yml")) {
        $poggitYaml = yaml_parse_file($importPluginDir . ".poggit.yml");
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
            if (is_file($importPluginDir . $stubPath)) {
                $phar->addFile($importPluginDir . $stubPath, "stub.php");
                $phar->setStub('<?php require "phar://" . __FILE__ . "/stub.php"; __HALT_COMPILER();');
            }
        } else {
            if (is_file($importPluginDir . $stubFile)) {
                $phar->addFile($importPluginDir . $stubFile, $stubFile);
                $phar->setStub('<?php require "phar://" . __FILE__ . "/" . ' . var_export($stubFile, true) . '; __HALT_COMPILER();');
            }
        }
    }
    $phar->compressFiles(Phar::GZ);
    $phar->stopBuffering();
}

function addFilesToPhar(Phar $phar, string $dir, string $localName) {
    $localName = rtrim($localName, "/\\") . "/";
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
        if (!is_file($file)) {
            continue;
        }
        $localPath = $localName . str_replace("\\", "/", ltrim(substr($file, strlen($dir)), "/\\"));
        $phar->addFile($file, $localPath);
    }
}