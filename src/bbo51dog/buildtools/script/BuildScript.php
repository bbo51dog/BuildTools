<?php

namespace bbo51dog\buildtools\script;

require_once __DIR__ . "/../BuildToolsException.php";
use AssertionError;
use bbo51dog\buildtools\BuildToolsException;
use InvalidArgumentException;
use Phar;
use PharException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * @throws BuildToolsException
 */
function buildPlugin(string $pluginName, string $importDir, string $exportDir, string $virionsDir) {
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
        if (!empty($buildConfig["virions"])) {
            $virionNames = $buildConfig["virions"];
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
    if (!empty($virionNames) && is_array($virionNames)) {
        foreach ($virionNames as $virionName) {
            $virionPath = $virionsDir . $virionName . ".phar";
            if (!is_file($virionPath)) {
                throw new BuildToolsException("Virion phar file \"$virionPath\" not found");
            }
            $virionPhar = new Phar($virionPath);
            virion_infect($virionPhar, $phar);
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


// From virion.php by poggit

const VIRION_INFECTION_MODE_SYNTAX = 0;
const VIRION_INFECTION_MODE_SINGLE = 1;
const VIRION_INFECTION_MODE_DOUBLE = 2;

function virion_infect(Phar $virus, Phar $host, string $prefix = "", int $mode = VIRION_INFECTION_MODE_SYNTAX, &$hostChanges = 0, &$viralChanges = 0): int {
    if(!isset($virus["virion.yml"])) {
        throw new RuntimeException("virion.yml not found, could not activate virion", 2);
    }
    $virionYml = yaml_parse(file_get_contents($virus["virion.yml"]));
    if(!is_array($virionYml)) {
        throw new RuntimeException("Corrupted virion.yml, could not activate virion", 2);
    }

    $infectionLog = isset($host["virus-infections.json"]) ? json_decode(file_get_contents($host["virus-infections.json"]), true) : [];

    $genus = $virionYml["name"];
    $antigen = $virionYml["antigen"];

    foreach($infectionLog as $old) {
        if($old["antigen"] === $antigen) {
            echo "[!] Target already infected by this virion, aborting\n";
            return 3;
        }
    }

    //    do {
    //        $antibody = str_replace(["+", "/"], "_", trim(base64_encode(random_bytes(10)), "="));
    //        if(ctype_digit($antibody{0})) $antibody = "_" . $antibody;
    //        $antibody = $prefix . $antibody . "\\" . $antigen;
    //    } while(isset($infectionLog[$antibody]));

    $antibody = $prefix . $antigen;

    $infectionLog[$antibody] = $virionYml;

    echo "Using antibody $antibody for virion $genus ({$antigen})\n";

    $hostPharPath = "phar://" . str_replace(DIRECTORY_SEPARATOR, "/", $host->getPath());
    $hostChanges = 0;
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($hostPharPath)) as $name => $chromosome) {
        if($chromosome->isDir()) continue;
        if($chromosome->getExtension() !== "php") continue;

        $rel = cut_prefix($name, $hostPharPath);
        $data = change_dna($original = file_get_contents($name), $antigen, $antibody, $mode, $hostChanges);
        if($data !== "") $host[$rel] = $data;
    }

    $restriction = "src/" . str_replace("\\", "/", $antigen) . "/"; // restriction enzyme ^_^
    $ligase = "src/" . str_replace("\\", "/", $antibody) . "/";

    $viralChanges = 0;
    foreach(new RecursiveIteratorIterator($virus) as $name => $genome) {
        if($genome->isDir()) continue;

        $rel = cut_prefix($name, "phar://" . str_replace(DIRECTORY_SEPARATOR, "/", $virus->getPath()) . "/");

        if(str_starts_with($rel, "resources/")) {
            $host[$rel] = file_get_contents($name);
        } elseif(str_starts_with($rel, "src/")) {
            if(!str_starts_with($rel, $restriction)) {
                echo "Warning: file $rel in virion is not under the antigen $antigen ($restriction)\n";
                $newRel = $rel;
            } else {
                $newRel = $ligase . cut_prefix($rel, $restriction);
            }
            $data = change_dna(file_get_contents($name), $antigen, $antibody, $mode, $viralChanges); // it's actually RNA
            $host[$newRel] = $data;
        }
    }

    $host["virus-infections.json"] = json_encode($infectionLog);

    return 0;
}

function cut_prefix(string $string, string $prefix): string {
    if(!str_starts_with($string, $prefix)) throw new AssertionError("\$string does not start with \$prefix:\n$string\n$prefix");
    return substr($string, strlen($prefix));
}

function change_dna(string $chromosome, string $antigen, string $antibody, $mode, &$count = 0): string {
    switch($mode) {
        case VIRION_INFECTION_MODE_SYNTAX:
            $tokens = token_get_all($chromosome);
            $tokens[] = ""; // should not be valid though
            foreach($tokens as $offset => $token) {
                if(!is_array($token) or $token[0] !== T_WHITESPACE) {
                    /** @noinspection IssetArgumentExistenceInspection */
                    list($id, $str, $line) = is_array($token) ? $token : [-1, $token, $line ?? 1];
                    //namespace test; is a T_STRING whereas namespace test\test; is not.
                    if(isset($init, $prefixToken) and $id === T_STRING){
                        if($str === $antigen) { // case-sensitive!
                            $tokens[$offset][1] = $antibody . substr($str, strlen($antigen));
                            ++$count;
                        } elseif(stripos($str, $antigen) === 0) {
                            echo "\x1b[38;5;227m\n[WARNING] Not replacing FQN $str case-insensitively.\n\x1b[m";
                        }
                        unset($init, $prefixToken);
                    } else {
                        if($id === T_NAMESPACE) {
                            $init = $offset;
                            $prefixToken = $id;
                        } elseif($id === T_NAME_QUALIFIED) {
                            if(($str[strlen($antigen)]??"\\") === "\\") {
                                if(str_starts_with($str, $antigen)) { // case-sensitive!
                                    $tokens[$offset][1] = $antibody . substr($str, strlen($antigen));
                                    ++$count;
                                } elseif(stripos($str, $antigen) === 0) {
                                    echo "\x1b[38;5;227m\n[WARNING] Not replacing FQN $str case-insensitively.\n\x1b[m";
                                }
                            }
                            unset($init, $prefixToken);
                        } elseif($id === T_NAME_FULLY_QUALIFIED){
                            if(str_starts_with($str, "\\" . $antigen . "\\")) { // case-sensitive!
                                $tokens[$offset][1] = "\\" . $antibody . substr($str, strlen($antigen)+1);
                                ++$count;
                            } elseif(stripos($str, "\\" . $antigen . "\\") === 0) {
                                echo "\x1b[38;5;227m\n[WARNING] Not replacing FQN $str case-insensitively.\n\x1b[m";
                            }
                            unset($init, $prefixToken);
                        }
                    }
                }
            }
            $ret = "";
            foreach($tokens as $token) {
                $ret .= is_array($token) ? $token[1] : $token;
            }
            break;
        case VIRION_INFECTION_MODE_SINGLE:
            $ret = str_replace($antigen, $antibody, $chromosome, $subCount);
            $count += $subCount;
            break;
        case VIRION_INFECTION_MODE_DOUBLE:
            $ret = str_replace(
                [$antigen, str_replace("\\", "\\\\", $antigen)],
                [$antibody, str_replace("\\", "\\\\", $antibody)],
                $chromosome, $subCount
            );
            $count += $subCount;
            break;
        default:
            throw new InvalidArgumentException("Unknown mode: $mode");
    }

    return $ret;
}