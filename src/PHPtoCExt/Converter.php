<?php 
namespace PHPtoCExt;

abstract class Converter 
{
  protected $codeLines;
  protected $codeASTXMLLines;
  protected $searches;
  protected $replaces;
  protected $postSearches;
  protected $postReplaces;

  public function __construct($codeLines, $codeASTXMLLines)
  {
    $this->codeLines = $codeLines;
    $this->codeASTXMLLines = $codeASTXMLLines;
    $this->searches = array();
    $this->replaces = array();
    $this->postSearches = array();
    $this->postReplaces = array();
  }

  public function getSearches()
  {
    return $this->searches;
  }

  public function getReplaces()
  {
    return $this->replaces;
  }

  public function getPostSearches()
  {
    return $this->postSearches;
  }

  public function getPostReplaces()
  {
    return $this->postReplaces;    
  }

  protected function searchAndReplace($search, $replace)
  {
    $this->searches[] = $search; 
    $this->replaces[] = $replace; 
  }

  protected function postSearchAndReplace($search, $replace)
  {
    $this->postSearches[] = $search; 
    $this->postReplaces[] = $replace; 
  }

  //some util methods for all converters to use, maybe refactor later on to a better place?
  protected function getClassMap()
  {
    //get all classes info, with namespace
    $classInfos = array(); 

    $classMap = array();

    $namespace = "";
    $className = "";

    foreach($this->codeASTXMLLines as $index => $line)
    {
      if (strpos($line,"<node:Stmt_Namespace>") > 0) {
        $startLine = (int)str_replace(array("<scalar:int>","</scalar:int>"),"",$this->codeASTXMLLines[$index + 2]);
        $namespace = str_replace(array("namespace ",";"),"",$this->codeLines[$startLine - 1]);
      } else if (strpos($line,"<node:Stmt_Class>") > 0) {
        $classInfo = new \stdClass();
        $classInfo->startLine = (int)str_replace(array("<scalar:int>","</scalar:int>"),"",$this->codeASTXMLLines[$index + 2]);
        $classInfo->endLine = (int)str_replace(array("<scalar:int>","</scalar:int>"),"",$this->codeASTXMLLines[$index + 5]);
        $classInfo->namespace = $namespace;
        $classInfo->className = "\\".$namespace."\\".trim(str_replace(array("<scalar:string>","</scalar:string>"),"",$this->codeASTXMLLines[$index + 11])); 
        $classInfo->methodInfos = array();
        $className = $classInfo->className;

        $classInfos[] = $classInfo;   

        $classMap[$classInfo->className] = $classInfo;
      } else if (strpos($line,"<node:Stmt_ClassMethod>") > 0) {
        $classMethodInfo = new \stdClass();
        $classMethodInfo->startLine = (int)str_replace(array("<scalar:int>","</scalar:int>"),"",$this->codeASTXMLLines[$index + 2]);
        $classMethodInfo->endLine = (int)str_replace(array("<scalar:int>","</scalar:int>"),"",$this->codeASTXMLLines[$index + 5]);
        $startLineContent = $this->codeLines[$classMethodInfo->startLine - 1];
        $classMethodInfo->name = trim(explode(" ",explode("function ",$startLineContent)[1])[0]);
        $classMethodInfo->pureName = explode(" ", str_replace("(", " ", $classMethodInfo->name))[0];
        //now figure out where it is public, protected or private 

        //find out all methods belongs to this class
        foreach(array("public","protected","private") as $visibility) {
          if (strpos($startLineContent,"$visibility ") !== FALSE) {
            $classMethodInfo->visibility = $visibility;
          }
        }

        if (!isset($classMethodInfo->visibility)) {
          $classMethodInfo->visibility = "protected";
        }

        if (strpos($startLineContent, "static ") !== FALSE) {
          $classMethodInfo->isStatic = true;
        } else {
          $classMethodInfo->isStatic = false;
        }

        $classMap[$className]->methodInfos[$classMethodInfo->pureName] = $classMethodInfo;
      }
    }

    //now figure out the parent classes for each class
    foreach($classInfos as $index => $classInfo) {
      $line = trim($this->codeLines[$classInfo->startLine - 1]);
      if (strpos($line, " extends ") !== FALSE) {
        $lineComps = explode(" extends ", $line);
        $classMap[$classInfo->className]->parentClass = "\\".$classInfo->namespace."\\".trim(explode(" ",$lineComps[1])[0]);
      }
    }

    return $classMap;
  }



  abstract public function convert(); 
}
