<?php
session_start();
error_reporting(E_ALL ^E_NOTICE);
require_once('../vendor/autoload.php');
use Symfony\Component\Process\Process;
// creation of the renderer object
$renderer = \Skriv\Markup\Renderer::factory();

switch($_POST["action"]) {
    case "init":
        $gHdl = new gitHandler();
        $pullStatus = $gHdl->getPullStatus();
        /*
         * Deal with git errors
         */
        if ($pullStatus < 1) {
            $output = msg($gHdl->shortMessage,true);
        }
        echo $output;

        break;
    case "push":
        build($renderer,$_SESSION["language"]);
        $gHdl = new gitHandler();
        $pullStatus = $gHdl->getPullStatus();
        /*
         * Deal with git errors
         */
        if ($pullStatus < 1) {
            $output = msg($gHdl->shortMessage,true);
            echo $output;
            die();
        }

        $cmd[] = "git add ../.";
        $cmd[] = "git commit -m'Auto commit from doc editor'";
        $cmd[] = "git push origin master";
        $cmd[] = "git checkout gh-pages";
        $html = file_get_contents("../html/".$_SESSION["language"]."/index.html");

        $res = shell_exec(implode(";",$cmd));

        file_put_contents("../html/".$_SESSION["language"]."/index.html",$html);

        $cmd = array();
        $cmd[] = "git add ../html/. ";
        $cmd[] = "git commit -m'Auto commit from doc editor'";
        $cmd[] = "git push --force origin gh-pages";
        $cmd[] = "git checkout master";
        shell_exec(implode(";",$cmd));

        echo "Html pushed to Github pages ";
        break;
    case "convert":
        echo $renderer->render($_POST["text"]);
        break;
    case "shutdown":
        $pid = shell_exec("ps ax | grep 'php -S localhost:8096' | grep -v grep");
        $pid = trim($pid);
        $pid = explode(" ",$pid);
        shell_exec("kill ".$pid[0]);
        break;
    case "setlg":
        $_SESSION["language"] = $_POST["language"];
        echo "Setted language in ".$_SESSION["language"];
        break;
    case "save":
        file_put_contents("../".$_SESSION["language"]."/doc.skriv",$_POST["text"]);
        echo "Content saved into directory ".$_SESSION["language"]."/";
        break;
    case "build":
        build($renderer,$_SESSION["language"]);
        echo "Files generated into directory html/".$_SESSION["language"]."/";
        break;
}

function build($renderer,$language) {
    $tpl = file_get_contents("../html/".$language."/tpl.htm");
    $tpl = str_replace("#{doc}#",$renderer->render(file_get_contents("../".$language."/doc.skriv")),$tpl);
    $tpl = str_replace("#{toc}#",$renderer->getToc(),$tpl);
    file_put_contents("../html/".$language."/index.html",$tpl);
}

function msg($text,$textarea = false) {
    if ($textarea) {
        return "<textarea>".$text."</textarea>";
    } else {
        return $text;
    }
}





class gitHandler
{
    public $error;
    public $opt;
    public $shortMessage;

    public function getPullStatus ($branch = "master") {
        $status = 1;

        shell_exec("git checkout master");
        $process = "git pull origin ".$branch;
        $process = new Process($process);
        $process->run(function ($type, $buffer) {
            if ('err' === $type) {
                $this->error[] = $buffer;
            } else {
                $this->opt[] = $buffer;
            }
        });

        /*
         * Deal with git errors
         */
        if ($this->in_array_match("`Your local changes to the following files would be overwritten by merge`",$this->error)) {
            $this->shortMessage = "Update impossible, you have to commit or stash you local file, git said:\n".implode("",$this->error);
            $status = -1;
        } elseif ($this->in_array_match("`Automatic merge failed;`",$this->opt)) {
            $this->shortMessage = "Some conflicts have to be fixed, reload this page to see in the editor or go to you terminal";
            $status = -2;
        } elseif ($this->in_array_match("`Pull is not possible because you have unmerged files`",$this->error)) {
            $this->shortMessage = "You have unmerged files, you can correct directly in this editor but you have to add and commit manually,  git said:\n".implode("",$this->error);
            $status = -3;
        }

        return $status;

    }

    private function in_array_match($regex, $array) {
        if (!is_array($array))
            trigger_error('Argument 2 must be array');
        foreach ($array as $v) {
            $match = preg_match($regex, $v);
            if ($match === 1) {
                return true;
            }
        }
        return false;
    }
}
