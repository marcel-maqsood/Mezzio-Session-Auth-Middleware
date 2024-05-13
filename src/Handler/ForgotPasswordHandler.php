<?php

declare(strict_types=1);

namespace MazeDEV\SessionAuth\Handler;

use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Mezzio\Authentication\UserInterface;
use Mezzio\Helper\UrlHelper;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\Response\JsonResponse;
use MazeDEV\SessionAuth\SessionAuthMiddleware;
use MazeDEV\FormularHandlerMiddleware\Adapter\SmtpMail;
use MazeDEV\DatabaseConnector\PersistentPDO;

class ForgotPasswordHandler implements RequestHandlerInterface
{

	private PersistentPDO $persistentPDO;
	private array $tableConfig;
	private array $authConfig;
	private array $resetPasswordMailAdapter;
	private TemplateRendererInterface $renderer;

	public function __construct(TemplateRendererInterface $renderer, PersistentPDO $persistentPDO, array $tableConfig, $authConfig, array $resetPasswordMailAdapter)
	{
		$this->renderer = $renderer;
		$this->persistentPDO = $persistentPDO;
		$this->tableConfig = $tableConfig;
		$this->authConfig = $authConfig;
		$this->resetPasswordMailAdapter = $resetPasswordMailAdapter;
	}

	
	public function handle(ServerRequestInterface $request) : ResponseInterface
	{
		if ($request->getMethod() === 'POST')
		{
			return $this->handlePost();
		}
	}

	private function handlePost()
	{
		if (!class_exists('MazeDEV\FormularHandlerMiddleware\Adapter\SmtpMail'))
		{
			return new JsonResponse(
				[
					'success' => false,
					'error' => 'Password-Reset does not work without SmtpMail',
					'targat' => SessionAuthMiddleware::$tableOverride
				],
				500);
		}

		$user = SessionAuthMiddleware::$permissionManager::getUser();

		if(!$user)
		{
			return new JsonResponse(
				[
					'success' => false,
					'error' => 'User not found',
					'target' => SessionAuthMiddleware::$tableOverride
				],
				400
			);
		}

		$currentTime = date('Y-m-d H:i:s');
		$validUntil = date(
			'Y-m-d H:i:s',
			strtotime($currentTime) + ( $this->authConfig['passwordResetOffset'] ?? 2592000)
		);

		//'sha256'
		$hash = hash($this->authConfig['security']['algo'], $validUntil . SessionAuthMiddleware::generateRandomSalt());

		$email = $user->{$this->tableConfig[SessionAuthMiddleware::$tableOverride]['loginMail']};

		$this->resetPasswordMailAdapter['recipients'] = [$email];

		$validFields = [
			'userHash' => $hash,
			'email' => $email
		];

		$fields = [
			'fields' => [
				'userHash' => [
					'type' => 'text',
					'required' => true,
				],
				'email' => [
					'type' => 'text',
					'required' => true,
				],
			]
		];

		$driver = new SmtpMail($fields, $this->resetPasswordMailAdapter, $validFields, $this->renderer);
		return new JsonResponse(
			[
				'success' => true,
				'target' => SessionAuthMiddleware::$tableOverride
			],
			200
		);
	}
}
