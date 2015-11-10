<?php
/**
 * The Hue Command.
 */

namespace TerminalHue\Commands;

use Phue\Client;
use TerminalHue\Library\Colors;
use TerminalHue\TerminalHue as Hue;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Light extends Command
{
    protected function configure()
    {
        $this
            ->setName('light')
            ->setDescription('Control a Hue light')
            ->addArgument(
                'id',
                InputArgument::OPTIONAL,
                'The light\'s ID'
            )
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
                'If set, an effect will be applied to the light',
                'none'
            )
            ->addOption(
                'brightness',
                'b',
                InputOption::VALUE_OPTIONAL,
                'If set, the brightness will be adjusted'
            )
            ->addOption(
                'rename',
                'r',
                InputOption::VALUE_OPTIONAL,
                'If set, it will rename the light'
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
         * Get the chosen light ID.
         */
        $lightID = $input->getArgument('id');

        /**
         * Create client.
         */
        $client = new Client(Hue::getConfig()->ip, Hue::getConfig()->username);

        /**
         * Check that the chosen light ID exists and is reachable.
         */
        if (! isset($client->getLights()[$lightID]) || ! $client->getLights()[$lightID]->isReachable()) {
            $output->writeln('<error>Light doesn\'t exist or is not reachable!</error>');
            $output->writeln('Use `hue lights` to see available lights.');
            exit(1);
        }
        $light = $client->getLights()[$lightID];

        /**
         * Turn all light on or off.
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
            $light->setOn($to);

            /**
             * If set to off, turn off & stop here.
             */
            if ( ! $to ) {
                return true;
            }
        }

        /**
         * Get the color argument.
         */
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
                 * Set the light to be that color.
                 */
                $light->setXY($color[0], $color[1])->setBrightness(100);
            } else {
                $output->writeln('<question>That doesn\'t look to be a supported color! Try one of these:</question>');
                foreach($possible_moods as $possible_mood => $mood_colors) {
                    $output->writeln('<comment>' . ucfirst($possible_mood) . '</comment>');
                }
            }
        }

        /**
        if ($color) {
            /**
             * Calculate X & Y points.
             */
        /*
            $xy = Converter::hexToXY($color);

            $red = [0.675, 0.322];
            $green = [0.409, 0.518];
            $blue = [0.167, 0.04];

            $area = 1/2*(-$green[1]*$blue[0] + $red[1]*(-$green[0] + $blue[0]) + $red[0]*($green[1] - $blue[1]) + $green[0]*$blue[1]);
            $alpha = 1/(2*$area)*($red[1]*$blue[0] - $red[0]*$blue[1] + ($blue[1] - $red[1])*$xy[0] + ($red[0] - $blue[0])*$xy[1]);
            $beta = 1/(2*$area)*($red[0]*$green[1] - $red[1]*$green[0] + ($red[1] - $green[1])*$xy[0] + ($green[0] - $red[0])*$xy[1]);
            $gamma = 1 - $alpha - $beta;
            $altX = $alpha*$red[0] + $beta*$green[0] + $gamma*$blue[0];
            $altY = $alpha*$red[1] + $beta*$green[1] + $gamma*$blue[1];

            if ($alpha > 1 && $beta > 1 && $gamma > 1) {
                $useXY = $xy;
            } else {
                $useXY = [$altX, $altY];
            }

            $client->sendCommand(
                (new SetLightState($light))
                    ->xy($useXY[0], $useXY[1])
            );
        }
        */

        /**
         * If effect option on, set the effect.
         */
        if ($input->getOption('effect')) {
            $light->setEffect($input->getOption('effect'));
        } else {
            $light->setEffect('none');
        }

        /**
         * If brightness option on, set it.
         */
        if ($input->getOption('brightness')) {
            $light->setBrightness($input->getOption('brightness'));
        }

        /**
         * If rename option set, rename.
         */
        if ($input->getOption('rename')) {
            $light->setName($input->getOption('rename'));
        }
    }
}