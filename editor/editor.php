<?php
session_start();
error_reporting(E_ALL ^E_NOTICE);
require_once('../vendor/autoload.php');
// creation of the renderer object
$renderer = \Skriv\Markup\Renderer::factory();

switch($_POST["action"]) {
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
    case "push":

        $cmd = array();
        $cmd[] = "git checkout master";
        $cmd[] = "git add ../.";
        $cmd[] = "git commit -m'Auto commit from doc editor'";
        $cmd[] = "git push origin master";
        $cmd[] = "git checkout gh-pages";
        $html = file_get_contents("../html/".$_SESSION["language"]."/index.html");

        $res = shell_exec(implode(";",$cmd));

        file_put_contents("../html/".$_SESSION["language"]."/index.html",$html);
        build($renderer,$_SESSION["language"]);
        $cmd = array();
        $cmd[] = "git add ../html/. ";
        $cmd[] = "git commit -m'Auto commit from doc editor'";
        $cmd[] = "git push origin gh-pages";
        $cmd[] = "git checkout master";
        $res1 = shell_exec(implode(";",$cmd));
        if (preg_match("`(rejected|conflict)`i",$res1)) {
            $output = "Some problems appear : maybe conflict or fast forward, inspect your branches master and gh-pages with git";
        }
        else {
            $output = "Html pushed to Github pages ";
        }
        echo $output;
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

