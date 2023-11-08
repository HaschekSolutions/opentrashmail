<article>
    <header>Subject: <?= escape($email['parsed']['subject']) ?></header>
    <header>
        Reciepients:
        <div>
            <?php foreach($email['rcpts'] as $to): ?>
                <small class="badge"><?= escape($to) ?></small>
            <?php endforeach; ?>
            </div>
    </header>
    <?= nl2br(escape($email['parsed']['body'])) ?>
    <footer>
        Attachments
        <div>
            <?php if(count($email['parsed']['attachments'])==0): ?>
                <small class="secondary">No attachments</small>
            <?php endif; ?>
            <ul>
                <?php foreach($email['parsed']['attachments'] as $attachment): ?>
                    <li>
                        <a href="/api/attachment/<?= $mailid ?>/<?= $attachment ?>"><?= escape($attachment) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
            </div>
    </footer>
</article>

<div hx-push-url="/eml/<?= $email ?>/<?= $mailid ?>" hx-trigger="load">