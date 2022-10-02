<?php 
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__FILE__));

error_reporting(E_ALL || ~E_NOTICE);
ini_set('display_errors', 1);

include_once(ROOT.DS.'inc'.DS.'core.php');

if (PHP_SAPI === 'cli-server')
    $_SERVER['SCRIPT_NAME'] = pathinfo(__FILE__, PATHINFO_BASENAME);

if($_GET['url'])
    $url = explode('/',ltrim(parse_url($_GET['url'], PHP_URL_PATH),'/'));
else $url = array_filter(explode('/',ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),'/')));

$action = strtolower($_REQUEST['a']);
$email = strtolower($_REQUEST['email']);
// quick hack to get admin email working. 
// by the time $email is checked its been over written.
// store a copy in $admincheck to compare later. 
$admincheck = $email;
    
if(!empty($email)){
  if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
    // email param provided, but invalid: skip action and show invalid email error
    $o = array('status'=>'err','reason'=>'Invalid Email address');
    unset($action);
  }
  $dir = getDirForEmail($email);
  $email = basename($dir);
}

switch($action)
{
    case 'del':
        $id = intval($_REQUEST['mid']);
        if(!is_dir($dir))
            $o = array('status'=>'err','reason'=>'No emails received on this address');
        else if(!is_numeric($id) || !emailIDExists($email,$id))
            $o = array('status'=>'err','reason'=>'Invalid Email ID');
        else
        {
            
            if(deleteEmail($email,$id))
                $o = array('status'=>'ok');
            else
                $o = array('status'=>'err','reason'=>'Could not delete email. Permission problem?');
        }
    break;
    case 'getdoms':
        $settings = loadSettings();
        if($settings['DOMAINS'])
            $o = explode(',',$settings['DOMAINS']);
        else $o = [];
    break;
    case 'attachment':
        $id = intval($_REQUEST['id']);
        $filename = basename($_REQUEST['filename']);
        $filepath = $dir.DS.'attachments'.DS.$id.'-'.$filename;
        if(!is_dir($dir))
            $o = array('status'=>'err','reason'=>'No emails received on this address');
        else if(!is_numeric($id) || !emailIDExists($email,$id))
            $o = array('status'=>'err','reason'=>'Invalid Email ID');
        else if(!file_exists($filepath))
            $o = array('status'=>'err','reason'=>'File not found');
        else
        {
            header('Content-Type: '.mime_content_type($filepath));
            readfile($filepath);
            exit();
        }
    break;

    case 'load':
        $id = intval($_REQUEST['id']);
        if(empty($email))
            $o = array('status'=>'err','reason'=>'No email address provided');
        else if(!is_dir($dir))
            $o = array('status'=>'err','reason'=>'No emails received on this address');
        else if(!is_numeric($id) || !emailIDExists($email,$id))
            $o = array('status'=>'err','reason'=>'Invalid Email ID');
        else
        {
            $data = getEmail($email,$id);
            if($_REQUEST['raw']=='true')
            {
                header('Content-Type: text/plain');
                exit($data['raw']);
            }
            $o = array('status'=>'ok','data'=>$data);
        }
    break;

    case 'list':
        $settings = loadSettings();
        /*
        
        if($settings['ADMIN'] && $settings['ADMIN']==$admincheck)
        {
            $o['status'] = 'ok';
            $o['type'] = 'admin';
                        $o['dateformat'] = $settings['DATEFORMAT'];
            $emails = listEmailAdresses();
            $emaillist = array();
            
            if(count($emails)>0)
            {
                foreach($emails as $email)
                {
                    $emaildata = getEmailsOfEmail($email);
                    foreach($emaildata as $time=>$d)
                        $emaillist[$time.'-'.$email]=$d;
                }
                if(is_array($emaillist))
                    krsort($emaillist);
                $data = (count($emaillist)?$emaillist:array());
            }

            $o['emails']=$data;
        }
        else */
        
        if(!is_dir($dir) && $settings['ADMIN']!=$admincheck)
            $o = array('status'=>'ok','emails'=>[]);
        else
        {
            if(!$email) $email = $admincheck;
            $data = getEmailsOfEmail($email);
            $lastid = $_REQUEST['lastid'];
            if($lastid && is_numeric($lastid))
            {
                foreach($data as $time=>$d)
                {
                    if($time>$lastid)
                        $emails[$time]=$d;
                }
                $data = (is_array($emails)?$emails:array());
            }
            
            $o = array('status'=>'ok','emails'=>$data); 
        }
    break;
    case 'fakename-enabled':
        $settings = loadSettings();
        $o = $settings['FAKENAME_GENERATOR'];
    break;
    case 'fakename-suffix':
        $settings = loadSettings();
        $o = $settings['FAKENAME_SUFFIX'];
    break;
}

echo json_encode($o);
