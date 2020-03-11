<?php 
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__FILE__));
define('DOMAIN',$_SERVER['SERVER_NAME']);

error_reporting(E_ALL || ~E_NOTICE);
ini_set('display_errors', 1);

include_once(ROOT.DS.'inc'.DS.'core.php');

header("Content-Type: application/rss+xml; charset=UTF8");

$url = explode('/',ltrim($_GET['url'],'/'));
array_shift($url);

$email = $url[0];
if(!$email)
{
    http_response_code(404);
    exit('Error: Email not found');
}

if(!filter_var($email, FILTER_VALIDATE_EMAIL)) exit();

$rss = '<?xml version="1.0" ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <atom:link href="https://'.DOMAIN.'/rss.php" rel="self" type="application/rss+xml" />
  <title>RSS for '.$email.'</title>
  <link>https://'.DOMAIN.'/#'.$email.'</link>
  <description>RSS Feed for email address '.$email.'</description>
  <lastBuildDate>'.date(DATE_RFC822,time()).'</lastBuildDate>
  <image>
      <title>RSS for '.$email.'</title>
      <url>https://raw.githubusercontent.com/HaschekSolutions/opentrashmail/master/web/imgs/logo_300.png</url>
      <link>https://github.com/HaschekSolutions/opentrashmail</link>
  </image>';

$emaildata = getEmailsOfEmail($email);
foreach($emaildata as $id=>$d)
{
    $data = getEmail($email,$id);
    //var_dump($data);
    $time = substr($id,0,-3);
    $date = date("Y-m-d H:i",$time);
    $att_text = array();
    $encl = array();
    if(is_array($data['parsed']['attachments']))
        foreach($data['parsed']['attachments'] as $filename)
        {
            $filepath = ROOT.DS.'..'.DS.'data'.DS.$email.DS.'attachments'.DS.$filename;
            $parts = explode('-',$filename);
            $fid = $parts[0];
            $fn = $parts[1];
            $url = 'https://'.DOMAIN.'/api.php?a=attachment&email='.$email.'&id='.$fid.'&filename='.$fn;
            //$encl[] = '<enclosure url="'.rawurlencode($url).'" length="'.filesize($filepath).'" type="'.mime_content_type($filepath).'" />';
            $att_text[] = "<a href='$url' target='_blank'>$fn</a>";
        }
    $rss.='
    <item>
        <title><![CDATA['.$data['parsed']['subject'].']]></title>
        <pubDate>'.date(DATE_RFC822,$time).'</pubDate>
        <link>https://'.DOMAIN.'/#'.$email.'</link>
        <description>
            <![CDATA[
            Email from: '.htmlentities($data['from']).'<br/>
            Email to: '.(is_array($data['rcpts'])?htmlentities(implode(',',$data['rcpts'])):htmlentities($email)).'<br/>
            '.((count($att_text)>0)?'Attachments:<br/>'.array2ul($att_text).'<br/>':'').'
            <a href="https://'.DOMAIN.'/api.php?a=load&email='.$email.'&id='.$id.'&raw=true">View raw email</a> <br/>
            <br/>---------<br/><br/>
            '.($data['parsed']['htmlbody']?$data['parsed']['htmlbody']:nl2br(htmlentities($data['parsed']['body']))).'
            ]]>
        </description>
        './*((count($encl)>0)?implode('<br/>',$encl):'').*/'
    </item>';

    if(++$i>5) break;
}




$rss.='</channel>
</rss> ';

echo $rss;


function array2ul($array) {
    $out = "<ul>";
    foreach($array as $key => $elem){
        $out .= "<li>$elem</li>";
    }
    $out .= "</ul>";
    return $out; 
}