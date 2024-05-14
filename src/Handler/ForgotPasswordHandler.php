<?php

declare(strict_types=1);

namespace MazeDEV\SessionAuth\Handler;

use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Session\Session;
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
	private array $submitPasswordAdapter;
	private UrlHelper $urlHelper;
	private TemplateRendererInterface $renderer;

	public function __construct(TemplateRendererInterface $renderer, PersistentPDO $persistentPDO, array $tableConfig, $authConfig, array $resetPasswordMailAdapter, array $submitPasswordAdapter, UrlHelper $urlHelper)
	{
		$this->renderer = $renderer;
		$this->persistentPDO = $persistentPDO;
		$this->tableConfig = $tableConfig;
		$this->authConfig = $authConfig;
		$this->resetPasswordMailAdapter = $resetPasswordMailAdapter;
		$this->submitPasswordAdapter = $submitPasswordAdapter;
		$this->urlHelper = $urlHelper;
	}

	
	public function handle(ServerRequestInterface $request) : ResponseInterface
	{
		$queryParams = $request->getQueryParams();
		if ($request->getMethod() === 'POST')
		{

			if (!class_exists('MazeDEV\FormularHandlerMiddleware\Adapter\SmtpMail'))
			{
				return new JsonResponse(
					[
						'success' => false,
						'error' => 'Password-Reset does not work without SmtpMail package',
					],
					500);
			}

			$postData = $request->getParsedBody() ?? [];
			return $this->handlePost($postData, $queryParams);
		}


		if(!empty($queryParams) && isset($queryParams['hash']))
		{
			$state = $this->isHashValid($queryParams['hash']);
			if($state !== true)
			{
				return $state;
			}
			//display success.
		}

		return new HtmlResponse($this->renderer->render(
			'app::SetPasswordForm',
			['loginAt' => SessionAuthMiddleware::$noAuthRoutes[SessionAuthMiddleware::$currentRoute]]
		));
	}

	private function handlePost($postData, $queryParams)
	{
		switch($postData['action'])
		{
			case 'request':
				return $this->handlePwRequest($postData);
				break;
			case 'submit':
				return $this->handlePwSubmit($postData, $queryParams);
				break;
		}
	}

	private function isHashValid($hash)
	{
		$user = $this->persistentPDO->get(
			"*",
			$this->tableConfig[SessionAuthMiddleware::$tableOverride]['tableName'],
			$this->tableConfig[SessionAuthMiddleware::$tableOverride]['resetHash'] . " = '" . $hash . "'",
			[],
			[],
			false
		);

		if(!$user)
		{

			\setcookie("error", 'Der verwendete Link ist nicht mehr gültig.', time() + 60, '/');
			return new RedirectResponse($this->urlHelper->generate(SessionAuthMiddleware::$noAuthRoutes[SessionAuthMiddleware::$currentRoute]));
		}

		$validUntil = $user->{$this->tableConfig[SessionAuthMiddleware::$tableOverride]['resetValid']};
		$validUntil = new \DateTime($user->{$this->tableConfig[SessionAuthMiddleware::$tableOverride]['resetValid']});
		$currentDateTime = new \DateTime();

		if ($validUntil < $currentDateTime)
		{

			\setcookie("error", 'Der verwendete Link ist nicht mehr gültig.', time() + 60, '/');
			return new RedirectResponse($this->urlHelper->generate(SessionAuthMiddleware::$noAuthRoutes[SessionAuthMiddleware::$currentRoute]));
		}

		return true;
	}

	private function handlePwRequest($postData)
	{
		if(!isset($postData['username']) || $postData['username'] == '')
		{
			return new JsonResponse(
				[
					'success' => false,
					'error' => 'No username given',
				],
				400);
		}

		$user = $this->persistentPDO->get(
			"*",
			$this->tableConfig[SessionAuthMiddleware::$tableOverride]['tableName'],
			$this->tableConfig[SessionAuthMiddleware::$tableOverride]['loginName'] . " = '" . $postData['username'] . "' OR "
			. $this->tableConfig[SessionAuthMiddleware::$tableOverride]['loginMail'] . " = '" . $postData['username'] ."'"
		);

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



		$updated = $this->persistentPDO->update(
			$this->tableConfig[SessionAuthMiddleware::$tableOverride]['tableName'],
			[
				$this->tableConfig[SessionAuthMiddleware::$tableOverride]['resetValid'] => $validUntil,
				$this->tableConfig[SessionAuthMiddleware::$tableOverride]['resetHash'] => $hash,
			],
			$this->tableConfig[SessionAuthMiddleware::$tableOverride]['identifier']
			. " = '" . $user->{$this->tableConfig[SessionAuthMiddleware::$tableOverride]['identifier']} . "'"
		);

		if(!$updated)
		{
			return new JsonResponse(
				[
					'success' => false,
					'error' => "User couldn't be updated.",
					'target' => SessionAuthMiddleware::$tableOverride
				],
				400
			);
		}

		$email = $user->{$this->tableConfig[SessionAuthMiddleware::$tableOverride]['loginMail']};

		$this->resetPasswordMailAdapter['recipients'] = [$email];

		$validFields = [
			'targetReset' => $postData['targetReset'],
			'name' => $user->{$this->tableConfig[SessionAuthMiddleware::$tableOverride]['loginName']},
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
			],
			200
		);
	}

	private function handlePwSubmit($postData, $queryParams)
	{
		if(!isset($postData['password']) || $postData['password'] == '')
		{
			return new JsonResponse(
				[
					'success' => false,
					'error' => 'No password given',
				],
				400);
		}

		$user = $this->persistentPDO->get(
			"*",
			$this->tableConfig[SessionAuthMiddleware::$tableOverride]['tableName'],
			$this->tableConfig[SessionAuthMiddleware::$tableOverride]['resetHash'] . " = '" . $queryParams['hash'] . "'"
		);

		if(!$user)
		{
			\setcookie("error", 'Der verwendete Link ist nicht mehr gültig.', time() + 60, '/');
			return new JsonResponse(
				[
					'success' => false,
					'error' => 'User not found',
				],
				400
			);
		}

		$validUntil = $user->{$this->tableConfig[SessionAuthMiddleware::$tableOverride]['resetValid']};
		$validUntil = new \DateTime($user->{$this->tableConfig[SessionAuthMiddleware::$tableOverride]['resetValid']});
		$currentDateTime = new \DateTime();

		if ($validUntil < $currentDateTime)
		{
			\setcookie("error", 'Der verwendete Link ist nicht mehr gültig.', time() + 60, '/');
			return new JsonResponse(
				[
					'success' => false,
					'error' => 'User not found',
				],
				400
			);
		}

		$password = password_hash($postData['password'] . $this->authConfig['security']['salt'], PASSWORD_BCRYPT);
		$updated = $this->persistentPDO->update(
			$this->tableConfig[SessionAuthMiddleware::$tableOverride]['tableName'],
			[
				$this->authConfig['repository']['fields']['password'] => $password,
				$this->tableConfig[SessionAuthMiddleware::$tableOverride]['resetHash'] => hash($this->authConfig['security']['algo'], SessionAuthMiddleware::generateRandomSalt()),
				$this->tableConfig[SessionAuthMiddleware::$tableOverride]['resetValid'] => \date('Y-m-d H:i:s')
			],
			$this->tableConfig[SessionAuthMiddleware::$tableOverride]['identifier']
			. " = '" . $user->{$this->tableConfig[SessionAuthMiddleware::$tableOverride]['identifier']} . "'"
		);


		if(!$updated)
		{
			\setcookie("error", 'Passwort konnte nicht gespeichert werden.', time() + 60, '/');
			return new JsonResponse(
				[
					'success' => false,
					'error' => 'Password not saved.',
				],
				400
			);
		}


		$email = $user->{$this->tableConfig[SessionAuthMiddleware::$tableOverride]['loginMail']};

		$this->submitPasswordAdapter['recipients'] = [$email];

		$validFields = [
			'name' => $user->{$this->tableConfig[SessionAuthMiddleware::$tableOverride]['loginName']},
			'userHash' => $queryParams['hash'],
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


		$driver = new SmtpMail($fields, $this->submitPasswordAdapter, $validFields, $this->renderer);
		return new JsonResponse(
			[
				'success' => true,
			],
			200
		);

	}
}
