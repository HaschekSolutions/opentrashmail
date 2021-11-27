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

function emailIDExists($email,$id)
{
    return file_exists(getDirForEmail($email).DS.$id.'.json');
}

function getEmailsOfEmail($email)
{
    $o = [];
    if ($handle = opendir(getDirForEmail($email))) {
        while (false !== ($entry = readdir($handle))) {
            if (endsWith($entry,'.json')) {
                $time = substr($entry,0,-5);
                $json = json_decode(file_get_contents(getDirForEmail($email).DS.$entry),true);
                $o[$time] = array('from'=>$json['parsed']['from'],'subject'=>$json['parsed']['subject']);
            }
        }
        closedir($handle);
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

function loadSettings()
{
    if(file_exists(ROOT.DS.'..'.DS.'config.ini'))
        return parse_ini_file(ROOT.DS.'..'.DS.'config.ini');
    return false;
}
