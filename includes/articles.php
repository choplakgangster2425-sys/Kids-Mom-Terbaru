<?php

declare(strict_types=1);

function article_categories(): array
{
    return ['AI', 'ASI', 'Gizi', 'Kehamilan', 'MPASI', 'Stunting', 'Tumbuh Kembang'];
}

function article_slug(string $title): string
{
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
    $slug = strtolower($transliterated === false ? $title : $transliterated);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim(substr($slug, 0, 180), '-');

    if ($slug === '') {
        $slug = 'artikel';
    }

    return $slug . '-' . bin2hex(random_bytes(5));
}

function upload_article_image(array $file): string
{
    if (!isset($file['error'], $file['tmp_name'], $file['size']) || !is_int($file['error'])) {
        throw new InvalidArgumentException('Berkas gambar tidak valid.');
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new InvalidArgumentException('Pilih gambar terlebih dahulu.');
    }

    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Gambar gagal diunggah. Silakan coba kembali.');
    }

    if ($file['size'] < 1 || $file['size'] > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Ukuran gambar maksimal 5 MB.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($file['tmp_name']);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!is_string($mimeType) || !isset($extensions[$mimeType])) {
        throw new InvalidArgumentException('Format gambar harus JPG, PNG, atau WebP.');
    }

    $imageInfo = getimagesize($file['tmp_name']);
    if (
        $imageInfo === false
        || $imageInfo[0] < 1
        || $imageInfo[1] < 1
        || $imageInfo[0] > 10000
        || $imageInfo[1] > 10000
        || ($imageInfo[0] * $imageInfo[1]) > 40000000
    ) {
        throw new InvalidArgumentException('Isi atau dimensi gambar tidak valid.');
    }

    $directory = __DIR__ . '/../assets/img';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder gambar artikel tidak dapat dibuat.');
    }

    $filename = 'article-' . bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('Gambar artikel tidak dapat disimpan.');
    }

    return $filename;
}

function delete_article_image(?string $filename): void
{
    if ($filename === null || $filename === '') {
        return;
    }

    if (!preg_match('/^article-[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)) {
        throw new RuntimeException('Nama berkas gambar artikel tidak valid.');
    }

    $path = __DIR__ . '/../assets/img/' . $filename;
    if (is_file($path) && !unlink($path)) {
        throw new RuntimeException('Gambar artikel lama tidak dapat dihapus.');
    }
}
