<article>
    <header>Subject: <?= escape($emaildata['parsed']['subject']) ?></header>
    <header>
        Reciepients:
        <div>
            <?php foreach ($emaildata['rcpts'] as $to) : ?>
                <small class="badge"><?= escape($to) ?></small>
            <?php endforeach; ?>
        </div>
    </header>
    <?= nl2br(escape($emaildata['parsed']['body'])) ?>
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


<script>history.pushState({email:"<?= $email ?>"}, "", "/eml/<?= $email ?>/<?= $mailid ?>");</script>