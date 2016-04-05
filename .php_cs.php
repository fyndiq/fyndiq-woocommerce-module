<?php

$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->exclude('include')
    ->in(__DIR__.'/src');

return Symfony\CS\Config\Config::create()
    ->level(Symfony\CS\FixerInterface::PSR2_LEVEL)
    ->finder($finder);
