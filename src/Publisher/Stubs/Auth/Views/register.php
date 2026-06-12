<?= $this->extend('layouts/auth.layout.php') ?>

<?= $this->section('title') ?><?= lang('Auth.register') ?> <?= $this->endSection() ?>

<?= $this->section('header') ?><?= lang('Auth.register') ?><?= $this->endSection() ?>

<?= $this->section('main') ?>

    <?php if (session('error') !== null) : ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert"><?= session('error') ?></div>
    <?php elseif (session('errors') !== null) : ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <?php if (is_array(session('errors'))) : ?>
                <?php foreach (session('errors') as $error) : ?>
                    <p><?= $error ?></p>
                <?php endforeach ?>
            <?php else : ?>
                <?= session('errors') ?>
            <?php endif ?>
        </div>
    <?php endif ?>

    <form action="<?= url_to('register') ?>" method="post">
        <?= csrf_field() ?>

        <!-- Email -->
        <div class="mb-4">
            <label for="floatingEmail" class="block text-sm font-medium text-gray-700"><?= lang('Auth.email') ?></label>
            <input type="email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="floatingEmail" name="email" inputmode="email" autocomplete="email" placeholder="<?= lang('Auth.email') ?>" value="<?= old('email') ?>" required>
        </div>

        <!-- Username -->
        <div class="mb-4">
            <label for="floatingUsername" class="block text-sm font-medium text-gray-700"><?= lang('Auth.username') ?></label>
            <input type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="floatingUsername" name="username" inputmode="text" autocomplete="username" placeholder="<?= lang('Auth.username') ?>" value="<?= old('username') ?>" required>
        </div>

        <!-- Password -->
        <div class="mb-4">
            <label for="floatingPassword" class="block text-sm font-medium text-gray-700"><?= lang('Auth.password') ?></label>
            <input type="password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="floatingPassword" name="password" inputmode="text" autocomplete="new-password" placeholder="<?= lang('Auth.password') ?>" required>
        </div>

        <!-- Password (Confirm) -->
        <div class="mb-4">
            <label for="floatingPasswordConfirm" class="block text-sm font-medium text-gray-700"><?= lang('Auth.passwordConfirm') ?></label>
            <input type="password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="floatingPasswordConfirm" name="password_confirm" inputmode="text" autocomplete="new-password" placeholder="<?= lang('Auth.passwordConfirm') ?>" required>
        </div>

        <div class="mt-6">
            <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <?= lang('Auth.register') ?>
            </button>
        </div>

        <p class="mt-4 text-center text-sm text-gray-600"><?= lang('Auth.alreadyHaveAccount') ?> <a href="<?= url_to('login') ?>" class="font-medium text-blue-600 hover:text-blue-500"><?= lang('Auth.login') ?></a></p>

    </form>

<?= $this->endSection() ?>
