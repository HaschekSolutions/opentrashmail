<?php
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__FILE__));

include_once(ROOT.DS.'inc'.DS.'OpenTrashmailBackend.class.php');
include_once(ROOT.DS.'inc'.DS.'core.php');

$url = array_filter(explode('/',ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),'/')));

$backend = new OpenTrashmailBackend($url);

if($_SERVER['HTTP_HX_REQUEST']!='true')
{
    if(count($url)==0 || !file_exists(ROOT.DS.implode('/', $url)))
        if($url[0]!='api' && $url[0]!='rss' && $url[0]!='json')
            exit($backend->renderTemplate('index.html',[
                'url'=>implode('/', $url),
                'settings'=>loadSettings(),
            ]));
}
else if(count($url)==1 && $url[0] == 'api') {
    exit($backend->renderTemplate('intro.html'));
}


$answer = $backend->run();


if($answer === false)
    return false;
else
    echo $answer;

