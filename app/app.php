<?php

use Silex\Application;
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\Debug\ExceptionHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Xi\Filelib\FileLibrary;
use Xi\Filelib\Plugin\Image\Command\ExecuteMethodCommand;
use Xi\Filelib\Plugin\Image\VersionPlugin;
use Xi\Filelib\Plugin\RandomizeNamePlugin;
use Xi\Filelib\Renderer\Adapter\SymfonyRendererAdapter;
use Xi\Filelib\Renderer\AcceleratedRenderer;
use Xi\Filelib\Authorization\AuthorizationPlugin;
use Xi\Filelib\Publisher\Adapter\Filesystem\SymlinkFilesystemPublisherAdapter;
use Xi\Filelib\Publisher\Publisher;
use Xi\Filelib\Publisher\Linker\ReversibleSequentialLinker;
use Xi\Filelib\Plugin\Video\ZencoderPlugin;
use Xi\Filelib\Profile\FileProfile;
use Xi\Filelib\Storage\Adapter\Filesystem\DirectoryIdCalculator\TimeDirectoryIdCalculator;
use Xi\Filelib\Backend\Adapter\DoctrineDbalBackendAdapter;
use Xi\Filelib\Storage\Adapter\FilesystemStorageAdapter;
use Xi\Filelib\Plugin\Image\ArbitraryVersionPlugin;
use Xi\Filelib\Version;
use Xi\Filelib\File\File;
use Xi\Filelib\Plugin\Image\Command\Command;
use Pekkis\Queue\Adapter\IronMQAdapter;
use Xi\Filelib\Storage\Adapter\Filesystem\PathCalculator\LegacyPathCalculator;

require_once __DIR__ . '/../vendor/autoload.php';

class Kernel
{
    private $env;
    private $debug;

    /**
     * @param string $env
     * @param bool   $debug
     */
    public function __construct($env, $debug = false)
    {
        $this->env = $env;
        $this->debug = $debug;

        $this->createApplication();
    }

    public function createApplication()
    {
        $app = new Application();
        $app['debug'] = $this->debug;

        if ($this->env === 'test') {
            $app['exception_handler']->disable();
        } else {
            ErrorHandler::register();
            ExceptionHandler::register($this->debug);
        }

        $this->registerErrorHandler($app);
        $this->registerServiceProviders($app);
        $this->registerSharedServices($app);

        $app->get('/', function (Application $app) {

            return new Response('OK');
        });

        $app->get('/renderer', function (Application $app) {
            return new Response('Rend');
        });

        $app->post('/*', function (Application $app, Request $request) {

            $customer = $request->getHost();

            return new \Symfony\Component\HttpFoundation\JsonResponse($customer);

            var_dump($customer);
            die();

            /** @var \Doctrine\DBAL\Connection $db */
            $db = $app['db'];

            $db->exec("SET search_path = ");

                /** @var FileLibrary $filelib */
                $filelib = $app['filelib'];




            return new Response('Rend');
        });


        return $app;
    }

    private function registerErrorHandler(Application $app)
    {
        $app->error(
            function (\Exception $e, $code) use ($app) {

                return new Response('Error');
            }
        );
    }

    private function registerServiceProviders(Application $app)
    {
        $app->register(new Silex\Provider\DoctrineServiceProvider());
        $app->register(new Silex\Provider\UrlGeneratorServiceProvider());
        $app->register(new Silex\Provider\SessionServiceProvider());

        // F.ex the session service provider initializes its default values overwriting the ones from the conf
        // so this must be *after* registering some providers to avoid making the conf setting wrong way.
        $app->register(
            new Igorw\Silex\ConfigServiceProvider(__DIR__ . "/config/{$this->env}.php")
        );

        $app->register(
            new Silex\Provider\MonologServiceProvider(),
            [
                'monolog.logfile' => __DIR__ . "/../log/{$this->env}.log",
                'monolog.level' => $app['logLevel']
            ]
        );

        $app->register(
            new Knp\Provider\ConsoleServiceProvider(),
            [
                'console.name' => 'Filelib.io',
                'console.version' => '1.0.0',
                'console.project_directory' => __DIR__,
            ]
        );
    }

    private function registerSharedServices(Application $app)
    {
        $shared = [
            'filelib.renderer' => function (Application $app) {
                $renderer = new AcceleratedRenderer(
                    $app['filelib'],
                    $app['filelib.renderer.adapter'],
                    realpath(__DIR__ . '/../data/files'),
                    '/protected'
                );
                $renderer->enableAcceleration($app['filelib.options']['enableAcceleration']);
                return $renderer;
            },
            'filelib.renderer.adapter' => function (Application $app) {
                return new SymfonyRendererAdapter();
            },
            'filelib.publisher' => function (Application $app) {

                $adapter = new SymlinkFilesystemPublisherAdapter(
                    realpath(__DIR__ . '/../web/files'),
                    "600",
                    "700",
                    "/files"
                );
                $linker = new ReversibleSequentialLinker();
                $publisher = new Publisher(
                    $adapter,
                    $linker
                );

                $publisher->attachTo($app['filelib']);

                return $publisher;
            },
            'filelib' => function (Application $app) {
                $root = realpath(sprintf(
                    __DIR__ . '/../data/%s',
                    $this->env === 'test' ? 'test_files' : 'files'
                ));

                $filelib = new FileLibrary(
                    new FilesystemStorageAdapter(
                        $root,
                        new LegacyPathCalculator(new TimeDirectoryIdCalculator()),
                        "664",
                        "775"
                    ),
                    new DoctrineDbalBackendAdapter($app['db'])
                );

                $filelib->addProfile(new FileProfile('video'));

                $filelib->addPlugin(new RandomizeNamePlugin(), ['default']);

                $filelib->addPlugin(
                    new VersionPlugin(
                        [
                            'thumbnail' => [
                                [
                                    new ExecuteMethodCommand('setImageCompression', [8]),
                                    new ExecuteMethodCommand('setImageCompressionQuality', [90]),
                                    new ExecuteMethodCommand('scaleImage', [555, 0]),
                                ],
                                'image/jpeg',
                            ],
                        ]
                    ),
                    ['default', 'video']
                );

                $filelib->addPlugin(
                    new ArbitraryVersionPlugin(
                        'quiz',
                        function () {
                            return ['x'];
                        },
                        function () {
                            return ['2x'];
                        },
                        function () {
                            return ['x' => 960];
                        },
                        function (Version $version) {
                            $params = $version->getParams();
                            if (!isset($params['x'])) {
                                return false;
                            }
                            return in_array($params['x'], [480, 960, 1440]);
                        },
                        function (File $file, Version $version) {
                            $params = $version->getParams();
                            if ($version->hasModifier('2x')) {
                                $params['x'] = $params['x'] * 2;
                            }
                            return Command::createCommandsFromDefinitions(
                                [
                                    ['setImageCompression', 8],
                                    ['setImageFormat', 'jpg'],
                                    ['setImageCompressionQuality', 85],
                                    ['scaleImage', [$params['x'], (int) $params['x'] * 1.32, true]],
                                ]
                            );
                        },
                        'image/jpeg',
                        true,
                        function () {
                            return [
                                [
                                    ['x' => 480],
                                    []
                                ],
                                [
                                    ['x' => 960],
                                    []
                                ],
                            ];
                        }
                    )

                );

                $filelib->createQueueFromAdapter($app['filelib.queue.adapter']);

                return $filelib;
            },

            'filelib.queue.adapter' => function(Application $app) {
                $options = $app['filelib.options'];
                return new IronMQAdapter(
                    $options['queue']['token'],
                    $options['queue']['projectId'],
                    $options['queue']['queueName']
                );
            },
        ];

        foreach ($shared as $name => $service) {
            $app[$name] = $app->share($service);
        }

    }
}
