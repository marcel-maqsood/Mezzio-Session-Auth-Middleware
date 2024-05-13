<?php

declare(strict_types=1);

namespace MazeDEV\SessionAuth\Handler;

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

		$smtpMailAdapter = $config['resetPasswordMailAdapter'] ?? null;

		if($smtpMailAdapter == null)
		{
			throw new Exception\InvalidConfigException(
				"'resetPasswordMailAdapter'-Config is missing, please check our docs: " . $config['authdocs'] . '#user-content-resetPassword'
			);
		}

		return new ForgotPasswordHandler(
			$container->get(TemplateRendererInterface::class),
			$container->get(PersistentPDO::class),
			$tableConfig,
			$authConfig,
			$smtpMailAdapter
		);
	}
}
