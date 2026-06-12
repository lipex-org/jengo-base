<?php
/**
 * @var $this \CodeIgniter\View\View
 */
?>
<?= $this->extend('layouts/base.layout.php') ?>

<?= $this->section('content') ?>
<div class="relative sm:flex sm:justify-center sm:items-center min-h-screen bg-dots-darker bg-center bg-gray-100 selection:bg-indigo-500 selection:text-white">
    <div class="sm:fixed sm:top-0 sm:right-0 p-6 text-right z-10">
        <?php if (auth()->loggedIn()): ?>
            <a href="<?= url_to('/dashboard') ?>" class="font-semibold text-gray-600 hover:text-gray-900 focus:outline-2 focus:rounded-sm focus:outline-indigo-500">Dashboard</a>
        <?php else: ?>
            <a href="<?= url_to('login') ?>" class="font-semibold text-gray-600 hover:text-gray-900 focus:outline-2 focus:rounded-sm focus:outline-indigo-500">Log in</a>
            <a href="<?= url_to('register') ?>" class="ml-4 font-semibold text-gray-600 hover:text-gray-900 focus:outline-2 focus:rounded-sm focus:outline-indigo-500">Register</a>
        <?php endif; ?>
    </div>

    <div class="max-w-7xl mx-auto p-6 lg:p-8">
        <div class="flex justify-center">
            <h1 class="text-6xl font-black text-indigo-600">JENGO</h1>
        </div>

        <div class="mt-16 text-center">
            <p class="text-2xl text-gray-700 font-medium">The CodeIgniter 4 Powerhouse</p>
            <p class="mt-4 text-gray-500 text-lg">You have successfully generated a new Jengo application.</p>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
