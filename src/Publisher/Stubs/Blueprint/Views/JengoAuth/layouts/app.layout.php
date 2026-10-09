<?php
/**
 * @var $this \CodeIgniter\View\View
 */
?>
<?= $this->extend('layouts/base.layout.php') ?>

<?= $this->section('content') ?>
<div class="min-h-screen bg-gray-100">
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <!-- Logo -->
                    <div class="shrink-0 flex items-center">
                        <a href="<?= url_to('/') ?>" class="flex items-center space-x-2">
                            <img src="<?= base_url('images/logo6.png') ?>" alt="Jengo" class="h-8 w-auto">
                        </a>
                    </div>

                    <!-- Navigation Links -->
                    <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
                        <a href="<?= url_to('dashboard') ?>" class="inline-flex items-center px-1 pt-1 border-b-2 border-primary text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-primary transition duration-150 ease-in-out">
                            Dashboard
                        </a>
                    </div>
                </div>

                <!-- Settings Dropdown / Auth Links -->
                <div class="hidden sm:flex sm:items-center sm:ml-6">
                    <?php if (auth()->check()): ?>
                        <div class="flex items-center space-x-4">
                            <span class="text-sm text-gray-700"><?= esc(auth()->currentUser()->username ?? auth()->currentUser()->email ?? 'User') ?></span>
                            <form action="<?= auth_url('logout') ?>" method="post" class="inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="text-sm text-gray-500 hover:text-gray-700 cursor-pointer">Logout</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center space-x-4">
                            <a href="<?= auth_url('login') ?>" class="text-sm text-gray-700 underline">Log in</a>
                            <a href="<?= auth_url('register') ?>" class="text-sm text-gray-700 underline">Register</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Heading -->
    <?php if (isset($header)): ?>
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <?= $header ?>
            </div>
        </header>
    <?php endif; ?>

    <!-- Page Content -->
    <main>
        <?= $this->renderSection('main') ?>
    </main>
</div>
<?= $this->endSection() ?>
