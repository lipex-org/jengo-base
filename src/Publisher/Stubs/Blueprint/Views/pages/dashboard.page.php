<?php
/**
 * @var $this \CodeIgniter\View\View
 */

$header = '<h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>';
?>
<?= $this->extend('layouts/app.layout.php') ?>
<?= $this->setData(['header' => $header])->setVar('header', $header) ?>

<?= $this->section('main') ?>
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                You're logged in!
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
