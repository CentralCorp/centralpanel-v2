<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\DotenvEditor;
use App\Support\PanelInstallation;
use Exception;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Throwable;

class InstallController extends Controller
{
    public const TEMP_KEY = PanelInstallation::TEMP_KEY;

    public const MIN_PHP_VERSION = '8.1';

    public const REQUIRED_EXTENSIONS = [
        'bcmath',
        'ctype',
        'json',
        'mbstring',
        'openssl',
        'PDO',
        'tokenizer',
        'xml',
        'xmlwriter',
        'curl',
        'fileinfo',
        'zip',
    ];

    protected array $databaseDrivers = [
        'mysql' => 'MySQL/MariaDB',
        'sqlite' => 'SQLite',
    ];

    protected bool $hasRequirements;
    protected array $requirements;

    public function __construct()
    {
        $this->requirements = static::getRequirements();
        $this->hasRequirements = !in_array(false, $this->requirements, true);

        $this->middleware(function (Request $request, callable $next) {
            if (PanelInstallation::ensureInstalledState()) {
                return redirect('/')->with('error', __('messages.install.already_installed'));
            }

            return $next($request);
        });
    }

    private function getCorrectAppUrl()
    {
        // Utiliser l'URL de la requête actuelle pour déterminer le bon domaine
        $request = request();

        if ($request) {
            $scheme = $request->isSecure() ? 'https' : 'http';
            $host = $request->getHost();
            $port = $request->getPort();

            $url = $scheme . '://' . $host;

            // Ajouter le port seulement s'il n'est pas standard
            if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
                $url .= ':' . $port;
            }

            return $url;
        }

        // Fallback
        return url('/');
    }

    private function prepareEnvironment()
    {
        // Créer les dossiers nécessaires
        $directories = [
            storage_path('app'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            storage_path('app/public'),
        ];

        foreach ($directories as $directory) {
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }
        }

        // Créer le fichier de log si nécessaire
        $logFile = storage_path('logs/laravel.log');
        if (!File::exists($logFile)) {
            File::put($logFile, '');
            chmod($logFile, 0644);
        }
    }

    private function createEnvFile()
    {
        // Le fichier .env existe déjà avec la clé temporaire pour permettre à Laravel de démarrer
        // On met juste à jour les valeurs nécessaires pour l'installation
        if (File::exists(base_path('.env'))) {
            $this->updateEnvValues([
                'APP_NAME' => 'CentralCorp Panel',
                'APP_ENV' => 'production',
                'APP_DEBUG' => false,
                'APP_URL' => $this->getCorrectAppUrl(),
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $this->sqliteDatabasePath(),
            ]);
        } else {
            // Fallback si le fichier n'existe pas
            $this->createBasicEnvFile();
        }
    }

    private function createBasicEnvFile()
    {
        File::put(base_path('.env'), DotenvEditor::content([
            [
                'APP_NAME' => 'CentralCorp Panel',
                'APP_ENV' => 'production',
                'APP_KEY' => self::TEMP_KEY,
                'APP_DEBUG' => false,
                'APP_URL' => $this->getCorrectAppUrl(),
            ],
            [
                'LOG_CHANNEL' => 'stack',
                'LOG_DEPRECATIONS_CHANNEL' => 'null',
                'LOG_LEVEL' => 'debug',
            ],
            [
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $this->sqliteDatabasePath(),
            ],
            [
                'BROADCAST_DRIVER' => 'log',
                'CACHE_DRIVER' => 'file',
                'FILESYSTEM_DISK' => 'local',
                'QUEUE_CONNECTION' => 'sync',
                'SESSION_DRIVER' => 'file',
                'SESSION_LIFETIME' => 120,
            ],
            [
                'MEMCACHED_HOST' => '127.0.0.1',
            ],
            [
                'REDIS_HOST' => '127.0.0.1',
                'REDIS_PASSWORD' => 'null',
                'REDIS_PORT' => 6379,
            ],
            [
                'MAIL_MAILER' => 'smtp',
                'MAIL_HOST' => 'mailpit',
                'MAIL_PORT' => 1025,
                'MAIL_USERNAME' => 'null',
                'MAIL_PASSWORD' => 'null',
                'MAIL_ENCRYPTION' => 'null',
                'MAIL_FROM_ADDRESS' => 'hello@example.com',
                'MAIL_FROM_NAME' => 'CentralCorp Panel',
            ],
        ]));
    }

    private function updateEnvValues(array $values)
    {
        DotenvEditor::updateFile(base_path('.env'), $values);
    }

    private function sqliteDatabasePath(): string
    {
        return DotenvEditor::normalizePath(database_path('database.sqlite'));
    }

    private function ensureDatabaseExists()
    {
        $databasePath = database_path('database.sqlite');
        $databaseDir = dirname($databasePath);

        // Créer le dossier database s'il n'existe pas
        if (!File::exists($databaseDir)) {
            File::makeDirectory($databaseDir, 0755, true);
        }

        // Créer le fichier SQLite s'il n'existe pas
        if (!file_exists($databasePath)) {
            try {
                // Créer le fichier
                touch($databasePath);
                // Définir les permissions appropriées
                chmod($databasePath, 0666);
            } catch (Exception $e) {
                throw new Exception('Impossible de créer le fichier de base de données SQLite: ' . $e->getMessage());
            }
        }

        // Vérifier que le fichier est accessible en écriture
        if (!is_writable($databasePath)) {
            throw new Exception('Le fichier de base de données SQLite n\'est pas accessible en écriture: ' . $databasePath);
        }
    }

    public function showDatabase()
    {
        try {
            if (!$this->hasRequirements) {
                return view('install.requirements', [
                    'requirements' => $this->requirements,
                ]);
            }

            return view('install.database', [
                'databaseDrivers' => $this->databaseDrivers,
            ]);
        } catch (Exception $e) {
            return response($e->getMessage(), 500);
        }
    }

    /**
     * Unified installation handler - handles both database setup and admin creation
     */
    public function install(Request $request)
    {
        try {
            // Validate all fields: database + admin
            $this->validate($request, [
                'type' => ['required', Rule::in(array_keys($this->databaseDrivers))],
                'host' => ['required_unless:type,sqlite'],
                'port' => ['nullable', 'integer', 'between:1,65535'],
                'database' => ['required_unless:type,sqlite'],
                'user' => ['required_unless:type,sqlite'],
                'db_password' => ['nullable'],
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255',
                'password' => ['required', 'confirmed', Password::default()],
            ]);

            $this->prepareEnvironment();
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');

            // Step 1: Configure database
            $databaseType = $request->input('type');
            $databaseEnvValues = [];

            if ($databaseType === 'sqlite') {
                // Create SQLite database file
                $this->ensureDatabaseExists();

                $databaseEnvValues = [
                    'DB_CONNECTION' => 'sqlite',
                    'DB_DATABASE' => $this->sqliteDatabasePath(),
                    'DB_HOST' => '',
                    'DB_PORT' => '',
                    'DB_USERNAME' => '',
                    'DB_PASSWORD' => '',
                ];

                // Configure and test SQLite connection
                DB::purge('sqlite');
                Config::set('database.default', 'sqlite');
                Config::set('database.connections.sqlite.database', database_path('database.sqlite'));
                DB::connection('sqlite')->getPdo();
            } else {
                $host = $request->input('host');
                $port = $request->input('port') ?: '3306';
                $database = $request->input('database');
                $user = $request->input('user');
                $password = $request->input('db_password');

                // Test connection first
                Config::set('database.connections.test', [
                    'driver' => $databaseType,
                    'host' => $host,
                    'port' => $port,
                    'database' => $database,
                    'username' => $user,
                    'password' => $password,
                ]);

                DB::connection('test')->getPdo();

                $databaseEnvValues = [
                    'DB_CONNECTION' => $databaseType,
                    'DB_HOST' => $host,
                    'DB_PORT' => $port,
                    'DB_DATABASE' => $database,
                    'DB_USERNAME' => $user,
                    'DB_PASSWORD' => $password,
                ];

                // Configure the default connection for migrations
                Config::set('database.default', $databaseType);
                Config::set("database.connections.{$databaseType}", [
                    'driver' => $databaseType,
                    'host' => $host,
                    'port' => $port,
                    'database' => $database,
                    'username' => $user,
                    'password' => $password,
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                    'strict' => true,
                    'engine' => null,
                ]);
            }

            // Step 2: Run migrations & seed panel defaults + default instance
            Artisan::call('migrate:fresh', ['--force' => true]);
            \App\Support\PanelOptions::ensureDefaults();

            // Step 3: Create admin user
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]);

            // Step 4: Post-installation setup
            try {
                Artisan::call('storage:link', ['--force' => true]);
            } catch (Throwable) {
                // The link may already exist on local/dev installs.
            }

            File::put(storage_path('installed'), 'Installation complétée le ' . now()->format('Y-m-d H:i:s') . "\nAdmin: " . $user->email);

            $correctUrl = $this->getCorrectAppUrl();
            Config::set('app.url', $correctUrl);

            $this->writeEnvironment($this->buildFinalEnvValues(
                $databaseEnvValues,
                $this->makeAppKey(),
                $correctUrl
            ));

            return view('install.success');
        } catch (Throwable $t) {
            return back()->withInput()->with('error', __('messages.install.install_error') . ' ' . $t->getMessage());
        }
    }

    public function finish()
    {
        try {
            if (!PanelInstallation::ensureInstalledState()) {
                return redirect()->route('install.database');
            }

            return view('install.success');
        } catch (Exception $e) {
            return response($e->getMessage(), 500);
        }
    }

    private function makeAppKey(): string
    {
        return 'base64:' . base64_encode(Encrypter::generateKey(config('app.cipher')));
    }

    private function buildFinalEnvValues(array $databaseValues, string $appKey, string $appUrl): array
    {
        return array_merge([
            'APP_NAME' => 'CentralCorp Panel',
            'APP_ENV' => 'production',
            'APP_KEY' => $appKey,
            'APP_DEBUG' => false,
            'APP_URL' => $appUrl,
            'LOG_CHANNEL' => 'stack',
            'LOG_DEPRECATIONS_CHANNEL' => 'null',
            'LOG_LEVEL' => 'error',
        ], $databaseValues, [
            'BROADCAST_DRIVER' => 'log',
            'CACHE_DRIVER' => 'file',
            'FILESYSTEM_DISK' => 'local',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'file',
            'SESSION_LIFETIME' => 120,
            'MEMCACHED_HOST' => '127.0.0.1',
            'REDIS_HOST' => '127.0.0.1',
            'REDIS_PASSWORD' => 'null',
            'REDIS_PORT' => 6379,
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => 'mailpit',
            'MAIL_PORT' => 1025,
            'MAIL_USERNAME' => 'null',
            'MAIL_PASSWORD' => 'null',
            'MAIL_ENCRYPTION' => 'null',
            'MAIL_FROM_ADDRESS' => 'hello@example.com',
            'MAIL_FROM_NAME' => 'CentralCorp Panel',
        ]);
    }

    private function writeEnvironment(array $values): void
    {
        DotenvEditor::updateFile(base_path('.env'), $values);
    }

    public static function getRequirements(): array
    {
        $requirements = [
            'php' => version_compare(PHP_VERSION, static::MIN_PHP_VERSION, '>='),
            'writable' => is_writable(base_path()),
            'storage-writable' => is_writable(storage_path()),
            'bootstrap-writable' => is_writable(base_path('bootstrap/cache')),
        ];

        foreach (static::REQUIRED_EXTENSIONS as $extension) {
            $requirements['extension-' . $extension] = extension_loaded($extension);
        }

        return $requirements;
    }

    public static function parsePhpVersion(): string
    {
        preg_match('/^(\d+)\.(\d+)/', PHP_VERSION, $matches);

        if (count($matches) > 2) {
            return "{$matches[1]}.{$matches[2]}";
        }

        return PHP_VERSION;
    }
}
