<?php

declare(strict_types=1);

$dataFile = __DIR__ . '/data/cards.json';

function readCards(string $dataFile): array
{
    if (!file_exists($dataFile)) {
        return [];
    }

    $content = file_get_contents($dataFile);
    if ($content === false || $content === '') {
        return [];
    }

    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

function saveCards(string $dataFile, array $cards): bool
{
    $dir = dirname($dataFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return file_put_contents($dataFile, json_encode($cards, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
}

function cleanText(?string $value): string
{
    return trim((string)$value);
}

function cleanArray(array $values, int $max): array
{
    $clean = [];
    foreach ($values as $value) {
        $value = trim((string)$value);
        if ($value !== '') {
            $clean[] = $value;
        }
        if (count($clean) >= $max) {
            break;
        }
    }
    return $clean;
}

function normalizeUrl(string $url): string
{
    if ($url === '') {
        return '';
    }
    return preg_match('#^https?://#i', $url) ? $url : 'https://' . $url;
}

function cleanLinks(array $labels, array $urls): array
{
    $links = [];
    $count = min(count($labels), count($urls));
    for ($i = 0; $i < $count; $i++) {
        $label = trim((string)$labels[$i]);
        $url = trim((string)$urls[$i]);
        if ($url === '') {
            continue;
        }
        $url = normalizeUrl($url);
        $links[] = [
            'label' => $label !== '' ? $label : (parse_url($url, PHP_URL_HOST) ?: 'Website'),
            'url' => $url,
        ];
    }
    return $links;
}

$error = '';
$cards = readCards($dataFile);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = cleanText($_POST['name'] ?? '');
    $title = cleanText($_POST['title'] ?? '');
    $company = cleanText($_POST['company'] ?? '');
    $address = cleanText($_POST['address'] ?? '');
    $locationUrl = normalizeUrl(cleanText($_POST['location_url'] ?? ''));
    $whatsapp = preg_replace('/\s+/', '', cleanText($_POST['whatsapp'] ?? ''));
    $phones = cleanArray($_POST['phones'] ?? [], 3);
    $emails = cleanArray($_POST['emails'] ?? [], 2);

    $socials = [
        'facebook' => normalizeUrl(cleanText($_POST['facebook'] ?? '')),
        'linkedin' => normalizeUrl(cleanText($_POST['linkedin'] ?? '')),
        'youtube' => normalizeUrl(cleanText($_POST['youtube'] ?? '')),
        'instagram' => normalizeUrl(cleanText($_POST['instagram'] ?? '')),
        'tiktok' => normalizeUrl(cleanText($_POST['tiktok'] ?? '')),
    ];

    $links = cleanLinks($_POST['link_labels'] ?? [], $_POST['link_urls'] ?? []);

    if ($name === '') {
        $error = 'Name is required.';
    }

    $imagePath = '';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['logo']['tmp_name'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmp);
            finfo_close($finfo);

            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];

            if (isset($allowed[$mime])) {
                $uploadDir = __DIR__ . '/data/uploads';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }
                $filename = uniqid('logo_', true) . '.' . $allowed[$mime];
                if (move_uploaded_file($tmp, $uploadDir . '/' . $filename)) {
                    $imagePath = 'data/uploads/' . $filename;
                }
            } else {
                $error = 'Only image files are allowed (jpg, png, gif, webp).';
            }
        } else {
            $error = 'Image upload failed.';
        }
    }

    if ($error === '') {
        $id = bin2hex(random_bytes(6));
        $cards[$id] = [
            'id' => $id,
            'name' => $name,
            'title' => $title,
            'company' => $company,
            'address' => $address,
            'location_url' => $locationUrl,
            'phones' => $phones,
            'whatsapp' => $whatsapp,
            'emails' => $emails,
            'links' => $links,
            'socials' => $socials,
            'image' => $imagePath,
            'created_at' => date('c'),
        ];

        if (saveCards($dataFile, $cards)) {
            header('Location: card.php?id=' . urlencode($id));
            exit;
        }

        $error = 'Unable to save card data. Please check file permissions.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>vCard Studio</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="page">
    <header>
        <h1>vCard Studio</h1>
        <p>Create and manage multiple virtual business cards.</p>
    </header>

    <?php if ($error !== ''): ?>
        <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <section class="panel">
        <h2>Create New Card</h2>
        <form method="POST" enctype="multipart/form-data" id="card-form">
            <div class="grid">
                <label>Name *
                    <input type="text" name="name" required>
                </label>
                <label>Job Title
                    <input type="text" name="title">
                </label>
                <label>Company
                    <input type="text" name="company">
                </label>
                <label>Address
                    <input type="text" name="address">
                </label>
                <label>Location Link (Google Maps etc.)
                    <input type="text" name="location_url" placeholder="https://maps.google.com/...">
                </label>
                <label>Logo / Image
                    <input type="file" name="logo" accept="image/*">
                </label>
                <label>Whatsapp Number
                    <input type="text" name="whatsapp" placeholder="e.g. 60123456789">
                </label>
            </div>

            <div class="group">
                <div class="group-header">
                    <h3>Phone Numbers (max 3)</h3>
                    <button type="button" class="small" data-add="phones" data-max="3">+ Add</button>
                </div>
                <div id="phones-wrapper" class="stack">
                    <input type="text" name="phones[]" placeholder="Phone number">
                </div>
            </div>

            <div class="group">
                <div class="group-header">
                    <h3>Emails (max 2)</h3>
                    <button type="button" class="small" data-add="emails" data-max="2">+ Add</button>
                </div>
                <div id="emails-wrapper" class="stack">
                    <input type="email" name="emails[]" placeholder="email@example.com">
                </div>
            </div>

            <div class="group">
                <div class="group-header">
                    <h3>Website Links</h3>
                    <button type="button" class="small" data-add-link="1">+ Add</button>
                </div>
                <div id="links-wrapper" class="stack">
                    <div class="link-row">
                        <input type="text" name="link_labels[]" placeholder="Link label (optional)">
                        <input type="text" name="link_urls[]" placeholder="https://example.com">
                    </div>
                </div>
            </div>

            <div class="group">
                <h3>Social Media (all optional)</h3>
                <div class="grid">
                    <label>Facebook <input type="url" name="facebook" placeholder="https://facebook.com/..." /></label>
                    <label>LinkedIn <input type="url" name="linkedin" placeholder="https://linkedin.com/in/..." /></label>
                    <label>Youtube <input type="url" name="youtube" placeholder="https://youtube.com/@..." /></label>
                    <label>Instagram <input type="url" name="instagram" placeholder="https://instagram.com/..." /></label>
                    <label>Tiktok <input type="url" name="tiktok" placeholder="https://tiktok.com/@..." /></label>
                </div>
            </div>

            <button type="submit" class="primary">Generate Card</button>
        </form>
    </section>

    <section class="panel">
        <h2>Existing Cards</h2>
        <?php if (count($cards) === 0): ?>
            <p>No cards created yet.</p>
        <?php else: ?>
            <ul class="card-list">
                <?php foreach (array_reverse($cards) as $card): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($card['name']); ?></strong>
                        <span><?php echo htmlspecialchars($card['company'] ?? ''); ?></span>
                        <a href="card.php?id=<?php echo urlencode((string)$card['id']); ?>">Open</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<script src="assets/app.js"></script>
</body>
</html>
