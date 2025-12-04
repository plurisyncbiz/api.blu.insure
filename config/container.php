<?php

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\WebProcessor;
use Monolog\Logger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Container\ContainerInterface;
use Slim\Csrf\Guard;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Middleware\ErrorMiddleware;
use Tuupola\Middleware\HttpBasicAuthentication;
use App\Security\JwtAuth;
use App\Filesystem\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpException;
use App\Infrastructure\Adapters\PhpseclibSftpAdapter;
use App\Domain\Interfaces\SftpInterface;
use App\Infrastructure\Adapters\MtnSmsAdapter;
use App\Domain\Services\SmsService;


return [
    'settings' => function () {
        return require __DIR__ . '/settings.php';
    },

    App::class => function (ContainerInterface $container) {
        $app = AppFactory::createFromContainer($container);
// Register routes
        (require __DIR__ . '/routes.php')($app);
// Register middleware
        (require __DIR__ . '/middleware.php')($app);

        return $app;
    },
    //Error Middleware
    ErrorMiddleware::class => function (ContainerInterface $container) {
        $app = $container->get(App::class);
        $settings = $container->get('settings');
        $logger = new Logger('app');
        $filename = sprintf('%s/error.log', $settings['logger']['path']);
        $level = $settings['logger']['level'];
        $fileHandler = new RotatingFileHandler($filename, 0, $level, true, 0777);
        $fileHandler->setFormatter(new LineFormatter(null, null, false, true));
        $logger->pushHandler($fileHandler);

        $errorMiddleware = new ErrorMiddleware(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            (bool)$settings['error']['display_error_details'],
            (bool)$settings['error']['log_errors'],
            (bool)$settings['error']['log_error_details'],
            $logger
        );

        $errorMiddleware->setErrorHandler(
            HttpNotFoundException::class,
            function (ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails) {
                $response = new Response();
                $description = json_encode(array(
                    'StatusCode' => 405,
                    'data' => array(
                        'error' => '405 Not Allowed'
                    )
                ));
                $response->getBody()->write($description);
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            });

        $errorMiddleware->setErrorHandler(
            HttpException::class,
            function (ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails) {
                $response = new Response();
                $description = json_encode(array(
                    'StatusCode' => 500,
                    'data' => array(
                        'error' => '500 Server Error'
                    )
                ));
                $response->getBody()->write($description);
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            });
        $errorMiddleware->setErrorHandler(
            \PDOException::class,
            function (ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails) {
                $response = new Response();
                $description = json_encode(array(
                    'StatusCode' => 500,
                    'data' => array(
                        'error' => '500 Server Error'
                    )
                ));
                $response->getBody()->write($description);
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            });

        $errorMiddleware->setErrorHandler(
            HttpMethodNotAllowedException::class,
            function (ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails) {
                $response = new Response();
                $description = json_encode(array(
                    'StatusCode' => 405,
                    'data' => array(
                        'error' => '405 Not Allowed'
                    )
                ));
                $response->getBody()->write($description);
                return $response->withStatus(405)->withHeader('Content-Type', 'application/json');
            });
        $errorMiddleware->setErrorHandler(
            \Slim\Exception\HttpBadRequestException::class,
            function (ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails) {
                $response = new Response();
                $description = json_encode(array(
                    'StatusCode' => 400,
                    'data' => array(
                        'error' => '400 Bad Request'
                    )
                ));
                $response->getBody()->write($description);
                return $response->withStatus(405)->withHeader('Content-Type', 'application/json');
            });

        return $errorMiddleware;
    },
    // Database connections
    PDO::class => function (ContainerInterface $c) {
        $settings = $c->get('settings')['db'];
        $dsn = "{$settings['driver']}:host={$settings['host']};dbname={$settings['dbname']};charset={$settings['charset']}";
        return new PDO($dsn, $settings['user'], $settings['password'], $settings['driverOptions']);
    },
    //Logging
    Logger::class => function (ContainerInterface $container) {
        $settings = $container->get('settings')['logger'];
        $logger = new Logger('app');

        $filename = sprintf('%s/app.log', $settings['path']);
        $level = $settings['level'];
        //rotate filename
        $rotatingFileHandler = new RotatingFileHandler($filename, 0, $level, true, 0777);
        $rotatingFileHandler->setFormatter(new LineFormatter(null, null, false, true));
        $logger->pushHandler($rotatingFileHandler);
        //add webprocessor
        $webProcessor = new WebProcessor();
        $logger->pushProcessor($webProcessor);

        return $logger;
    },
    //SFTP Connector
    SftpInterface::class => function (ContainerInterface $container) {
        $settings = $container->get('settings')['sftp'];


        return new PhpseclibSftpAdapter(
            $settings['host'],
            $settings['username'],
            $settings['password'],
            (int)$settings['port']
        );
    },
    MtnSmsAdapter::class => function (ContainerInterface $container) {
        $user = $container->get('settings')['smsgateway']['username'];
        $pass = $container->get('settings')['smsgateway']['password'];
        return new MtnSmsAdapter($user, $pass);
    },
    SmsService::class => function (ContainerInterface $container) {
        return new SmsService(
            $container->get(PDO::class),
            $container->get(MtnSmsAdapter::class)
        );
    },
    MailerInterface::class => function (ContainerInterface $container) {
        $dsn = $container->get('settings')['smtp']['dsn'];
        return new Mailer(Transport::fromDsn($dsn));
    },
    Guard::class => function (ContainerInterface $container) {
        return new Guard($container->get(ResponseFactoryInterface::class));
    },
    ResponseFactoryInterface::class => function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },
    HttpBasicAuthentication::class => function (ContainerInterface $container) {
        return new HttpBasicAuthentication($container->get('settings')['api_auth']);
    },
    JwtAuth::class => function (ContainerInterface $container) {
        return new JwtAuth($container->get('settings')['jwt']);
    },
    Storage::class => function (ContainerInterface $container) {
// Read storage adapter settings
        $settings = $container->get('settings')['storage'];
        $adapter = $settings['adapter'];
        $config = $settings['config'];
// Create filesystem with
        $filesystem = new Filesystem($container->get($adapter)($config));
        return new Storage($filesystem);
    },
    LocalFilesystemAdapter::class => function () {
        return function (array $config) {
            return new LocalFilesystemAdapter(
                $config['root'] ?? '',
                PortableVisibilityConverter::fromArray(
                    $config['permissions'] ?? [],
                    $config['visibility'] ?? Visibility::PUBLIC
                ),
                $config['lock'] ?? LOCK_EX,
                $config['link'] ?? LocalFilesystemAdapter::DISALLOW_LINKS
            );
        };
    },
];