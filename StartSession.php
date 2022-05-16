<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Log;

use Closure;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

use Illuminate\Session\Middleware\StartSession as OriginalStartSession;

class StartSession extends OriginalStartSession
{
    /**
     * Handle the given request within session state.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Contracts\Session\Session  $session
     * @param  \Closure  $next
     * @return mixed
     */
    protected function handleStatefulRequest(Request $request, $session, Closure $next)
    {
        // If a session driver has been configured, we will need to start the session here
        // so that the data is ready for an application. Note that the Laravel sessions
        // do not make use of PHP "native" sessions in any way since they are crappy.
        $request->setLaravelSession(
            $this->startSession($request, $session)
        );

        $this->collectGarbage($session);

        $response = $next($request);

        $this->storeCurrentUrl($request, $session);

        // Extra line:
        if ($request->cookies->get($session->getName())) {
            $this->addCookieToResponse($response, $session);
        }
        else {
            $this->addIdentifierToResponse($response, $session);
        }

        // Again, if the session has been configured we will need to close out the session
        // so that the attributes may be persisted to some storage medium. We will also
        // add the session identifier cookie to the application response headers now.
        $this->saveSession($request);

        return $response;
    }


    /**
     * Get the session implementation from the manager.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Session\Session
     */
    public function getSession(Request $request)
    {
        return tap($this->manager->driver(), function ($session) use ($request) {
            if ($request->cookies->get($session->getName())) {
                Log::debug('1. Set session ID from cookie');
                $session->setId($request->cookies->get($session->getName()));
            }
            else if ($request->headers->get("X-Session-Token", $request->input("sess_id"))) {
                Log::debug('2. Set session ID from header');
                $sessionToken = $request->headers->get("X-Session-Token", $request->input("sess_id"));
                $session->setId($sessionToken);
                // Log::debug('$session->getId() (X-Session-Token) ' . $session->getId());
            }
        });

        // ******************************************************************************
        // Original code
        // return tap($this->manager->driver(), function ($session) use ($request) {
        //     $session->setId($request->cookies->get($session->getName()));
        // });
        // ******************************************************************************
    }

   /**
     * Add the session cookie to the application response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  \Illuminate\Contracts\Session\Session  $session
     * @return void
     */
    protected function addIdentifierToResponse(Response $response, Session $session)
    {
        if ($this->sessionIsPersistent($config = $this->manager->getSessionConfig())) {
            $response->headers->set("X-Session-Token", $session->getId());
        }
    }
}
