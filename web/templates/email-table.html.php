<nav aria-label="breadcrumb">
  <ul>
    <li><?= escape($email) ?></li>
    <li></li>
    <li></li>
  </ul>
</nav>

<div>
  <a role="button" class="outline" href="#" id="copyemailbtn" onclick="copyEmailToClipboard();return false;"><i class="far fa-clipboard"></i> Copy address to clipboard</a>
  <a role="button" class="outline" href="/rss/<?= $email ?>" target="_blank"><i class="fas fa-rss"></i> RSS Feed</a>
  <a role="button" class="outline" href="/json/<?= $email ?>" target="_blank"><i class="fas fa-file-code"></i> JSON API</a>
  <a role="button" class="outline" href="#" onclick="openWebhookModal();return false;"><i class="fas fa-plug"></i> Configure Webhook</a>
</div>

<table role="grid">
  <thead>
    <tr>
      <th scope="col">#</th>
      <th scope="col">Date</th>
      <th scope="col">From</th>
      <?php if($isadmin==true): ?><th scope="col">To</th><?php endif; ?>
      <th scope="col">Subject</th>
      <th scope="col">Action</th>
    </tr>
  </thead>
  <tbody>

    <?php if(count($emails)==0): ?>
    <tr>
      <td colspan="5"><center>No emails received on this address (yet..)</center></td>
    </tr>
    <?php endif; ?>

    <?php foreach($emails as $unixtime => $ed): ?>
        <tr>
            <th scope="row"><?= ++$i; ?></th>
            <td id="date-td-<?= $i ?>"><script>document.getElementById('date-td-<?= $i ?>').innerHTML = moment.unix(parseInt(<?=$unixtime?>/1000)).format('<?= $dateformat; ?>');</script></td>
            <td><?= escape($ed['from']) ?></td>
            <?php if($isadmin==true): ?><td><?= $ed['email'] ?></td><?php endif; ?>
            <td><?= escape($ed['subject']) ?></td>
            <td>
              <?php if($isadmin==true): ?>
                  <a href="/read/<?= $ed['email'] ?>/<?= $ed['id'] ?>" hx-get="/api/read/<?= $ed['email'] ?>/<?= $ed['id'] ?>" hx-push-url="/read/<?= $ed['email'] ?>/<?= $ed['id'] ?>" hx-target="#main" role="button">Open</a>
                  <a href="#" hx-get="/api/delete/<?= $ed['email'] ?>/<?= $ed['id'] ?>" hx-confirm="Are you sure?" hx-target="closest tr" hx-swap="outerHTML swap:1s" role="button">Delete</a>
              <?php else: ?>
                  <a href="/read/<?= $email ?>/<?= $ed['id'] ?>" hx-get="/api/read/<?= $email ?>/<?= $ed['id'] ?>" hx-push-url="/read/<?= $email ?>/<?= $ed['id'] ?>" hx-target="#main" role="button">Open</a>
                  <a href="#" hx-get="/api/delete/<?= $email ?>/<?= $ed['id'] ?>" hx-confirm="Are you sure?" hx-target="closest tr" hx-swap="outerHTML swap:1s" role="button">Delete</a>
              <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<script>history.pushState({urlpath:"/address/<?= $email ?>"}, "", "/address/<?= $email ?>");</script>
<script>
  function copyEmailToClipboard(){
    navigator.clipboard.writeText("<?= $email ?>");
    document.getElementById('copyemailbtn').innerHTML = '<i class="fas fa-check-circle" style="color: green;"></i> Copied!';
  }
</script>

<!-- Webhook Configuration Modal -->
<dialog id="webhookModal">
  <article>
    <header>
      <h3>Webhook Configuration for <?= escape($email) ?></h3>
    </header>
    <form id="webhookForm">
      <label>
        <input type="checkbox" id="webhookEnabled" name="enabled" />
        Enable webhook for this email address
      </label>
      
      <label for="webhookUrl">
        Webhook URL
        <input type="url" id="webhookUrl" name="webhook_url" placeholder="https://api.example.com/webhook" />
      </label>
      
      <label for="payloadTemplate">
        JSON Payload Template
        <textarea id="payloadTemplate" name="payload_template" rows="10" placeholder='{"email": "{{to}}", "from": "{{from}}", "subject": "{{subject}}", "body": "{{body}}"}'>{
  "email": "{{to}}",
  "from": "{{from}}",
  "subject": "{{subject}}",
  "body": "{{body}}",
  "attachments": {{attachments}}
}</textarea>
        <small>Available placeholders: {{to}}, {{from}}, {{subject}}, {{body}}, {{htmlbody}}, {{sender_ip}}, {{attachments}}</small>
      </label>
      
      <details>
        <summary>Advanced Settings</summary>
        
        <label for="maxAttempts">
          Max Retry Attempts
          <input type="number" id="maxAttempts" name="max_attempts" min="1" max="10" value="3" />
        </label>
        
        <label for="backoffMultiplier">
          Backoff Multiplier
          <input type="number" id="backoffMultiplier" name="backoff_multiplier" min="1" max="5" step="0.5" value="2" />
        </label>
        
        <label for="secretKey">
          Secret Key (for HMAC signing)
          <input type="text" id="secretKey" name="secret_key" placeholder="Optional secret key for payload signing" />
          <small>If provided, webhook requests will include X-Webhook-Signature header with HMAC-SHA256 signature</small>
        </label>
      </details>
    </form>
    <footer>
      <button class="secondary" onclick="closeWebhookModal()">Cancel</button>
      <button onclick="saveWebhookConfig()">Save Configuration</button>
    </footer>
  </article>
</dialog>

<script>
let currentWebhookConfig = null;

async function openWebhookModal() {
  // Load current configuration
  try {
    const response = await fetch('/api/webhook/get/<?= $email ?>');
    if (response.ok) {
      currentWebhookConfig = await response.json();
      
      // Populate form with current values
      document.getElementById('webhookEnabled').checked = currentWebhookConfig.enabled || false;
      document.getElementById('webhookUrl').value = currentWebhookConfig.webhook_url || '';
      document.getElementById('payloadTemplate').value = currentWebhookConfig.payload_template || '{\n  "email": "{{to}}",\n  "from": "{{from}}",\n  "subject": "{{subject}}",\n  "body": "{{body}}",\n  "attachments": {{attachments}}\n}';
      document.getElementById('maxAttempts').value = currentWebhookConfig.retry_config?.max_attempts || 3;
      document.getElementById('backoffMultiplier').value = currentWebhookConfig.retry_config?.backoff_multiplier || 2;
      document.getElementById('secretKey').value = currentWebhookConfig.secret_key || '';
    }
  } catch (error) {
    console.error('Error loading webhook config:', error);
  }
  
  document.getElementById('webhookModal').showModal();
}

function closeWebhookModal() {
  document.getElementById('webhookModal').close();
}

async function saveWebhookConfig() {
  const formData = new FormData(document.getElementById('webhookForm'));
  const config = {
    email: '<?= $email ?>',
    enabled: formData.get('enabled') === 'on',
    webhook_url: formData.get('webhook_url'),
    payload_template: formData.get('payload_template'),
    max_attempts: parseInt(formData.get('max_attempts')),
    backoff_multiplier: parseFloat(formData.get('backoff_multiplier')),
    secret_key: formData.get('secret_key')
  };
  
  try {
    const response = await fetch('/api/webhook/save/<?= $email ?>', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams(config)
    });
    
    const result = await response.json();
    
    if (result.success) {
      alert('Webhook configuration saved successfully!');
      closeWebhookModal();
    } else {
      alert('Error saving webhook configuration: ' + result.message);
    }
  } catch (error) {
    console.error('Error saving webhook config:', error);
    alert('Error saving webhook configuration');
  }
}
</script>