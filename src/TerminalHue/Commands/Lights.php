<?php
/**
 * The Hue Command.
 */

namespace TerminalHue\Commands;

use Phue\Client;
use Symfony\Component\Console\Helper\Table;
use TerminalHue\Library\Colors;
use TerminalHue\TerminalHue as Hue;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Lights extends Command
{
    protected function configure()
    {
        $this
            ->setName('lights')
            ->setDescription('Control a Hue light')
            ->addArgument(
                'on',
                InputArgument::OPTIONAL,
                'Turn all lights on or off'
            )
            ->addArgument(
                'color',
                InputArgument::OPTIONAL,
                'The desired color'
            )
            ->addOption(
                'effect',
                'e',
                InputOption::VALUE_OPTIONAL,
                'If set, an effect will be applied to all lights',
                'none'
            )
            ->addOption(
                'brightness',
                'b',
                InputOption::VALUE_OPTIONAL,
                'If set, the brightness will be adjusted'
            )
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        /**
         * If Hue isn't configured yet, stop.
         */
        if (! Hue::getConfig()) {
            $output->writeln("You haven't gone through the set up yet! Run `hue setup` first.");
            exit(1);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**
         * Create client.
         */
        $client = new Client(Hue::getConfig()->ip, Hue::getConfig()->username);

        /**
         * Turn all lights on or off.
         */
        $on = $input->getArgument('on');
        if ($on) {
            $to = true;
            if ( $on == 'off' ) {
                $to = false;
            }

            /**
             * If set to on, turn on.
             */
            foreach ($client->getLights() as $lightId => $light) {
                $light->isReachable() ? $light->setOn($to) : '';
            }

            /**
             * If set to off, turn off & stop here.
             */
            if ( ! $to ) {
                return true;
            }
        }

        $color = $input->getArgument('color');

        /**
         * Handle if only 'on' param set (intended to be color).
         */
        if ($on && $on !== 'on' && $on !== 'off' && ! $color ) {
            $color = $on;
        }

        /**
         * Possible moods.
         */
        $possible_moods = Colors::colors();

        /**
         * If have mood, set it.
         */
        if ( $color ) {
            /**
             * If mood matches possible ones, go ahead.
             */
            if ( isset($possible_moods[$color]) ) {
                $color = $possible_moods[$color];
                /**
                 * Set each light to be that color.
                 */
                foreach ($client->getLights() as $lightId => $light) {
                    if ($light->isReachable()) {
                        $light->setXY($color[0], $color[1])->setBrightness(100);
                    }
                }
            } else {
                $output->writeln('<question>That doesn\'t look to be a supported color! Try one of these:</question>');
                foreach($possible_moods as $possible_mood => $mood_colors) {
                    $output->writeln('<comment>' . ucfirst($possible_mood) . '</comment>');
                }
            }
        }

        /**
         * No arguments added, just list available lights.
         */
        if (! $on && ! $color) {
            if ($client->getLights()) {
                foreach ($client->getLights() as $lightId => $light) {
                    $rows[] = [$light->getId(), $light->getName(), $light->getType(), $light->isOn() ? '✔' : '✘', $light->isReachable() ? '✔' : '✘'];
                }
                $table = new Table($output);
                $table->setHeaders(array('ID', 'Name', 'Type', 'On', 'Reachable'))->setRows($rows);
                $table->render();
            }
        }

        /**
         * If effect option on, set the effect for all lights.
         */
        $effect = ($input->getOption('effect')) ? $input->getOption('effect') : 'none';
        foreach ($client->getLights() as $lightId => $light) {
            if ($light->isOn() && $light->isReachable()) {
                $light->setEffect($effect);
            }
        }

        /**
         * If brightness option on, set it.
         */
        if ($input->getOption('brightness')) {
            foreach ($client->getLights() as $lightId => $light) {
                if ($light->isOn() && $light->isReachable()) {
                    $light->setBrightness($input->getOption('brightness'));
                }
            }
        }
    }
}