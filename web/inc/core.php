<?php

function getDirForEmail($email)
{
    return realpath(ROOT.DS.'..'.DS.'data'.DS.$email);
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

function getEmailsOfEmail($email)
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
                        if (endsWith($entry,'.json')) {
                            $time = substr($entry,0,-5);
                            $json = json_decode(file_get_contents(getDirForEmail($email).DS.$entry),true);
                            $o[$time] = array(
                                'email'=>$email,'id'=>$time,
                                'from'=>$json['parsed']['from'],
                                'subject'=>$json['parsed']['subject'],
                                'md5'=>md5($time.$json['raw']),
                                'maillen'=>strlen($json['raw'])
                            );
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
                if (endsWith($entry,'.json')) {
                    $time = substr($entry,0,-5);
                    $json = json_decode(file_get_contents(getDirForEmail($email).DS.$entry),true);
                    $o[$time] = array('email'=>$email,'id'=>$time,'from'=>$json['parsed']['from'],'subject'=>$json['parsed']['subject'],'md5'=>md5($time.$json['raw']),'maillen'=>strlen($json['raw']));
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

function attachmentExists($email,$id,$attachment)
{
    return file_exists(getDirForEmail($email).DS.'attachments'.DS.$id.'-'.$attachment);
}

function listAttachmentsOfMailID($email,$id)
{
    $o = array();
    if ($handle = opendir(getDirForEmail($email).DS.'attachments')) {
        while (false !== ($entry = readdir($handle))) {
            if (startsWith($entry,$id.'-')) {
                $o[] = $entry;
            }
        }
        closedir($handle);
    }

    return $o;
}

function deleteEmail($email,$id)
{
    $dir = getDirForEmail($email);
    $attachments = listAttachmentsOfMailID($email,$id);
    foreach($attachments as $attachment)
        unlink($dir.DS.'attachments'.DS.$attachment);
    return unlink($dir.DS.$id.'.json');
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