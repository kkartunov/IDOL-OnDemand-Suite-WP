<?php
require "vendor/autoload.php";

use Sami\Parser\Filter\TrueFilter;

$sami = new Sami\Sami('src', array(
    'build_dir' => __DIR__.'/docs/php/build',
    'cache_dir' => __DIR__.'/docs/php/cache'
));
$sami['filter'] = function(){
    return new TrueFilter;
};

return $sami;
