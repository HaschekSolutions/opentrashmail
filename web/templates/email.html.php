<nav aria-label="breadcrumb">
  <ul>
    <li><a href="/address/<?= $email ?>" hx-get="/api/address/<?= $email ?>" hx-target="#main"><?= escape($email) ?></a></li>
    <li><?= escape($emaildata['parsed']['subject']) ?></li>
  </ul>
</nav>

<article>
    <header>
        <p>Subject: <?= escape($emaildata['parsed']['subject']) ?></p>
        <p>Received: <span id="date2-<?= $mailid ?>"><script>document.getElementById('date2-<?= $mailid ?>').innerHTML = moment.unix(parseInt(<?=$mailid?>/1000)).format('<?= $dateformat; ?>');</script></span></p>

        <p>
            Recipients:
            <?php foreach ($emaildata['rcpts'] as $to) : ?>
                <small class="badge"><?= escape($to) ?></small>
            <?php endforeach; ?>
        </p>
    </header>
    
    <div id="emailbody">
        <?php if($emaildata['parsed']['htmlbody']): ?>
            <a href="#" hx-confirm="Warning: HTML may contain tracking functionality or scripts. Do you want to proceed?" hx-get="/api/raw-html/<?= $email ?>/<?= $mailid ?>" hx-target="#emailbody" role="button" class="secondary outline">Render email in HTML</a>
        <?php endif; ?>
        <hr>
        <pre><?= nl2br(escape($emaildata['parsed']['body'])) ?></pre>
    </div>
    <footer>
        Attachments
        <div>
            <?php if (count($emaildata['parsed']['attachments']) == 0) : ?>
                <small class="secondary">No attachments</small>
            <?php endif; ?>
            <ul>
                <?php foreach ($emaildata['parsed']['attachments'] as $attachment) : ?>
                    <li>
                        <a target="_blank" href="/api/attachment/<?= $email ?>/<?= $attachment ?>"><?= escape($attachment) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </footer>
</article>

<article>
    <header>Raw email</header>
    <a href="/api/raw/<?= $email ?>/<?= $mailid ?>" target="_blank">Open in new Window</a>
    <pre><button hx-get="/api/raw/<?= $email ?>/<?= $mailid ?>" hx-swap="outerHTML">Load Raw Email</button></pre>
</article>

<!-- 
<script>history.pushState({email:"<?= $email ?>",id:"<?= $mailid ?>"}, "", "/read/<?= $email ?>/<?= $mailid ?>");</script> -->
