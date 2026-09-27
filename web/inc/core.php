<?php

function getDirForEmail($email)
{
    return realpath(ROOT.DS.'..'.DS.'data'.DS.strtolower($email));
}

function startsWith($haystack, $needle)
{
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

function isEmailFile($filename)
{
    return preg_match('/^\d+\.json$/', $filename) === 1;
}

function getEmail($email,$id)
{
    return json_decode(file_get_contents(getDirForEmail($email).DS.$id.'.json'),true);
}

function getRawEmail($email,$id)
{
    $data = json_decode(file_get_contents(getDirForEmail($email).DS.$id.'.json'),true);

    return $data['raw'];
}

function emailIDExists($email,$id)
{
    return file_exists(getDirForEmail($email).DS.$id.'.json');
}

function getEmailsOfEmail($email,$includebody=false,$includeattachments=false)
{
    $o = [];
    $settings = loadSettings();

    if($settings['ADMIN'] && $settings['ADMIN']==$email)
    {
        $emails = listEmailAdresses();
        if(count($emails)>0)
        {
            foreach($emails as $email)
            {
                if ($handle = opendir(getDirForEmail($email))) {
                    while (false !== ($entry = readdir($handle))) {
                        if (isEmailFile($entry)) {
                            $time = substr($entry,0,-5);
                            $json = json_decode(file_get_contents(getDirForEmail($email).DS.$entry),true);
                            $key = $time.'-'.$email; // same id exists in every mailbox of a mail with multiple recipients
                            $o[$key] = array(
                                'email'=>$email,'id'=>$time,
                                'from'=>$json['parsed']['from'],
                                'subject'=>$json['parsed']['subject'],
                                'md5'=>md5($time.$json['raw']),
                                'maillen'=>strlen($json['raw'])
                            );
                            if($includebody==true)
                                $o[$key]['body'] = $json['parsed']['body'];
                                if($includeattachments==true)
                                {
                                    $o[$key]['attachments'] = $json['parsed']['attachments'];
                                    //add url to attachments
                                    foreach($o[$key]['attachments'] as $k=>$v)
                                        $o[$key]['attachments'][$k] = $settings['URL'].'/api/attachment/'.rawurlencode($email).'/'.rawurlencode($v);
                                }
                        }
                    }
                    closedir($handle);
                }
            }
        }
    }
    else
    {
        if ($handle = opendir(getDirForEmail($email))) {
            while (false !== ($entry = readdir($handle))) {
                if (isEmailFile($entry)) {
                    $time = substr($entry,0,-5);
                    $json = json_decode(file_get_contents(getDirForEmail($email).DS.$entry),true);
                    $o[$time] = array(
                                        'email'=>$email,
                                        'id'=>$time,
                                        'from'=>$json['parsed']['from'],
                                        'subject'=>$json['parsed']['subject'],
                                        'md5'=>md5($time.$json['raw']),'maillen'=>strlen($json['raw'])
                                    );
                                    if($includebody==true)
                                        $o[$time]['body'] = $json['parsed']['body'];
                                    if($includeattachments==true)
                                    {
                                        $o[$time]['attachments'] = $json['parsed']['attachments'];
                                        //add url to attachments
                                        foreach($o[$time]['attachments'] as $k=>$v)
                                            $o[$time]['attachments'][$k] = $settings['URL'].'/api/attachment/'.rawurlencode($email).'/'.rawurlencode($v);
                                    }
                }                   
            }
            closedir($handle);
        }
    }

    if(is_array($o))
        ksort($o);

    return $o;
}

function listEmailAdresses()
{
    $o = array();
    if ($handle = opendir(ROOT.DS.'..'.DS.'data'.DS)) {
        while (false !== ($entry = readdir($handle))) {
            if(filter_var($entry, FILTER_VALIDATE_EMAIL))
                $o[] = $entry;
        }
        closedir($handle);
    }

    return $o;
}

function attachmentExists($email,$id,$attachment=false)
{
    return is_file(getDirForEmail($email).DS.'attachments'.DS.basename($id.(($attachment)?'-'.$attachment:'')));
}

function listAttachmentsOfMailID($email,$id)
{
    $data = json_decode(file_get_contents(getDirForEmail($email).DS.$id.'.json'),true);
    $attachments = $data['parsed']['attachments'];
    if(!is_array($attachments))
        return [];
    else
        return $attachments;
}

function deleteEmail($email,$id)
{
    $dir = getDirForEmail($email);
    $attachments = listAttachmentsOfMailID($email,$id);
    foreach($attachments as $attachment)
        if(file_exists($dir.DS.'attachments'.DS.basename($attachment)))
            unlink($dir.DS.'attachments'.DS.basename($attachment));
    return unlink($dir.DS.$id.'.json');
}


// Path prefix when the web UI is not hosted at the root of a domain, taken from the URL setting (eg. https://example.com/trashmail -> /trashmail)
function getBasePath($settings)
{
    $path = $settings ? parse_url(trim($settings['URL'] ?? ''), PHP_URL_PATH) : '';
    return rtrim($path ?: '', '/');
}

function loadSettings()
{
    if(file_exists(ROOT.DS.'..'.DS.'config.ini'))
        return parse_ini_file(ROOT.DS.'..'.DS.'config.ini');
    return false;
}


function escape($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function array2ul($array)
{
    $out = "<ul>";
    foreach ($array as $key => $elem) {
        $out .= "<li>$elem</li>";
    }
    $out .= "</ul>";
    return $out;
}

function tailShell($filepath, $lines = 1) {
    ob_start();
    passthru('tail -'  . $lines . ' ' . escapeshellarg($filepath));
    return trim(ob_get_clean());
}

function getUserIP()
{
	$remote  = $_SERVER['REMOTE_ADDR'];
    // Proxy headers can be set by anyone. Only trust them if the request comes from a reverse proxy
    // in a private network or from one configured in TRUSTED_PROXIES (eg. Cloudflare's IP ranges)
    $settings = loadSettings();
    $trusted = !filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    if(!$trusted && !empty($settings['TRUSTED_PROXIES']))
        $trusted = isIPInRange($remote, $settings['TRUSTED_PROXIES']);
    if(!$trusted)
        return $remote;
    if(filter_var(@$_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP))
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
	$client  = @$_SERVER['HTTP_CLIENT_IP'];
	$forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
	
    if(strpos($forward,','))
    {
        $a = explode(',',$forward);
        $forward = trim($a[0]);
    }
	if(filter_var($forward, FILTER_VALIDATE_IP))
	{
		$ip = $forward;
	}
    elseif(filter_var($client, FILTER_VALIDATE_IP))
	{
		$ip = $client;
	}
	else
	{
		$ip = $remote;
	}
	return $ip;
}

/**
 * Check if a given IPv4 or IPv6 is in a network
 * @param  string $ip    IP to check in IPV4 format eg. 127.0.0.1
 * @param  string $range IP/CIDR netmask eg. 127.0.0.0/24, or 2001:db8::8a2e:370:7334/128
 * @return boolean true if the ip is in this range / false if not.
 * via https://stackoverflow.com/a/56050595/1174516
 */
function isIPInRange( $ip, $range ) {

    if(strpos($range,',')!==false)
    {
        // we got a list of ranges. splitting
        $ranges = array_map('trim',explode(',',$range));
        foreach($ranges as $range)
            if(isIPInRange($ip,$range)) return true;
        return false;
    }
    // Get mask bits
    list($net, $maskBits) = explode('/', $range);

    // Size
    $size = (strpos($ip, ':') === false) ? 4 : 16;

    // Convert to binary
    $ip = inet_pton($ip);
    $net = inet_pton($net);
    if (!$ip || !$net) {
        throw new InvalidArgumentException('Invalid IP address');
    }

    // Build mask
    $solid = floor($maskBits / 8);
    $solidBits = $solid * 8;
    $mask = str_repeat(chr(255), $solid);
    for ($i = $solidBits; $i < $maskBits; $i += 8) {
        $bits = max(0, min(8, $maskBits - $i));
        $mask .= chr((pow(2, $bits) - 1) << (8 - $bits));
    }
    $mask = str_pad($mask, $size, chr(0));

    // Compare the mask
    return ($ip & $mask) === ($net & $mask);
}

function getVersion()
{
    if(file_exists(ROOT.DS.'..'.DS.'VERSION'))
        return trim(file_get_contents(ROOT.DS.'..'.DS.'VERSION'));
    else return '';
}

// Word lists used for random addresses. Replace wordlists/adjectives.txt and wordlists/nouns.txt
// (eg. by mounting your own files in Docker) to use your own words. One word per line
function loadWordList($name)
{
    $file = ROOT.DS.'..'.DS.'wordlists'.DS.$name.'.txt';
    $lines = is_readable($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    // only words that are valid in an email address
    return array_values(array_filter(array_map(fn($w) => strtolower(trim($w)), $lines), fn($w) => preg_match('/^[a-z0-9-]+$/', $w)));
}

function generateRandomEmail()
{
    $nouns = loadWordList('nouns');
    $adjectives = loadWordList('adjectives');
    $word = function($list) {
        return $list ? $list[array_rand($list)] : bin2hex(random_bytes(4));
    };

    $settings = loadSettings();
    $domains = array_map('trim', explode(',', $settings['DOMAINS']));
    $dom = $domains[array_rand($domains)];

    while (strpos($dom, '*') !== false) {
        $dom = preg_replace('/\*/', $word($nouns), $dom, 1);
    }

    return $word($adjectives) . '.' . $word($nouns).'@'.$dom;
}

function removeScriptsFromHtml($html) {
    // Remove script tags
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $html);

    // Remove event attributes that execute scripts
    $html = preg_replace('/\bon\w+="[^"]*"/i', "", $html);

    // Remove href attributes that execute scripts
    $html = preg_replace('/\bhref="javascript[^"]*"/i', "", $html);

    // Remove any other attributes that execute scripts
    $html = preg_replace('/\b\w+="[^"]*\bon\w+="[^"]*"[^>]*>/i', "", $html);

    return $html;
}

function countEmailsOfAddress($email)
{
    $count = 0;
    if ($handle = opendir(getDirForEmail($email))) {
        while (false !== ($entry = readdir($handle)))
            if (isEmailFile($entry))
                $count++;
        closedir($handle);
    }
    return $count;
}

function delTree($dir) {

    $files = array_diff(scandir($dir), array('.','..'));
     foreach ($files as $file) {
       (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
     }
     return rmdir($dir);
 
   }

function getWebhookConfig($email)
{
    $webhookFile = getDirForEmail($email).DS.'webhook.json';
    if (file_exists($webhookFile)) {
        return json_decode(file_get_contents($webhookFile), true);
    }
    return null;
}

function saveWebhookConfig($email, $config)
{
    // Validate email format first
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    $dir = getDirForEmail($email);
    if (!$dir) {
        return false;
    }
    
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            return false;
        }
    }
    $webhookFile = $dir.DS.'webhook.json';
    return file_put_contents($webhookFile, json_encode($config, JSON_PRETTY_PRINT)) !== false;
}

function deleteWebhookConfig($email)
{
    $webhookFile = getDirForEmail($email).DS.'webhook.json';
    if (file_exists($webhookFile)) {
        return unlink($webhookFile);
    }
    return true;
}