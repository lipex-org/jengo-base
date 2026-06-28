<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Validation\ValidationRules;
use Jengo\Inertia\Inertia;

class LoginController extends BaseController
{
    /**
     * Displays the form the login to the site.
     *
     * @return ResponseInterface|string
     */
    public function loginView()
    {
        if (auth()->loggedIn()) {
            return redirect()->to(config('Auth')->loginRedirect());
        }

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        // If an action has been defined, start it up.
        if ($authenticator->hasAction()) {
            return redirect()->route('auth-action-show');
        }

        return Inertia::render('auth/login', [
            'canResetPassword' => setting('Auth.allowMagicLinkLogins'),
            'status' => session('status'),
        ]);
    }

    /**
     * Attempts to log the user in.
     */
    public function loginAction(): RedirectResponse
    {
        // Validate here first, since some things,
        // like the password, can only be validated properly here.
        $rules = $this->getValidationRules();

        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        if (!$this->validateData($data, $rules, [], config('Auth')->DBGroup)) {
            return redirect()->to('/login')->withInput()->with('errors', $this->validator->getErrors());
        }

        /** @var array $credentials */
        $credentials = array_intersect_key($data, array_fill_keys(setting('Auth.validFields'), null));
        $credentials = array_filter($credentials);
        $credentials['password'] = $data['password'] ?? null;
        $remember = (bool) ($data['remember'] ?? false);

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        // Attempt to login
        $result = $authenticator->remember($remember)->attempt($credentials);
        if (!$result->isOK()) {
            return redirect()->route('login')->withInput()->with('error', $result->reason());
        }

        // If an action has been defined for login, start it up.
        if ($authenticator->hasAction()) {
            return redirect()->route('auth-action-show')->withCookies();
        }

        return redirect()->to(config('Auth')->loginRedirect())->withCookies();
    }

    /**
     * Returns the rules that should be used for validation.
     *
     * @return array<string, array<string, list<string>|string>>
     */
    protected function getValidationRules(): array
    {
        $rules = new ValidationRules();

        return $rules->getLoginRules();
    }

    /**
     * Logs the current user out.
     */
    public function logoutAction(): ResponseInterface
    {
        // Capture logout redirect URL before auth logout,
        // otherwise you cannot check the user in `logoutRedirect()`.
        $url = config('Auth')->logoutRedirect();

        auth()->logout();

        return Inertia::location($url);
    }
}
