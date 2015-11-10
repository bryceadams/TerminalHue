<?php
/**
 * The Hue Command.
 */

namespace TerminalHue\Commands;

use Phue\Client;
use Phue\Command\CreateUser;
use Phue\Command\Ping;
use Phue\Transport\Exception\ConnectionException;
use Phue\Transport\Exception\LinkButtonException;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Setup extends Command
{
    protected function configure()
    {
        $this
            ->setName('setup')
            ->setDescription('Setup the Hue Bridge connection')
            ->addArgument(
                'ip',
                InputArgument::OPTIONAL,
                'What is the IP?'
            )
            ->addArgument(
                'username',
                InputArgument::OPTIONAL,
                'What is the desired username?'
            )
            ->addOption(
               'yell',
               null,
               InputOption::VALUE_NONE,
               'If set, the task will yell in uppercase letters'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ip = $input->getArgument('ip');
        $username = $input->getArgument('username');

        if ($ip) {
            $ipAddress = $ip;
        } else {
            $output->writeln('Finding your Bridge...');
            $bridges = json_decode(file_get_contents('https://www.meethue.com/api/nupnp'));

            /**
             * No bridges - stop.
             */
            if (! $bridges) {
                $output->writeln('No bridges found - sorry!');
            }

            /**
             * One bridge, grab IP.
             */
            if (count($bridges) == 1) {
                $ipAddress = $bridges[0]->internalipaddress;
            }

            /**
             * Multiple bridges? Ask for IP.
             */
            if (count($bridges) > 1) {
                $choices = [];
                foreach($bridges as $bridge) {
                    $choices[] = $bridge->internalipaddress;
                }

                $helper = $this->getHelper('question');
                $question = new ChoiceQuestion(
                    'We found multiple bridges. Which one do you want to connect to?',
                    $choices,
                    0
                );
                $question->setErrorMessage('Choice %s is invalid.');

                $choice = $helper->ask($input, $output, $question);
                $ipAddress = $choice;
                $output->writeln('You have just selected: '.$choice);
            }

        }

        if ($input->getOption('yell')) {
            //
        }

        /**
         * Username.
         */
        $user = $username ? $username : get_current_user();
        $user = $user . substr(md5(microtime()),rand(0,26),10);

        /**
         * Authenticate with Bridge.
         */
        $client = new Client($ipAddress, $user);

        $output->writeln("Testing connection to bridge at {$client->getHost()}");

        try {
            $client->sendCommand(
                new Ping
            );
        } catch (ConnectionException $e) {
            $output->writeln("Issue connecting to bridge - " . $e->getMessage());
            exit(1);
        }

        $output->writeln("Attempting to create user: " . $user . "...");
        $output->writeln("Press the Bridge's button! Waiting...");

        $maxTries = 30;
        for ($i = 1; $i <= $maxTries; ++$i) {
            try {
                $response = $client->sendCommand(
                    new CreateUser($user)
                );
                $output->write("\n");
                $output->writeln("Successfully created new user: " . $response->username);
                break;
            } catch (LinkButtonException $e) {
                $output->write(".");
            } catch (Exception $e) {
                $output->writeln("Failure to create user. Please try again!");
                $output->writeln("Reason: " . $e->getMessage());
                break;
            }
            sleep(1);
        }

        /**
         * Save to memory.
         * @todo Would prefer a better way of doing this, if you can think of one.
         */
        if ($ipAddress && $user) {
            file_put_contents("config.txt", json_encode(['ip' => $ipAddress, 'username' => $user]));
        }

        $output->writeln("Let there be light! Use `hue list` to see available commands.");
    }
}