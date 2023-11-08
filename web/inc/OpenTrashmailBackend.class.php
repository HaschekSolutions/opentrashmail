<?php

class OpenTrashmailBackend{
    private $url;
    private $settings;

    public function __construct($url){
        $this->url = $url;
        $this->settings = loadSettings();
    }
    public function run(){

        // api calls
        if($this->url[0]=='api')
        {
            switch($this->url[1]){
                case 'address':
                    return $this->listAccount($_REQUEST['email']?:$this->url[2]);
                case 'read':
                    return $this->readMail($_REQUEST['email'],$_REQUEST['id']);
                case 'attachment':
                    return $this->getAttachment($this->url[2],$this->url[3],$this->url[4]);
                case 'delete':
                    return $this->deleteMail($_REQUEST['email'],$_REQUEST['id']);
                default:
                    return false;
            }
        }

        // rss feed
        else if($this->url[0]=='rss')
        {
            header("Content-Type: application/rss+xml; charset=UTF8");
            $email = $this->url[1];
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(404);
                exit('Error: Email not found');
            }
            return $this->renderPartial('rss.xml',[
                'email'=>$email,
                'emaildata'=>getEmailsOfEmail($email),
                'url'=>$this->settings['URL'],
            ]);
        }

        else return false;
    }

    function getAttachment($email,$id,$attachment)
    {
        if(!filter_var($email, FILTER_VALIDATE_EMAIL))
            return $this->error('Invalid email address');
        else if(!ctype_digit($id))
            return $this->error('Invalid id');
        else if(!emailIDExists($email,$id))
            return $this->error('Email not found');
        else if(!attachmentExists($email,$id,$attachment))
            return $this->error('Attachment not found');
        $dir = getDirForEmail($email);
        $file = $dir.DS.'attachments'.DS.$id.'-'.$attachment;
        $mime = mime_content_type($file);
        header('Content-Type: '.$mime);
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    function readMail($email,$id)
    {
        if(!filter_var($email, FILTER_VALIDATE_EMAIL))
            return $this->error('Invalid email address');
        else if(!ctype_digit($id))
            return $this->error('Invalid id');
        else if(!emailIDExists($email,$id))
            return $this->error('Email not found');
        $email = getEmail($email,$id);
        //$email['raw'] = file_get_contents(getDirForEmail($email['email']).DS.$email['id'].'.json');
        //$email['parsed'] = json_decode($email['raw'],true);

        var_dump($email);
        return $this->renderPartial('email.html',[
            'email'=>$email,
            'mailid'=>$id,
        ]);

    }

    public function listAccount($email)
    {
        if(!filter_var($email, FILTER_VALIDATE_EMAIL))
            return $this->error('Invalid email address');
        $emails = getEmailsOfEmail($email);
        var_dump($emails);
        return $this->renderPartial('email-table.html',[
            'email'=>$email,
            'emails'=>$emails,
            'dateformat'=>$this->settings['DATEFORMAT']
        ]);
    }

    public function error($text)
    {
        return '<h1>'.$text.'</h1>';
    }

    public function renderPartial($partialname,$variables=[])
    {
        ob_start();
        if(is_array($variables))
            extract($variables);
        if(file_exists(ROOT.DS.'partials'.DS.$partialname.'.php'))
            include(ROOT.DS.'partials'.DS.$partialname.'.php');
        $rendered = ob_get_contents();
        ob_end_clean();
    
        return $rendered;
    }

}