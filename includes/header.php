<?php
// includes/header.php

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= APP_NAME ?> - Dashboard</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/style.css"
    >

    <link
        rel="icon"
        type="image/svg+xml"
        href="<?= BASE_URL ?>assets/img/logo.svg"
    >
</head>

<body>

<div id="wrapper">

<?php
// ============================================================
// FLASH MESSAGE
// ============================================================

$flash = getFlash();

if ($flash):
    
    $alertClass = 'alert-info';
    $icon = 'fa-circle-info';

    if ($flash['type'] === 'success') {
        $alertClass = 'alert-success';
        $icon = 'fa-circle-check';
    } elseif ($flash['type'] === 'danger') {
        $alertClass = 'alert-danger';
        $icon = 'fa-circle-xmark';
    } elseif ($flash['type'] === 'warning') {
        $alertClass = 'alert-warning';
        $icon = 'fa-triangle-exclamation';
    }
?>

<div
    class="container-fluid px-4 pt-3"
    style="position: relative; z-index: 9999;"
>
    <div
        class="alert <?= $alertClass ?> alert-dismissible fade show shadow-sm"
        role="alert"
        style="border-radius: 12px;"
    >
        <i class="fa-solid <?= $icon ?> me-2"></i>

        <?= htmlspecialchars($flash['message']) ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
            aria-label="Close"
        ></button>
    </div>
</div>

<?php endif; ?>