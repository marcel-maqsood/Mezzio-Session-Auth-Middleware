<?php

declare(strict_types=1);

namespace MazeDEV\SessionAuth\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Mezzio\Authentication\UserInterface;
use Mezzio\Helper\UrlHelper;
use Laminas\Diactoros\Response\RedirectResponse;

class LogoutHandler implements RequestHandlerInterface
{


    private $urlHelper;

    public function __construct($urlHelper)
    {
        $this->urlHelper = $urlHelper;
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        
        $session  = $request->getAttribute('session');
        $session->unset(UserInterface::class);

        return new RedirectResponse($this->urlHelper->generate('home'));
    }
}
