<?php
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__FILE__));

include_once(ROOT.DS.'inc'.DS.'OpenTrashmailBackend.class.php');
include_once(ROOT.DS.'inc'.DS.'core.php');

$settings = loadSettings();
define('BASE_PATH', getBasePath($settings));

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// reverse proxies that don't strip the prefix of the path the UI is hosted under
if(BASE_PATH && ($path === BASE_PATH || startsWith($path, BASE_PATH.'/')))
    $path = substr($path, strlen(BASE_PATH));
$url = array_values(array_filter(array_map('rawurldecode', explode('/',ltrim($path,'/'))), 'strlen'));

$backend = new OpenTrashmailBackend($url);

if($settings['ALLOWED_IPS'])
{
    $ip = getUserIP();
    if(!isIPInRange( $ip, $settings['ALLOWED_IPS'] ))
    {
        http_response_code(403);
        exit("Your IP (".escape($ip).") is not allowed to access this site.");
    }
}

if($settings['PASSWORD'] || $settings['ADMIN_PASSWORD']) // let's only start a session if we need one
    session_start();

if($settings['PASSWORD']) //site requires a password
{
    $pw = $settings['PASSWORD'];
    $auth = false;
    //first check for auth header or POST/GET variable
    if(isset($_SERVER['HTTP_PWD']) && hash_equals((string)$pw, (string)$_SERVER['HTTP_PWD']))
        $auth = true;
    else if(isset($_REQUEST['password']) && hash_equals((string)$pw, (string)$_REQUEST['password']))
        $auth = true;
    // if not, check for session
    else if(isset($_SESSION['authenticated']) && $_SESSION['authenticated'] == true)
        $auth = true;
    // if user sent a pw but it's wrong, show error
    else if(isset($_REQUEST['password']))
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
                'url'=>implode('/', array_map('rawurlencode', $url)),
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

