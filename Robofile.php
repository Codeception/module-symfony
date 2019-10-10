<?php

require __DIR__ . '/vendor/autoload.php';

use Codeception\Module\Symfony;
use Codeception\Util\DocumentationHelpers;

class RoboFile extends \Robo\Tasks
{
    use DocumentationHelpers;

    public function buildDocs()
    {
        $className = Symfony::class;
        $classPath = str_replace('\\', '/', $className);
        $source = "https://github.com/Codeception/module-symfony/tree/master/src/$classPath.php";
        $sourceMessage = '<p>&nbsp;</p><div class="alert alert-warning">Module reference is taken from the source code. <a href="' . $source . '">Help us to improve documentation. Edit module reference</a></div>';
        $documentationFile = 'documentation.md';
        $this->generateDocumentationForClass($className, $documentationFile, $sourceMessage);
    }
}