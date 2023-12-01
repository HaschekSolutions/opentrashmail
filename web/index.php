<?php
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__FILE__));

include_once(ROOT.DS.'inc'.DS.'OpenTrashmailBackend.class.php');
include_once(ROOT.DS.'inc'.DS.'core.php');

$url = array_filter(explode('/',ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),'/')));

$backend = new OpenTrashmailBackend($url);

$settings = loadSettings();

if($settings['ALLOWED_IPS'])
{
    $ip = getUserIP();
    if(!isIPInRange( $ip, $settings['ALLOWED_IPS'] ))
        exit("Your IP ($ip) is not allowed to access this site.");
}

if($settings['PASSWORD'] || $settings['ADMIN_PASSWORD']) // let's only start a session if we need one
    session_start();

if($settings['PASSWORD']) //site requires a password
{
    $pw = $settings['PASSWORD'];
    $auth = false;
    //first check for auth header or POST/GET variable
    if(isset($_SERVER['HTTP_PWD']) && $_SERVER['HTTP_PWD'] == $pw)
        $auth = true;
    else if(isset($_REQUEST['password']) && $_REQUEST['password'] == $pw)
        $auth = true;
    // if not, check for session
    else if(isset($_SESSION['authenticated']) && $_SESSION['authenticated'] == true)
        $auth = true;
    // if user sent a pw but it's wrong, show error
    else if($_REQUEST['password'] != $settings['PASSWORD'])
        exit($backend->renderTemplate('password.html',[
            'error'=>'Wrong password',
        ]));
    
    if($auth===true)
        $_SESSION['authenticated'] = true;
    else
        exit($backend->renderTemplate('password.html'));
}

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

