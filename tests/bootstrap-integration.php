<?php

declare(strict_types=1);

$cms = getenv('WEBBLOCKS_CMS_TEST_ROOT');
if (! is_string($cms) || ! is_file($cms.'/vendor/autoload.php')) {
  throw new RuntimeException('Set WEBBLOCKS_CMS_TEST_ROOT to a local CMS checkout with its test dependencies.');
}
$loader = require $cms.'/vendor/autoload.php';
$loader->addPsr4('WebBlocks\\Support\\', dirname(__DIR__).'/src');
$loader->addPsr4('WebBlocks\\Cms\\Tests\\', $cms.'/tests');
