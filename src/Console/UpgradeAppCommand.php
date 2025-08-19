<?php

/**
 * OAI-PMH 2.0 Data Provider
 * Copyright (C) 2024 Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace OCC\OaiPmh2\Console;

use Composer\Command\InstallCommand;
use Composer\Command\RunScriptCommand;
use Composer\Console\Application as ComposerApplication;
use Composer\InstalledVersions;
use Exception;
use OCC\OaiPmh2\Configuration;
use OCC\OaiPmh2\Console;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use ZipArchive;

/**
 * Add or update a record in the database.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @extends Console<array{
 *     release: non-empty-string,
 *     dev: bool,
 *     force: bool,
 *     list: bool
 * }>
 * @phpstan-type ReleaseInfo = array{
 *     url: string,
 *     pretty_version: string,
 *     version: string,
 *     install_path?: string,
 *     dev: bool
 * }
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
#[AsCommand(
    name: 'app:upgrade',
    description: 'Upgrade this OAI-PMH2 Data Provider',
    aliases: ['app:install']
)]
final class UpgradeAppCommand extends Console
{
    /**
     * This holds information about the application.
     *
     * @var array{
     *     name: string,
     *     pretty_version: string,
     *     version: string,
     *     install_path: string,
     *     dev: bool,
     *     ...
     * }
     */
    protected array $appInfo;

    /**
     * This holds information about the release to be installed.
     *
     * @var ReleaseInfo
     */
    protected array $releaseInfo;

    /**
     * This holds the filesystem handler.
     */
    protected Filesystem $fs;

    /**
     * This holds the HTTP client instance.
     */
    protected HttpClientInterface $http;

    /**
     * Configures the current command.
     *
     * @return void
     */
    #[\Override]
    protected function configure(): void
    {
        $this->addArgument(
            'release',
            InputArgument::OPTIONAL,
            'The release/branch to install or "latest" for newest stable release',
            'latest'
        );
        $this->addOption(
            'list',
            'l',
            InputOption::VALUE_NONE,
            'List all available stable releases from Packagist'
        );
        $this->addOption(
            'dev',
            null,
            InputOption::VALUE_NONE,
            'Use development branches instead of stable releases'
        );
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force installation even if the current version is equal or newer than the target release'
        );
        parent::configure();
    }

    /**
     * Cleans up the temporary directory.
     *
     * @return void
     */
    protected function cleanUp(): void
    {
        if (isset($this->releaseInfo['install_path']) && file_exists($this->releaseInfo['install_path'])) {
            $this->fs->remove($this->releaseInfo['install_path']);
        }
    }

    /**
     * Compares current version to requested version.
     *
     * @return void
     *
     * @throws Exception if requested version is not newer and flag "--force" is not set
     */
    protected function compareVersions(): void
    {
        $compare = version_compare($this->appInfo['version'], $this->releaseInfo['version']);
        if ($compare > -1 && !$this->arguments['force']) {
            throw new Exception(
                sprintf(
                    'Current version is %s "%s". Use flag "--force" to install anyway.',
                    $compare === 0 ? 'up-to-date with' : 'newer than',
                    $this->arguments['release']
                )
            );
        }
    }

    /**
     * Fetches the release archive and saves it to a temporary file.
     *
     * @return string The path to the downloaded archive
     *
     * @throws Exception if the download fails
     */
    protected function download(): string
    {
        $this->io->writeln('Downloading release...');
        $progressBar = $this->io->createProgressBar();
        $progressBar->setFormat(' [%bar%] %percent%% (of %max% bytes)');
        $progressBar->start();
        $callback = function (int $dlNow, int $dlSize) use ($progressBar): void {
            if ($dlSize > -1 && $progressBar->getMaxSteps() !== $dlSize) {
                $progressBar->setMaxSteps($dlSize);
            }
            $progressBar->setProgress($dlNow);
        };
        $response = $this->http->request(
            'GET',
            $this->releaseInfo['url'],
            [
                'headers' => [
                    'Accept' => 'application/zip, application/vnd.github+json',
                    'User-Agent' => Configuration::getInstance()->repositoryName,
                    'X-GitHub-Api-Version' => '2022-11-28'
                ],
                'on_progress' => $callback
            ]
        );
        if ($response->getStatusCode() !== 200) {
            $response->cancel();
            $progressBar->clear();
            throw new Exception(
                sprintf(
                    'Failed to fetch release archive from "%s".',
                    $this->releaseInfo['url']
                ),
                $response->getStatusCode()
            );
        }
        $file = '';
        try {
            $file = $this->fs->tempnam(sys_get_temp_dir(), 'oai', '.zip');
            foreach ($this->http->stream($response) as $chunk) {
                $this->fs->appendToFile($file, $chunk->getContent());
            }
            $progressBar->finish();
            $this->io->newLine();
        } catch (IOException $exception) {
            if (file_exists($file)) {
                $this->fs->remove($file);
            }
            $response->cancel();
            $progressBar->clear();
            throw $exception;
        }
        return $file;
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface $input The input
     * @param OutputInterface $output The output
     *
     * @return int 0 if everything went fine, or an error code
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->appInfo = InstalledVersions::getRootPackage();
        $this->http = HttpClient::create();
        $this->io->writeln(
            sprintf(
                'Current version: <info>%s</info>',
                $this->appInfo['pretty_version']
            )
        );

        if ($this->arguments['list']) {
            return $this->listAvailableReleases();
        }

        $this->fs = new Filesystem();
        try {
            $this->setReleaseInfo();
            if (!$this->appInfo['dev'] || !$this->arguments['dev']) {
                $this->compareVersions();
            }
            $this->getNewRelease();
            $this->replaceFiles();
            $this->cleanUp();
            $this->installDependencies();
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            $this->runPostUpgradeCmd();
        } catch (Exception $exception) {
            $this->cleanUp();
            $this->io->getErrorStyle()->error($exception->getMessage());
            return Command::FAILURE;
        }
        $this->io->newLine();
        $this->io->success(
            sprintf(
                '%s %s successfully installed!',
                Configuration::getInstance()->repositoryName,
                $this->releaseInfo['pretty_version']
            )
        );
        return Command::SUCCESS;
    }

    /**
     * Fetches the release information from Packagist.
     *
     * @return array<string, ReleaseInfo> Release information
     *
     * @throws Exception if fetching release information fails
     */
    protected function fetchReleases(): array
    {
        $packageName = $this->appInfo['name'];
        if ($this->arguments['dev']) {
            $packageName .= '~dev';
        }
        $response = $this->http->request(
            'GET',
            'https://repo.packagist.org/p2/' . $packageName . '.json',
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => Configuration::getInstance()->repositoryName
                ]
            ]
        );
        if ($response->getStatusCode() !== 200) {
            throw new Exception(
                sprintf(
                    'Failed to fetch releases of "%s" from Packagist.',
                    $this->appInfo['name']
                ),
                $response->getStatusCode()
            );
        }
        /** @var ?array{packages: array<string, list<array<string, mixed>>>} */
        $releases = json_decode($response->getContent(), true);
        if (!isset($releases)) {
            throw new Exception('Failed to decode response from Packagist.');
        }
        /** @var list<array{version: string, version_normalized: string, dist: array{url: string}}> */
        $releases = $releases['packages'][$this->appInfo['name']] ?? [];
        $versions = [];
        $numOfReleases = count($releases);
        for ($i = 0; $i < $numOfReleases; $i++) {
            $versions[$releases[$i]['version']] = [
                'url' => $releases[$i]['dist']['url'],
                'pretty_version' => $releases[$i]['version'],
                'version' => $releases[$i]['version_normalized'],
                'dev' => $this->arguments['dev']
            ];
        }
        return $versions;
    }

    /**
     * Gets the specified release.
     *
     * @return void
     *
     * @throws Exception if no newer release can be found
     */
    protected function getNewRelease(): void
    {
        if (!$this->appInfo['dev'] && !$this->arguments['dev']) {
            $compare = version_compare($this->appInfo['version'], $this->releaseInfo['version']);
            $this->io->writeln(
                sprintf(
                    'Found version: %s',
                    match ($compare) {
                        -1 => '<info>' . $this->releaseInfo['pretty_version'] . '</info> (newer)',
                        0 => '<info>' . $this->releaseInfo['pretty_version'] . '</info> (current)',
                        1 => '<comment>' . $this->releaseInfo['pretty_version'] . '</comment> (older)'
                    }
                )
            );
        } else {
            $this->io->writeln(
                sprintf(
                    'Found version: <info>%s</info>',
                    $this->releaseInfo['pretty_version']
                )
            );
        }
        $file = $this->download();
        try {
            $this->unpack($file);
            $this->fs->remove($file);
        } catch (Exception $exception) {
            $this->fs->remove($file);
            throw $exception;
        }
    }

    /**
     * Installs dependencies for new release.
     *
     * @return void
     */
    protected function installDependencies(): void
    {
        $this->io->writeln('Installing dependencies...');
        $app = new ComposerApplication();
        $app->add(new InstallCommand());
        $app->doRun(
            new ArrayInput([
                'command' => 'install',
                '--no-cache' => true,
                '--no-dev' => true,
                '--quiet' => true,
                '--working-dir' => Path::canonicalize($this->appInfo['install_path'] . DIRECTORY_SEPARATOR)
            ]),
            $this->io
        );
    }

    /**
     * Fetches and displays available releases from GitHub.
     *
     * @return int 0 if everything went fine, or an error code
     */
    protected function listAvailableReleases(): int
    {
        try {
            $releases = $this->fetchReleases();
        } catch (Exception $exception) {
            $this->io->getErrorStyle()->error($exception->getMessage());
            return Command::FAILURE;
        }
        $this->io->write('Available versions:');
        if (count($releases) === 0) {
            $this->io->writeln(' [none]');
            return Command::SUCCESS;
        } else {
            $this->io->newLine();
        }
        foreach ($releases as $release) {
            if ($this->appInfo['version'] === $release['version']) {
                $release['pretty_version'] = '<info>' . $release['pretty_version'] . '</info> (current)';
            }
            $line = ' ∘ ' . $release['pretty_version'];
            $this->io->writeln($line);
        }
        return Command::SUCCESS;
    }

    /**
     * Replaces old files with new release.
     *
     * @return void
     *
     * @throws Exception if something goes wrong
     */
    protected function replaceFiles(): void
    {
        $this->io->writeln('Moving new files in place...');
        $newPath = Path::canonicalize($this->releaseInfo['install_path'] ?? sys_get_temp_dir());
        $oldPath = Path::canonicalize($this->appInfo['install_path']);
        if (!is_writable($oldPath)) {
            throw new Exception(
                sprintf(
                    'Could not write to directory "%s".',
                    $oldPath . DIRECTORY_SEPARATOR
                )
            );
        }
        if (!is_readable($newPath . '/upgrade.yml')) {
            throw new Exception(
                sprintf(
                    'Upgrade configuration "%s" not found or not readable.',
                    $newPath . '/upgrade.yml'
                )
            );
        }
        /** @var array{directories: array<string, 'copy'|'mirror'>} */
        $config = Yaml::parseFile($newPath . '/upgrade.yml');
        /** @psalm-suppress RiskyTruthyFalsyComparison */
        foreach (scandir($newPath) ?: [] as $node) {
            if (is_dir($node) && array_key_exists($node, $config['directories'])) {
                $this->fs->mirror(
                    $newPath . DIRECTORY_SEPARATOR . $node,
                    $oldPath . DIRECTORY_SEPARATOR . $node,
                    null,
                    [
                        'override' => true,
                        'delete' => $config['directories'][$node] === 'mirror'
                    ]
                );
            } elseif (is_file($node)) {
                $this->fs->copy(
                    $newPath . DIRECTORY_SEPARATOR . $node,
                    $oldPath . DIRECTORY_SEPARATOR . $node,
                    true
                );
            }
        }
    }

    /**
     * Runs post-upgrade maintenance tasks.
     *
     * @return void
     */
    protected function runPostUpgradeCmd(): void
    {
        $this->io->writeln('Running post-upgrade migrations...');
        $app = new ComposerApplication();
        $app->add(new RunScriptCommand());
        $app->doRun(
            new ArrayInput([
                'command' => 'run-script',
                'script' => 'post-upgrade-cmd',
                '--working-dir' => Path::canonicalize($this->appInfo['install_path'] . DIRECTORY_SEPARATOR)
            ]),
            $this->io
        );
    }

    /**
     * Populates the release information.
     *
     * @return void
     *
     * @throws Exception if the given release is invalid or not newer
     */
    protected function setReleaseInfo(): void
    {
        $releases = $this->fetchReleases();
        if (count($releases) === 0) {
            throw new Exception(
                sprintf(
                    'There are no releases available for "%s". Use flag "--dev" for development branches.',
                    $this->appInfo['name']
                )
            );
        }
        if ($this->arguments['release'] === 'latest') {
            if ($this->arguments['dev']) {
                throw new Exception(
                    'Alias "latest" is not supported when using "--dev". Set target development branch explicitly.'
                );
            }
            $latestFound = false;
            foreach ($releases as $release) {
                if (preg_match('/^[1-9][0-9]*\.[0-9]+\.[0-9]+\.[0-9]+$/', $release['version']) === 1) {
                    $this->releaseInfo = $release;
                    $latestFound = true;
                    break;
                }
            }
            if (!$latestFound) {
                throw new Exception(
                    'There is no "latest" release considered stable. Set target release explicitly.'
                );
            }
        } else {
            if (!array_key_exists($this->arguments['release'], $releases)) {
                throw new Exception(
                    sprintf(
                        'Invalid release "%s" given. Use flag "--list" to see available releases.',
                        $this->arguments['release']
                    )
                );
            }
            $this->releaseInfo = $releases[$this->arguments['release']];
        }
    }

    /**
     * Unpacks an ZIP archive to a temporary location.
     *
     * @param string $file The fully qualified filename to unpack
     *
     * @return void
     *
     * @throws Exception if unpacking fails
     */
    protected function unpack(string $file): void
    {
        $this->io->writeln('Unpacking archive...');
        $progressBar = $this->io->createProgressBar(100);
        $progressBar->setFormat(' [%bar%] %percent%%');
        $progressBar->start();
        $callback = function (float $state) use ($progressBar): void {
            $progressBar->setProgress((int) $state * 100);
        };
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::RDONLY) !== true) {
            $progressBar->clear();
            throw new Exception('Failed to open archive file.');
        }
        $tempDir = $zip->getNameIndex(0);
        $zip->registerProgressCallback(0.01, $callback);
        if ($zip->extractTo(sys_get_temp_dir()) !== true) {
            $progressBar->clear();
            $zip->close();
            throw new Exception(
                sprintf(
                    'Failed to extract archive to temporary location "%s".',
                    sys_get_temp_dir() . DIRECTORY_SEPARATOR
                )
            );
        }
        $this->releaseInfo['install_path'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $tempDir;
        $zip->close();
        $progressBar->finish();
        $this->io->newLine();
    }
}
