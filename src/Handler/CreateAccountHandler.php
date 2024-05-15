<?php

declare(strict_types=1);

namespace MazeDEV\SessionAuth\Handler;

use Laminas\Diactoros\Response\HtmlResponse;
use MazeDEV\AbstractRequestHandler\AbstractRequestHandler;
use MazeDEV\SessionAuth\SessionAuthMiddleware;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Mezzio\Helper\UrlHelper;
use Laminas\Diactoros\Response\JsonResponse;
use MazeDEV\FormularHandlerMiddleware\Adapter\SmtpMail;
use MazeDEV\DatabaseConnector\PersistentPDO;

class CreateAccountHandler extends AbstractRequestHandler
{
	private array $authConfig;
	private array $resetPasswordMailAdapter;
	private array $submitPasswordAdapter;
	private UrlHelper $urlHelper;
	private array $messages;

	public function __construct(TemplateRendererInterface $renderer, PersistentPDO $persistentPDO, array $tableConfig, $authConfig, array $resetPasswordMailAdapter, array $submitPasswordAdapter, UrlHelper $urlHelper, array $messages)
	{
		parent::__construct($renderer, $persistentPDO, $tableConfig);
		$this->authConfig = $authConfig;
		$this->resetPasswordMailAdapter = $resetPasswordMailAdapter;
		$this->submitPasswordAdapter = $submitPasswordAdapter;
		$this->urlHelper = $urlHelper;
		$this->messages = $messages;
	}


	public function handle(ServerRequestInterface $request) : ResponseInterface
	{
		if ($request->getMethod() === 'POST')
		{
			return $this->handlePost($request, "");
		}

		//This Handler will be accessed only with POST requests so there is no default response.
	}

	protected function defaultResponse(ServerRequestInterface $request, array $postData = []): JsonResponse
	{
		return new JsonResponse(
			[
				'success' => false,
				'error' => 'unexpected_error',
			],
			400);
	}

	protected function save(array $postData): bool|array
	{

		$insertArray = parent::generateInsertArray(SessionAuthMiddleware::$tableOverride, $postData);

		//success might be a bool or id, depending on
		$success = $this->persistentPDO->insert($this->tableConfig[SessionAuthMiddleware::$tableOverride]['tableName'], $insertArray);

		if(!$success)
		{
			$this->errorMsgs[] = $this->messages['error']['user-create-error'];
			return false;
		}

		return true;

	}

	protected function update(array $entry = []): bool
	{
		// TODO: Implement update() method.
	}

	protected function delete(array $postData): JsonResponse
	{
		// TODO: Implement delete() method.
	}

	protected function generateTemplateData(array $postData = [], array $feedback = []): array
	{
		// TODO: Implement generateTemplateData() method.
	}

	protected function getLookupResult(ServerRequestInterface $request, array $postData = [], $feedBack = []): HtmlResponse
	{
		// TODO: Implement getLookupResult() method.
	}

	protected function handleExtraConfigs(ServerRequestInterface $request, array $postData): JsonResponse|HtmlResponse|bool
	{
		// TODO: Implement handleExtraConfigs() method.
	}
}
