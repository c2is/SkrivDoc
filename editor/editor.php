<?php

session_start();
error_reporting(E_ALL ^E_NOTICE);
require_once('../vendor/autoload.php');
use Symfony\Component\Process\Process;

$book = new book();

// creation of the renderer object
$renderer = \Skriv\Markup\Renderer::factory();


/*
 * Ajax handler
 */
switch($_POST["action"]) {
    case "init":
        $output = "Now editing ".$book->getCurrentPage();
        $expireNum = time() - 3600;
        if (!isset($_SESSION["initCheck"])) {
            $_SESSION["initCheck"] = time();
        }
        // if the last time check is more than one day
        if ($_SESSION["initCheck"] <= $expireNum) {
            $gHdl = new gitHandler();
            $pullStatus = $gHdl->getPullStatus();
            $_SESSION["initCheck"] = time();
            /*
             * Deal with git errors
             */
            if ($pullStatus < 1) {
                $output = msg($gHdl->shortMessage, true);
            }
        }

        echo $output;

        break;
    case "push":
        build($renderer, $book->getLanguage(),$book->getCurrentPage());
        $gHdl = new gitHandler();
        $pullStatus = $gHdl->getPullStatus();
        /*
         * Deal with git errors
         */
        if ($pullStatus < 1) {
            $output = msg($gHdl->shortMessage, true);
            echo $output;
            die();
        }

        $cmd[] = "git add ../.";
        $cmd[] = "git commit -m'Auto commit from doc editor'";
        $cmd[] = "git push origin master";
        $cmd[] = "git checkout gh-pages";
        $html = file_get_contents("../html/".$book->getLanguage()."/index.html");

        $res = shell_exec(implode(";", $cmd));

        file_put_contents("../html/".$book->getLanguage()."/index.html", $html);
        $cmd = array();
        $cmd[] = "git add ../html/. ";
        $cmd[] = "git commit -m'Auto commit from doc editor'";
        $cmd[] = "git push --force origin gh-pages";
        $cmd[] = "git checkout master";
        $status = shell_exec(implode(";", $cmd));
        echo "Html pushed to Github pages ";
        break;
    case "convert":
        echo $renderer->render($_POST["text"]);
        break;
    case "shutdown":
        $pid = shell_exec("ps ax | grep 'php -S localhost:8096' | grep -v grep");
        $pid = trim($pid);
        $pid = explode(" ", $pid);
        shell_exec("kill ".$pid[0]);
        break;
    case "setlg":
        $book->setLanguage($_POST["language"]);
        break;
    case "loadPage":
        echo file_get_contents("../".$book->getLanguage()."/".$book->getCurrentPage());
        break;
    case "loadConverted":
        echo $renderer->render(file_get_contents("../".$book->getLanguage()."/".$book->getCurrentPage()));
        break;
    case "save":
        file_put_contents("../".$book->getLanguage()."/".$book->getCurrentPage(), $_POST["text"]);
        echo "Content saved into ".$book->getLanguage()."/".$book->getCurrentPage();
        break;
    case "build":
        build($renderer, $book->getLanguage(),$book->getCurrentPage());
        echo "Files generated into directory html/".$book->getLanguage()."/";
        break;
    case "prev":
        $book->moveBw();
        break;
    case "next":
        $book->moveFw();
        break;
    case "add":
        echo $book->addPage();
        break;
    case "del":
        echo $book->delPage();
        break;
}

function build($renderer,$language, $pageName)
{
    $tpl = file_get_contents("../html/".$language."/tpl.htm");
    $tpl = str_replace("#{doc}#", $renderer->render(file_get_contents("../".$language."/".$pageName)), $tpl);
    $tpl = str_replace("#{toc}#", $renderer->getToc(), $tpl);
    file_put_contents("../html/".$language."/index.html", $tpl);
}

function msg($text,$textarea = false)
{
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

    public function getPullStatus ($branch = "master")
    {
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
        if ($this->in_array_match("`Your local changes to the following files would be overwritten by merge`", $this->error)) {
            $this->shortMessage = "Update impossible, you have to commit or stash you local file, git said:\n".implode("", $this->error);
            $status = -1;
        } elseif ($this->in_array_match("`Automatic merge failed;`", $this->opt)) {
            $this->shortMessage = "Some conflicts have to be fixed, reload this page to see in the editor or go to you terminal";
            $status = -2;
        } elseif ($this->in_array_match("`Pull is not possible because you have unmerged files`", $this->error)) {
            $this->shortMessage = "You have unmerged files, you can correct directly in this editor but you have to add and commit manually,  git said:\n".implode("", $this->error);
            $status = -3;
        }

        return $status;

    }

    private function in_array_match($regex, $array)
    {
        if (!is_array($array)) {
            trigger_error('Argument 2 must be array');
        }
        foreach ($array as $v) {
            $match = preg_match($regex, $v);
            if ($match === 1) {
                return true;
            }
        }

        return false;
    }
}

class book
{
    const ORDER_PATTERN = "([0-9]*)\.skriv";
    const PAGE_PREFIX = "chapter";
    private $pages;
    public $currentPage;

    public function __construct()
    {
        if (! $this->getLanguage()) {
            $this->setLanguage();
        }
        if (! isset($_SESSION["currentPage"])) {
            $this->setCurrentPage(self::PAGE_PREFIX."1.skriv");
        } else {
            $this->currentPage =  $_SESSION["currentPage"];
        }
        $this->pages = array();
    }

    public function getCurrentPage()
    {
        return $this->currentPage;
    }

    public function setCurrentPage($pageName)
    {
        $_SESSION["currentPage"] = $pageName;
        $this->currentPage =  $_SESSION["currentPage"];
        return $this->currentPage;
    }

    public function setLanguage($prefix="en")
    {
        $_SESSION["language"] = $prefix;
    }
    /*
     * Change page in the editor : go to the next
     */
    public function moveFw()
    {
        $indexCurrent  = array_search($this->getCurrentPage(), $this->getPages());
        $this->setCurrentPage($this->getPages()[$indexCurrent + 1]);
    }
    /*
    * Change page in the editor : go to the previous
    */
    public function moveBw()
    {
        $indexCurrent  = array_search($this->getCurrentPage(), $this->getPages());
        $this->setCurrentPage($this->getPages()[$indexCurrent - 1]);
    }

    public function getLanguage()
    {
        if (! isset($_SESSION["language"])) {
            return false;
        } else {
            return $_SESSION["language"];
        }

    }

    private function getPages()
    {
        $files = array();
        $this->lsDir("../".$this->getLanguage(),$files);
        $tmp = array();
        foreach ($files as $file) {
            $match = array();

            preg_match("`".self::ORDER_PATTERN."`", $file, $match);
            if ((int) $match[1] > 0) {
                $tmp[(int) $match[1]] = $file;
                $pages[(int) $match[1]] = $file;
            }
        }
        $tmp = array_flip($tmp);
        sort($tmp);
        foreach ($tmp as $index => $indexPage) {
            $this->pages[$index] = $pages[$indexPage];
        }

        return $this->pages;
    }

    public function addPage()
    {
        $indexCurrent  = array_search($this->getCurrentPage(), $this->getPages());
        preg_match("`".self::ORDER_PATTERN."`", $this->getPages()[$indexCurrent], $match);
        $numCurrent = $match[1];

        $numNewPage = $numCurrent +1;
        $pageName = self::PAGE_PREFIX.$numNewPage.".skriv";
        $index  = array_search($pageName,$this->getPages());
        if ($index !== false) {
            $this->shiftPagesFw($index);
        }

        file_put_contents("../".$this->getLanguage()."/".$pageName,"");

    }
    /*
     * Shift pages forward
    */
    protected function shiftPagesFw($startIndex)
    {
        $pages = $this->getPages();
        for ($i = count($pages)-1; $i >= $startIndex;$i--) {
            preg_match("`".self::ORDER_PATTERN."`", $pages[$i], $match);
            $newName = self::PAGE_PREFIX.($match[1] + 1).".skriv";
            if ($pages[$i] == "") {
                echo $i;

            } else {
                rename("../".$this->getLanguage()."/".$pages[$i], "../".$this->getLanguage()."/".$newName);
            }

        }

    }
    public function delPage()
    {
        $pages = $this->getPages();
        $indexCurrent  = array_search($this->getCurrentPage(), $pages);
        $pageName = $pages[$indexCurrent];

        unlink("../".$this->getLanguage()."/".$pageName);
        $this->shiftPagesBw($indexCurrent + 1,$pages);

    }

    /*
    * Shift pages backward
    */
    protected function shiftPagesBw($startIndex,$pages)
    {
        foreach ($pages as $index=>$page) {
            if ($index >= $startIndex) {
                preg_match("`".self::ORDER_PATTERN."`", $page, $match);
                $newName = self::PAGE_PREFIX.($match[1] - 1).".skriv";
                rename("../".$this->getLanguage()."/".$page, "../".$this->getLanguage()."/".$newName);
            }
        }
    }

    private function lsDir($dirPath, &$files)
    {
        $excluded = array(".", "..");
        $buffer = opendir($dirPath);

        while ($file = @readdir($buffer)) {
            if (! in_array($file,$excluded)) {
                if (is_dir($dirPath.'/'.$file)) {
                    $this->lsDir($dirPath.'/'.$file, $files);
                } else {
                    $files[] = $file;
                }
            }

        }
        closedir($buffer);
    }

}
