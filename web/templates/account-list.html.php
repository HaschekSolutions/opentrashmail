<table>
  <thead>
    <tr>
      <th scope="col">Email Addess</th>
      <th>Emails in Inbox</th>
      <th>Action</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($emails as $email): ?>
        <tr>
            <td>
                <a href="/address/<?= $email; ?>" hx-get="/api/address/<?= $email; ?>" hx-push-url="/address/<?= $email; ?>" hx-target="#main">
                    <?= escape($email) ?>
                </a>
            </td>
            <td><?= countEmailsOfAddress($email); ?></td>
            <td>
                <input type="submit" value="Delete" hx-get="/api/deleteaccount/<?= $email ?>" hx-confirm="Are you sure to delete this account and all its emails?" hx-target="closest tr" hx-swap="outerHTML swap:1s">
            </td>
        </tr>
    <?php endforeach; ?>
  </tbody>
</table>