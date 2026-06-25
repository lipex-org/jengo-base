<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Shield\Authentication\Actions\ActionInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;

class ActionController extends BaseController
{
    protected ?ActionInterface $action = null;

    /**
     * Perform an initial check if we have a valid action or not.
     *
     * @param list<string> $params
     *
     * @return Response|string
     */
    public function _remap(string $method, ...$params)
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        // Grab our action instance if one has been set.
        $this->action = $authenticator->getAction();

        if (!$this->action instanceof ActionInterface) {
            throw new PageNotFoundException();
        }

        return $this->{$method}(...$params);
    }

    /**
     * Shows the initial screen to the user to start the flow.
     *
     * @return Response|string
     */
    public function show()
    {
        // NOTE: Shield's ActionInterface returns a string or Response.
        // If it returns a Shield view, we might need to intercept it 
        // if we want full Inertia support for 2FA/Email Activation screens.
        // For now, let's see if we can render the action's show() output.
        // If show() returns a view name, we'd need to map it to an Inertia page.

        return $this->action->show();
    }

    /**
     * Processes the form that was displayed in the previous form.
     *
     * @return Response|string
     */
    public function handle()
    {
        return $this->action->handle($this->request);
    }

    /**
     * This handles the response after the user takes action
     * in response to the show/handle flow. This might be
     * from clicking the 'confirm my email' action or
     * following entering a code sent in an SMS.
     *
     * @return Response|string
     */
    public function verify()
    {
        if ($this->request->getUserAgent()->isRobot()) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->action->verify($this->request);
    }
}
