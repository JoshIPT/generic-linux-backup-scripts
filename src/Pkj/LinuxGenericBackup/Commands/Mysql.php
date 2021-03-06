<?php
/**
 * Created by PhpStorm.
 * User: peecdesktop
 * Date: 08.08.14
 * Time: 23:45
 */

namespace Pkj\LinuxGenericBackup\Commands;


use Pkj\LinuxGenericBackup\BackupHandler;
use Pkj\LinuxGenericBackup\GenericDatabaseInstructions;
use Pkj\LinuxGenericBackup\JsonFileExpressionParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class Mysql extends BaseCommand{



    protected function configure() {

        $this->setName("backups:mysql")
            ->setDescription("Starts database backup for mysql databases, see config/database.json.")
            ->setDefinition(
                array_merge(array(
                ),BackupHandler::genericCommandArguments("database.json")
            ))
            ->setHelp(<<<EOT

Usage:

<info>./run backups:mysql</info>

EOT
            );

    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        try {
            $handler = $this->container->get('backup.handler');
            $generic = BackupHandler::genericCommandArgumentsParse($input);
            $handler->injectInterfaces($output, $generic, "database.json");
            $handler->allowCmdOverride($input);
            new GenericDatabaseInstructions($handler);
            $handler->addTask(array($this, 'createBackups'));
            $handler->run();
        } catch(\Exception $e) {
            $this->container->get('notification.manager')->error("Error creating backups: " . $e->getMessage());
            throw $e;
        }
    }

    public function createBackups (BackupHandler $handler) {
        $createdBackupArchives = array();
        $config =  $handler->config['database'];

        // Get databases in array..
        $databases = array();
        $dbh = new \PDO( "mysql:host={$config['host']}", $config['user'], $config['password'] );
        $dbs = $dbh->query( 'SHOW DATABASES' );
        while( ( $db = $dbs->fetchColumn( 0 ) ) !== false ) {
            if (!in_array($db, $config['ignore_databases'])) {
                $databases[] = $db;
            }
        }

        // Run.
        foreach($databases as $db) {
            $bpath = $handler->getBackupFilePath($db);
            $cmd = "mysqldump --force --opt --user={$config['user']} --password={$config['password']} --databases $db  | gzip -c >  $bpath";
            $handler->doExec($cmd, false, function ($msg) {
                return preg_replace("/password=(.*?)--databases/i", 'password="***" --databases', $msg);
            });
            $createdBackupArchives[] = $bpath;
        }
        return $createdBackupArchives;
    }



} 
