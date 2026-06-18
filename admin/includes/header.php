<?php
if (!isset($page_title)) {
    if (isset($pageTitle)) {
        $page_title = $pageTitle;
    } else {
        $page_title = 'Dashboard';
    }
}

if (!isset($page_subtitle)) {
    $page_subtitle = 'Manage the iVotePH election management system.';
}

if (!isset($activePage)) {
    $activePage = '';
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?php echo e($page_title); ?> - iVotePH Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/ivoteph/admin/assets/img/ivoteph-logo.png">


    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link href="/ivoteph/admin/assets/css/style.css?v=adminfix20260613" rel="stylesheet">
</head>

<body>
    <div class="ivote-admin-shell">