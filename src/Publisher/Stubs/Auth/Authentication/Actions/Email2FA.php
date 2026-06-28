<?php

declare(strict_types=1);

namespace App\Authentication\Actions;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\Actions\ActionInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Entities\UserIdentity;
use CodeIgniter\Shield\Exceptions\RuntimeException;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Jengo\Inertia\Inertia;

/**
 * Class Email2FA
 *
 * Sends an email to the user with a code to verify their account.
 */
class Email2FA implements ActionInterface
{
    private string $type = Session::ID_TYPE_EMAIL_2FA;

    /**
     * Displays the "Hey we're going to send you a number to your email"
     * message to the user with a prompt to continue.
     */
    public function show()
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $user = $authenticator->getPendingUser();
        if ($user === null) {
            throw new RuntimeException('Cannot get the pending login User.');
        }

        $this->createIdentity($user);

        return Inertia::render('auth/email-2fa-show', ['user' => $user]);
    }

    /**
     * Generates the random number, saves it as a temp identity
     * with the user, and fires off an email to the user with the code,
     * then displays the form to accept the 6 digits
     *
     * @return ResponseInterface|string
     */
    public function handle(IncomingRequest $request)
    {
        $data = $request->getJSON(true) ?? $request->getPost();
        $email = $data['email'] ?? null;

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $user = $authenticator->getPendingUser();
        if ($user === null) {
            throw new RuntimeException('Cannot get the pending login User.');
        }

        if (empty($email) || $email !== $user->email) {
            return redirect()->route('auth-action-show')->with('error', lang('Auth.invalidEmail', [$email]));
        }

        $identity = $this->getIdentity($user);

        if (!$identity instanceof UserIdentity) {
            return redirect()->route('auth-action-show')->with('error', lang('Auth.need2FA'));
        }

        $ipAddress = $request->getIPAddress();
        $userAgent = (string) $request->getUserAgent();
        $date = Time::now()->toDateTimeString();

        // Send the user an email with the code
        helper('email');
        $emailObj = emailer(['mailType' => 'html'])
            ->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '');
        $emailObj->setTo($user->email);
        $emailObj->setSubject(lang('Auth.email2FASubject'));
        $emailObj->setMessage(view(
            setting('Auth.views')['action_email_2fa_email'],
            ['code' => $identity->secret, 'user' => $user, 'ipAddress' => $ipAddress, 'userAgent' => $userAgent, 'date' => $date],
            ['debug' => false],
        ));

        if ($emailObj->send(false) === false) {
            throw new RuntimeException('Cannot send email for user: ' . $user->email . "\n" . $emailObj->printDebugger(['headers']));
        }

        // Clear the email
        $emailObj->clear();

        return Inertia::render('auth/email-2fa-verify');
    }

    /**
     * Attempts to verify the code the user entered.
     *
     * @return ResponseInterface|string
     */
    public function verify(IncomingRequest $request)
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $data = $request->getJSON(true) ?? $request->getPost();
        $postedToken = $data['token'] ?? null;

        $user = $authenticator->getPendingUser();
        if ($user === null) {
            throw new RuntimeException('Cannot get the pending login User.');
        }

        $identity = $this->getIdentity($user);

        // Token mismatch? Let them try again...
        if (!$authenticator->checkAction($identity, $postedToken)) {
            session()->setFlashdata('error', lang('Auth.invalid2FAToken'));

            return Inertia::render('auth/email-2fa-verify');
        }

        // Get our login redirect url
        return redirect()->to(config('Auth')->loginRedirect());
    }

    /**
     * Creates an identity for the action of the user.
     *
     * @return string secret
     */
    public function createIdentity(User $user): string
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        // Delete any previous identities for action
        $identityModel->deleteIdentitiesByType($user, $this->type);

        $generator = static fn(): string => random_string('nozero', 6);

        return $identityModel->createCodeIdentity(
            $user,
            [
                'type' => $this->type,
                'name' => 'login',
                'extra' => lang('Auth.need2FA'),
            ],
            $generator,
        );
    }

    /**
     * Returns an identity for the action of the user.
     */
    private function getIdentity(User $user): ?UserIdentity
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);

        return $identityModel->getIdentityByType(
            $user,
            $this->type,
        );
    }

    /**
     * Returns the string type of the action class.
     */
    public function getType(): string
    {
        return $this->type;
    }
}
