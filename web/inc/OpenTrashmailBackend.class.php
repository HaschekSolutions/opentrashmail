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
                    return $this->readMail($_REQUEST['email']?:$this->url[2],$_REQUEST['id']?:$this->url[3]);
                case 'listaccounts':
                    if($this->settings['SHOW_ACCOUNT_LIST'] && (($this->settings['ADMIN_PASSWORD'] != "" && $_SESSION['admin'])|| !$this->settings['ADMIN_PASSWORD']))
                        return $this->listAccounts();
                    else return '403 Forbidden';
                case 'raw-html':
                    return $this->getRawMail($this->url[2],$this->url[3],true);
                case 'raw':
                    return $this->getRawMail($this->url[2],$this->url[3]);
                case 'attachment':
                    return $this->getAttachment($this->url[2],$this->url[3]);
                case 'delete':
                    return $this->deleteMail($_REQUEST['email']?:$this->url[2],$_REQUEST['id']?:$this->url[3]);
                case 'random':
                    $addr = generateRandomEmail();
                    return $this->listAccount($addr);
                case 'deleteaccount':
                    return $this->deleteAccount($_REQUEST['email']?:$this->url[2]);
                case 'logs':
                    if($this->settings['SHOW_LOGS'] && (($this->settings['ADMIN_PASSWORD'] != "" && $_SESSION['admin'])|| !$this->settings['ADMIN_PASSWORD']))
                        return $this->renderTemplate('logs.html',[
                            'lines' => (is_numeric($this->url[2])&&$this->url[2]>0)?$this->url[2]:100,
                            'mailserverlogfile'=>ROOT.DS.'../logs'.DS.'mailserver.log',
                            'webservererrorlogfile'=>ROOT.DS.'../logs'.DS.'web.error.log',
                            'webserveraccesslogfile'=>ROOT.DS.'../logs'.DS.'web.access.log',
                            'configfile' => ROOT.DS.'../config.ini',
                        ]);
                    else return '403 Forbidden';
                case 'admin':
                    if($this->settings['ADMIN_ENABLED']==true)
                        return $this->renderTemplate('admin.html',[
                            'settings'=>$this->settings,
                        ]);
                    else return '403 Not activated in config.ini';
                case 'webhook':
                    if($this->url[2] == 'get') {
                        return $this->getWebhook($_REQUEST['email']?:$this->url[3]);
                    } else if($this->url[2] == 'save') {
                        return $this->saveWebhook($_REQUEST['email']?:$this->url[3], $_REQUEST);
                    } else if($this->url[2] == 'delete') {
                        return $this->deleteWebhook($_REQUEST['email']?:$this->url[3]);
                    }
                    break;
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
            return $this->renderTemplate('rss.xml',[
                'email'=>$email,
                'emaildata'=>getEmailsOfEmail($email),
                'url'=>$this->settings['URL'],
            ]);
        }

        //json api
        else if($this->url[0]=='json')
        {
            header("Content-Type: application/json; charset=UTF8");
            if($this->url[1]=='listaccounts')
            {
                if($this->settings['SHOW_ACCOUNT_LIST'] && (($this->settings['ADMIN_PASSWORD'] != "" && $_REQUEST['password']==$this->settings['ADMIN_PASSWORD'])|| !$this->settings['ADMIN_PASSWORD']))
                    return json_encode(listEmailAdresses());
                else exit(json_encode(['error'=>'403 Forbidden']));
            }
            $email = $this->url[1];
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(404);
                exit(json_encode(['error'=>'Email not found']));
            }
            $id = $this->url[2];
            if($id) //user wants a specific email ID
            {
                if(!emailIDExists($email,$id))
                {
                    http_response_code(404);
                    exit(json_encode(['error'=>'Email ID not found']));
                }
                else if(!is_numeric($id))
                {
                    http_response_code(400);
                    exit(json_encode(['error'=>'Invalid ID']));
                }
                else
                    return json_encode(getEmail($email,$id));
            }
            else
                return json_encode(getEmailsOfEmail($email,true,true));
        }

        else return false;
    }
    
    function deleteAccount($email)
    {
        if(!filter_var($email, FILTER_VALIDATE_EMAIL))
            return $this->error('Invalid email address');
        $path = getDirForEmail($email);
        if(is_dir($path))
            delTree($path);
    }

    function listAccounts()
    {
        $accounts = listEmailAdresses();
        return $this->renderTemplate('account-list.html',[
            'emails'=>$accounts,
            'dateformat'=>$this->settings['DATEFORMAT']
        ]);
    }

    function deleteMail($email,$id)
    {
        if(!filter_var($email, FILTER_VALIDATE_EMAIL))
            return $this->error('Invalid email address');
        else if(!is_numeric($id))
            return $this->error('Invalid id');
        else if(!emailIDExists($email,$id))
            return $this->error('Email not found');
        deleteEmail($email,$id);
        return '';
    }

    function getRawMail($email,$id,$htmlbody=false)
    {
        if(!filter_var($email, FILTER_VALIDATE_EMAIL))
            return $this->error('Invalid email address');
        else if(!is_numeric($id))
            return $this->error('Invalid id');
        else if(!emailIDExists($email,$id))
            return $this->error('Email not found');
        $emaildata = getEmail($email,$id);
        if($htmlbody)
            exit($emaildata['parsed']['htmlbody']);
        header('Content-Type: text/plain');
        echo $emaildata['raw'];
        exit;
    }

    function getAttachment($email,$attachment)
    {
        $attachment = basename(urldecode($attachment));
        if(!filter_var($email, FILTER_VALIDATE_EMAIL))
            return $this->error('Invalid email address');
        else if(!attachmentExists($email,$attachment))
            return $this->error('Attachment not found');
        $dir = getDirForEmail($email);
        $file = $dir.DS.'attachments'.DS.$attachment;
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
        else if(!is_numeric($id))
            return $this->error('Invalid id');
        else if(!emailIDExists($email,$id))
            return $this->error('Email not found');
        $emaildata = getEmail($email,$id);
        //$email['raw'] = file_get_contents(getDirForEmail($email['email']).DS.$email['id'].'.json');
        //$email['parsed'] = json_decode($email['raw'],true);

        //var_dump($emaildata);
        return $this->renderTemplate('email.html',[
            'emaildata'=>$emaildata,
            'email'=>$email,
            'mailid'=>$id,
            'dateformat'=>$this->settings['DATEFORMAT']
        ]);

    }

    public function listAccount($email)
    {
        if(!filter_var($email, FILTER_VALIDATE_EMAIL))
            return $this->error('Invalid email address');
        $emails = getEmailsOfEmail($email);
        //var_dump($emails);
        return $this->renderTemplate('email-table.html',[
            'isadmin'=>($this->settings['ADMIN']==$email),
            'email'=>$email,
            'emails'=>$emails,
            'dateformat'=>$this->settings['DATEFORMAT']
        ]);
    }

    public function error($text)
    {
        return '<h1>'.$text.'</h1>';
    }

    public function renderTemplate($templatename,$variables=[])
    {
        ob_start();
        if(is_array($variables))
            extract($variables);
        if(file_exists(ROOT.DS.'templates'.DS.$templatename.'.php'))
            include(ROOT.DS.'templates'.DS.$templatename.'.php');
        else if(file_exists(ROOT.DS.'templates'.DS.$templatename))
            include(ROOT.DS.'templates'.DS.$templatename);
        $rendered = ob_get_contents();
        ob_end_clean();
    
        return $rendered;
    }

    public function getWebhook($email)
    {
        if(!filter_var($email, FILTER_VALIDATE_EMAIL))
            return $this->error('Invalid email address');
        
        header("Content-Type: application/json; charset=UTF8");
        $config = getWebhookConfig($email);
        return json_encode($config ?: ['enabled' => false]);
    }

    public function saveWebhook($email, $data)
    {
        if(!filter_var($email, FILTER_VALIDATE_EMAIL))
            return $this->error('Invalid email address');
        
        header("Content-Type: application/json; charset=UTF8");
        
        // Validate webhook URL
        $webhook_url = isset($data['webhook_url']) ? filter_var($data['webhook_url'], FILTER_SANITIZE_URL) : '';
        if($webhook_url && !filter_var($webhook_url, FILTER_VALIDATE_URL)) {
            return json_encode(['success' => false, 'message' => 'Invalid webhook URL']);
        }
        
        // Prevent SSRF by blocking internal IPs and local domains
        if($webhook_url) {
            $parsed = parse_url($webhook_url);
            $host = $parsed['host'] ?? '';
            
            // Block localhost and common internal hostnames
            $blocked_hosts = ['localhost', '127.0.0.1', '0.0.0.0', '[::1]', 'host.docker.internal'];
            if(in_array(strtolower($host), $blocked_hosts)) {
                return json_encode(['success' => false, 'message' => 'Webhook URL cannot point to internal services']);
            }
            
            // Block private IP ranges
            if(filter_var($host, FILTER_VALIDATE_IP)) {
                if(!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return json_encode(['success' => false, 'message' => 'Webhook URL cannot point to private IP addresses']);
                }
            }
        }
        
        // Validate payload template is valid JSON
        $payload_template = isset($data['payload_template']) ? $data['payload_template'] : '{"email":"{{to}}","from":"{{from}}","subject":"{{subject}}","body":"{{body}}"}';
        $test_json = str_replace(['{{to}}','{{from}}','{{subject}}','{{body}}','{{htmlbody}}','{{sender_ip}}','{{attachments}}'], 
                                 ['test','test','test','test','test','test','[]'], $payload_template);
        if(json_decode($test_json) === null && json_last_error() !== JSON_ERROR_NONE) {
            return json_encode(['success' => false, 'message' => 'Invalid JSON in payload template']);
        }
        
        // Validate retry config
        $max_attempts = isset($data['max_attempts']) ? intval($data['max_attempts']) : 3;
        if($max_attempts < 1 || $max_attempts > 10) {
            return json_encode(['success' => false, 'message' => 'Max attempts must be between 1 and 10']);
        }
        
        $backoff_multiplier = isset($data['backoff_multiplier']) ? floatval($data['backoff_multiplier']) : 2;
        if($backoff_multiplier < 1 || $backoff_multiplier > 5) {
            return json_encode(['success' => false, 'message' => 'Backoff multiplier must be between 1 and 5']);
        }
        
        $config = [
            'enabled' => isset($data['enabled']) ? filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN) : false,
            'webhook_url' => $webhook_url,
            'payload_template' => $payload_template,
            'retry_config' => [
                'max_attempts' => $max_attempts,
                'backoff_multiplier' => $backoff_multiplier
            ],
            'secret_key' => isset($data['secret_key']) ? substr($data['secret_key'], 0, 255) : ''
        ];
        
        if(saveWebhookConfig($email, $config)) {
            return json_encode(['success' => true, 'message' => 'Webhook configuration saved']);
        } else {
            return json_encode(['success' => false, 'message' => 'Failed to save webhook configuration']);
        }
    }

    public function deleteWebhook($email)
    {
        if(!filter_var($email, FILTER_VALIDATE_EMAIL))
            return $this->error('Invalid email address');
        
        header("Content-Type: application/json; charset=UTF8");
        
        if(deleteWebhookConfig($email)) {
            return json_encode(['success' => true, 'message' => 'Webhook configuration deleted']);
        } else {
            return json_encode(['success' => false, 'message' => 'Failed to delete webhook configuration']);
        }
    }

}
