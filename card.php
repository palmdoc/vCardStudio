<?php

declare(strict_types=1);

$dataFile = __DIR__ . '/data/cards.json';

if (!file_exists($dataFile)) {
    http_response_code(404);
    exit('No cards found.');
}

$cards = json_decode((string)file_get_contents($dataFile), true);
if (!is_array($cards)) {
    http_response_code(500);
    exit('Invalid card data.');
}

$id = trim((string)($_GET['id'] ?? ''));
$card = $cards[$id] ?? null;
if (!is_array($card)) {
    http_response_code(404);
    exit('Card not found.');
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function vcfEscape(string $value): string
{
    $value = str_replace(["\r", "\n"], ['\\n', '\\n'], $value);
    return str_replace([';', ','], ['\\;', '\\,'], $value);
}

function isExternalLink(string $url): bool
{
    return (bool)preg_match('#^https?://#i', $url);
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$shareUrl = $scheme . '://' . $host . ($base !== '' ? $base : '') . '/card.php?id=' . urlencode($id);
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($shareUrl);

if (($_GET['download'] ?? '') === 'vcf') {
    $fullName = trim((string)($card['name'] ?? ''));
    $organization = trim((string)($card['company'] ?? ''));
    $title = trim((string)($card['title'] ?? ''));
    $address = trim((string)($card['address'] ?? ''));

    $lines = [
        'BEGIN:VCARD',
        'VERSION:3.0',
        'FN:' . vcfEscape($fullName),
    ];

    if ($organization !== '') {
        $lines[] = 'ORG:' . vcfEscape($organization);
    }
    if ($title !== '') {
        $lines[] = 'TITLE:' . vcfEscape($title);
    }
    if ($address !== '') {
        $lines[] = 'ADR:;;' . vcfEscape($address) . ';;;;';
    }

    foreach (($card['phones'] ?? []) as $phone) {
        $lines[] = 'TEL;TYPE=CELL:' . preg_replace('/\s+/', '', (string)$phone);
    }
    if (!empty($card['whatsapp'])) {
        $lines[] = 'TEL;TYPE=WORK,VOICE:' . preg_replace('/\D+/', '', (string)$card['whatsapp']);
    }
    foreach (($card['emails'] ?? []) as $email) {
        $lines[] = 'EMAIL;TYPE=INTERNET:' . trim((string)$email);
    }

    if (!empty($card['links'][0]['url'])) {
        $lines[] = 'URL:' . trim((string)$card['links'][0]['url']);
    }

    $lines[] = 'END:VCARD';

    header('Content-Type: text/vcard; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9\-_]/i', '_', $fullName ?: 'contact') . '.vcf"');
    echo implode("\r\n", $lines) . "\r\n";
    exit;
}

$name = trim((string)($card['name'] ?? ''));
$title = trim((string)($card['title'] ?? ''));
$company = trim((string)($card['company'] ?? ''));
$address = trim((string)($card['address'] ?? ''));
$subtitle = trim($title . ($title !== '' && $company !== '' ? ' · ' : '') . $company);

$actions = [];
if (!empty($card['location_url'])) {
    $actions[] = ['icon' => '📍', 'label' => 'Location', 'url' => (string)$card['location_url']];
}
if (!empty($card['phones'][0])) {
    $actions[] = ['icon' => '📞', 'label' => 'Clinic Line', 'url' => 'tel:' . (string)$card['phones'][0]];
}
if (!empty($card['whatsapp'])) {
    $actions[] = ['icon' => '💬', 'label' => 'Whatsapp', 'url' => 'https://wa.me/' . preg_replace('/\D+/', '', (string)$card['whatsapp'])];
}
if (!empty($card['emails'][0])) {
    $actions[] = ['icon' => '✉️', 'label' => 'Email', 'url' => 'mailto:' . (string)$card['emails'][0]];
}
foreach (($card['links'] ?? []) as $link) {
    if (!empty($link['url'])) {
        $actions[] = ['icon' => '🌐', 'label' => (string)($link['label'] ?: 'Website'), 'url' => (string)$link['url']];
    }
}
foreach (($card['socials'] ?? []) as $platform => $url) {
    if (!empty($url)) {
        $actions[] = ['icon' => '🔗', 'label' => ucfirst((string)$platform), 'url' => (string)$url];
    }
}
$actions[] = ['icon' => '👤', 'label' => 'Full vCard', 'url' => 'card.php?id=' . urlencode($id) . '&download=vcf'];
$actions = array_slice($actions, 0, 12);

$initial = strtoupper(substr($name !== '' ? $name : 'C', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($name); ?> - Virtual Card</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="preview-page sample-layout-wrap">
    <a class="back-link" href="index.php">← Back to editor</a>
    <article class="sample-card">
        <button class="share-circle" id="open-share" type="button" aria-label="Share card">⋯</button>

        <?php if (!empty($card['image']) && file_exists(__DIR__ . '/' . $card['image'])): ?>
            <div class="avatar"><img src="<?php echo e((string)$card['image']); ?>" alt="Profile image"></div>
        <?php else: ?>
            <div class="avatar placeholder"><?php echo e($initial); ?></div>
        <?php endif; ?>

        <h1><?php echo e($name); ?></h1>
        <?php if ($subtitle !== ''): ?><p class="subtext"><?php echo e($subtitle); ?></p><?php endif; ?>
        <?php if ($address !== ''): ?><p class="address"><?php echo e($address); ?></p><?php endif; ?>

        <a class="save-contact" href="card.php?id=<?php echo urlencode($id); ?>&download=vcf">Save Contact</a>

        <?php if (count($actions) > 0): ?>
            <div class="action-grid">
                <?php foreach ($actions as $action): ?>
                    <?php $external = isExternalLink((string)$action['url']); ?>
                    <a class="action-item" href="<?php echo e((string)$action['url']); ?>"<?php echo $external ? ' target="_blank" rel="noopener"' : ''; ?>>
                        <span class="icon-box"><?php echo e((string)$action['icon']); ?></span>
                        <span><?php echo e((string)$action['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</div>

<div class="modal" id="share-modal" hidden>
    <div class="modal-inner">
        <button class="close" id="close-share" type="button">✕</button>
        <h2>Share this card</h2>
        <img src="<?php echo e($qrUrl); ?>" alt="QR code">
        <input type="text" id="share-url" readonly value="<?php echo e($shareUrl); ?>">
        <button type="button" id="copy-link">Copy Link</button>
    </div>
</div>

<script>
const shareModal = document.getElementById('share-modal');
const openBtn = document.getElementById('open-share');
const closeBtn = document.getElementById('close-share');
const copyBtn = document.getElementById('copy-link');
const shareUrlInput = document.getElementById('share-url');

openBtn.addEventListener('click', () => {
    shareModal.hidden = false;
});
closeBtn.addEventListener('click', () => {
    shareModal.hidden = true;
});
shareModal.addEventListener('click', (event) => {
    if (event.target === shareModal) {
        shareModal.hidden = true;
    }
});

copyBtn.addEventListener('click', async () => {
    try {
        await navigator.clipboard.writeText(shareUrlInput.value);
        copyBtn.textContent = 'Copied!';
        setTimeout(() => {
            copyBtn.textContent = 'Copy Link';
        }, 1500);
    } catch (err) {
        shareUrlInput.select();
        document.execCommand('copy');
    }
});
</script>
</body>
</html>
