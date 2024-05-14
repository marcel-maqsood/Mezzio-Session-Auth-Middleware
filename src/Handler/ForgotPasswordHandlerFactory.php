<?php

declare(strict_types=1);

namespace MazeDEV\SessionAuth\Handler;

use Mezzio\Helper\UrlHelper;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use MazeDEV\DatabaseConnector\PersistentPDO;
use MazeDEV\FormularHandlerMiddleware\Adapter\SmtpMail;

class ForgotPasswordHandlerFactory
{
	public function __invoke(ContainerInterface $container) : ForgotPasswordHandler
	{
		$config = $container->get('config');
		$authConfig = $config['authentication'] ?? null;

		if ($authConfig === null)
		{
			throw new Exception\InvalidConfigException(
				"'resetPassword'-Config is missing in Config, please check our docs: " . $config['authdocs'] . '#user-content-tables'
			);
		}

		$tableConfig = $config['tables'] ?? null;
		if ($tableConfig === null)
		{
			throw new Exception\InvalidConfigException(
				"'tables'-Config is missing in Config, please check our docs: " . $config['authdocs'] . '#user-content-tables'
			);
		}

		$requestPassworddapter = $config['requestPasswordAdapter'] ?? null;

		if($requestPassworddapter == null)
		{
			throw new Exception\InvalidConfigException(
				"'requestPasswordAdapter'-Config is missing, please check our docs: " . $config['authdocs'] . '#user-content-resetPassword'
			);
		}

		$submitPasswordAdapter = $config['submitPasswordAdapter'] ?? null;

		if($submitPasswordAdapter == null)
		{
			throw new Exception\InvalidConfigException(
				"'submitPasswordAdapter'-Config is missing, please check our docs: " . $config['authdocs'] . '#user-content-resetPassword'
			);
		}

		return new ForgotPasswordHandler(
			$container->get(TemplateRendererInterface::class),
			$container->get(PersistentPDO::class),
			$tableConfig,
			$authConfig,
			$requestPassworddapter,
			$submitPasswordAdapter,
			$container->get(UrlHelper::class),

		);
	}
}
