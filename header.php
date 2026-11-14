<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Klinik Sekolah') ?> - Klinik Kesehatan Sekolah</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">Klinik Kesehatan Sekolah</a>
    <nav>
        <a href="index.php">Beranda</a>
        <a href="medicines.php">Medicines</a>
        <a href="patients.php">Patients</a>
    </nav>
</header>
<main class="container">
<?php flash_show(); ?>