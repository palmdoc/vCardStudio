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

if (!$card) {
    http_response_code(404);
    exit('Card not found.');
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$shareUrl = $scheme . '://' . $host . ($base !== '' ? $base : '') . '/card.php?id=' . urlencode($id);
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($shareUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($card['name']); ?> - Virtual Card</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="preview-page">
    <a class="back-link" href="index.php">← Back to editor</a>
    <article class="business-card">
        <button class="share-btn" id="open-share" type="button">Share</button>

        <div class="card-main">
            <?php if (!empty($card['image']) && file_exists(__DIR__ . '/' . $card['image'])): ?>
                <div class="logo-wrap">
                    <img src="<?php echo e($card['image']); ?>" alt="Logo">
                </div>
            <?php endif; ?>

            <div class="info-wrap">
                <h1><?php echo e($card['name']); ?></h1>
                <?php if (!empty($card['title'])): ?><p class="muted"><?php echo e($card['title']); ?></p><?php endif; ?>
                <?php if (!empty($card['company'])): ?><p><?php echo e($card['company']); ?></p><?php endif; ?>
                <?php if (!empty($card['address'])): ?><p><?php echo e($card['address']); ?></p><?php endif; ?>

                <?php if (!empty($card['phones'])): ?>
                    <?php foreach ($card['phones'] as $phone): ?>
                        <p><a href="tel:<?php echo e($phone); ?>">📞 <?php echo e($phone); ?></a></p>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($card['whatsapp'])): ?>
                    <p><a href="https://wa.me/<?php echo e(preg_replace('/\D+/', '', (string)$card['whatsapp'])); ?>" target="_blank" rel="noopener">💬 WhatsApp: <?php echo e($card['whatsapp']); ?></a></p>
                <?php endif; ?>

                <?php if (!empty($card['emails'])): ?>
                    <?php foreach ($card['emails'] as $email): ?>
                        <p><a href="mailto:<?php echo e($email); ?>">✉️ <?php echo e($email); ?></a></p>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($card['links'])): ?>
                    <div class="inline-links">
                        <?php foreach ($card['links'] as $link): ?>
                            <a href="<?php echo e($link['url']); ?>" target="_blank" rel="noopener"><?php echo e($link['label'] ?: 'Website'); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($card['socials'])): ?>
                    <div class="inline-links socials">
                        <?php foreach ($card['socials'] as $platform => $url): ?>
                            <?php if (!empty($url)): ?>
                                <a href="<?php echo e($url); ?>" target="_blank" rel="noopener"><?php echo e(ucfirst($platform)); ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
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

openBtn.addEventListener('click', () => shareModal.hidden = false);
closeBtn.addEventListener('click', () => shareModal.hidden = true);
shareModal.addEventListener('click', (event) => {
    if (event.target === shareModal) {
        shareModal.hidden = true;
    }
});

copyBtn.addEventListener('click', async () => {
    try {
        await navigator.clipboard.writeText(shareUrlInput.value);
        copyBtn.textContent = 'Copied!';
        setTimeout(() => { copyBtn.textContent = 'Copy Link'; }, 1500);
    } catch (err) {
        shareUrlInput.select();
        document.execCommand('copy');
    }
});
</script>
</body>
</html>
