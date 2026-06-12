<?php
/**
 * @var $this \CodeIgniter\View\View
 */
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? "Jengo Application" ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <?= view('layouts/partials/header.layout.partial.php') ?>
    <?= $this->renderSection('header') ?>
</head>
<body class="h-full font-sans text-gray-900 antialiased">
    <?= $this->renderSection('content') ?>

    <?= view('layouts/partials/footer.layout.partial.php') ?>
    <?= $this->renderSection('footer') ?>
</body>
</html>
