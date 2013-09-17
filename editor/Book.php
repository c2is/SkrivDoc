<?php
/**
 * This file is part of a C2iS <http://wwww.c2is.fr/> project.
 * André Cianfarani <a.cianfarani@c2is.fr>
 */
namespace SkrivEditor;

class Book
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
            if (! file_exists("../".$this->getLanguage()."/".self::PAGE_PREFIX."1.skriv")) {
                file_put_contents("../".$this->getLanguage()."/".self::PAGE_PREFIX."1.skriv", "");
            }
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
        unset($_SESSION["currentPage"]);
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

    public function getPages()
    {
        $this->pages = array();
        $files = array();
        $this->lsDir("../".$this->getLanguage(), $files);
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
        $index  = array_search($pageName, $this->getPages());
        if ($index !== false) {
            $this->shiftPagesFw($index);
        }

        file_put_contents("../".$this->getLanguage()."/".$pageName, "");

    }
    /*
     * Shift pages forward
    */
    protected function shiftPagesFw($startIndex)
    {
        $pages = $this->getPages();
        for ($i = count($pages)-1; $i >= $startIndex; $i--) {
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
        $this->shiftPagesBw($indexCurrent + 1, $pages);

        if (in_array($pageName, $this->getPages())) {
            $this->setCurrentPage($pageName);
        } else {
            $this->setCurrentPage($pages[$indexCurrent -1]);
        }


    }

    /*
    * Shift pages backward
    */
    protected function shiftPagesBw($startIndex,$pages)
    {
        foreach ($pages as $index => $page) {
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
            if (! in_array($file, $excluded)) {
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