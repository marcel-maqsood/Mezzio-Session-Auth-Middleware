<?php

declare(strict_types=1);

namespace MazeDEV\SessionAuth\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Mezzio\Authentication\UserInterface;
use Mezzio\Helper\UrlHelper;
use Laminas\Diactoros\Response\RedirectResponse;
use MazeDEV\SessionAuth\SessionAuthMiddleware;

class ForgotPasswordHandler implements RequestHandlerInterface
{
    public function __construct()
    {
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {        
        return new JsonResponse(['status' => false, 'targat' => SessionAuthMiddleware::$tableOverride], 400);
    }
}
