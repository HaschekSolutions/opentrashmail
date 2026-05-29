<a href="#" hx-push-url="<?= $url ?>/logs/10" hx-get="<?= $url ?>/api/logs/10" <?= $lines==10?'disabled':'' ?> hx-target="#adminmain" role="button">Last 10 lines</a>
<a href="#" hx-push-url="<?= $url ?>/logs/50" hx-get="<?= $url ?>/api/logs/50" <?= $lines==50?'disabled':'' ?> hx-target="#adminmain" role="button">Last 50 lines</a>
<a href="#" hx-push-url="<?= $url ?>/logs/100" hx-get="<?= $url ?>/api/logs/100" <?= $lines==100?'disabled':'' ?> hx-target="#adminmain" role="button">Last 100 lines</a>
<a href="#" hx-push-url="<?= $url ?>/logs/200" hx-get="<?= $url ?>/api/logs/200" <?= $lines==200?'disabled':'' ?> hx-target="#adminmain" role="button">Last 200 lines</a>
<a href="#" hx-push-url="<?= $url ?>/logs/500" hx-get="<?= $url ?>/api/logs/500" <?= $lines==500?'disabled':'' ?> hx-target="#adminmain" role="button">Last 500 lines</a>

<hr>

<h2>Mailserver log</h2>
<div>
    <pre><code class="language-log"><?= file_exists($mailserverlogfile)?tailShell($mailserverlogfile, $lines):'- Mailserver log file not found -' ?></code><pre>
</div>

<h2>Webserver error log</h2>
<div>
    <pre><code class="language-log"><?= file_exists($webservererrorlogfile)?tailShell($webservererrorlogfile, $lines):'- Webserver error log file not found -' ?></code><pre>
</div>

<h2>Webserver access log</h2>
<div>
    <pre><code class="language-log"><?= file_exists($webserveraccesslogfile)?tailShell($webserveraccesslogfile, $lines):'- Webserver access log file not found -' ?></code><pre>
</div>

<h2>Current config</h2>
<div>
    <pre><code class="language-ini"><?= file_exists($configfile)?file_get_contents($configfile):'- Config file not found -' ?></code><pre>
</div>

<script src="<?= $url ?>/js/prism.js"></script>
