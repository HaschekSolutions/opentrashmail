<?xml version="1.0" ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <atom:link href="<?= escape($url) ?>/rss/<?= escape(rawurlencode($email)) ?>" rel="self" type="application/rss+xml" />
  <title>RSS for <?= escape($email) ?></title>
  <link><?= escape($url) ?>/address/<?= escape(rawurlencode($email)) ?></link>
  <description>RSS Feed for email address <?= escape($email) ?></description>
  <lastBuildDate><?= date(DateTime::RFC2822, time()) ?></lastBuildDate>
  <?php foreach ($emaildata as $id => $d): 
    $data = getEmail($email, $id);
    $time = substr($id, 0, -3);
    $att_text = [];
    if (is_array($data['parsed']['attachments']))
        foreach ($data['parsed']['attachments'] as $filename) {
            $fn = preg_replace('/^\d+-/', '', $filename);
            $att_url = $url . '/api/attachment/' . rawurlencode($email) . '/' . rawurlencode($filename);
            $att_text[] = "<a href='".escape($att_url)."' target='_blank'>".escape($fn)."</a>";
        }
  ?>
    <item>
        <title><?= escape($data['parsed']['subject']) ?></title>
        <pubDate><?= date(DateTime::RFC2822, $time) ?></pubDate>
        <link><?= escape($url) ?>/read/<?= escape(rawurlencode($email)) ?>/<?= $id ?></link>
        <description>
            <![CDATA[
            Email from: <?= escape($d['from']) ?><br/>
            Email to: <?= escape(implode(';',$data['rcpts'])) ?><br/>
            <?= ((count($att_text) > 0) ? 'Attachments:<br/>' . array2ul($att_text) . '<br/>' : '') ?>
            <a href="<?= escape($url) ?>/api/raw/<?= escape(rawurlencode($email)) ?>/<?= $id ?>">View raw email</a> <br/>
            <br/>---------<br/><br/>
            <?= str_replace(']]>', ']]]]><![CDATA[>', ($data['parsed']['htmlbody'] ? removeScriptsFromHtml($data['parsed']['htmlbody']) : nl2br(escape($data['parsed']['body'])))) ?>
            ]]>
        </description>
    </item>
    <?php endforeach; ?>
</channel>
</rss> 