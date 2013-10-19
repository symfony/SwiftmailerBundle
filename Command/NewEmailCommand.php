<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A console command for creating and sending simple emails
 *
 * @author Gusakov Nikita <dev@nkt.me>
 */
class NewEmailCommand extends ContainerAwareCommand
{
    /**
     * The mail parts
     * @var array
     */
    private $options = array('from', 'to', 'subject', 'body');

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('swiftmailer:email:new')
            ->setDescription('Send simple email message')
            ->addOption('from', 'f', InputOption::VALUE_OPTIONAL, 'The from address of the message')
            ->addOption('to', 't', InputOption::VALUE_OPTIONAL, 'The to address of the message')
            ->addOption('subject', 'sub', InputOption::VALUE_OPTIONAL, 'The subject of the message')
            ->addOption('body', 'b', InputOption::VALUE_OPTIONAL, 'The body of the message')
            ->addOption('mailer', 'm', InputOption::VALUE_OPTIONAL, 'The mailer name', 'default')
            ->addOption('content-type', 'ct', InputOption::VALUE_OPTIONAL, 'The body content type of the message', 'text/html')
            ->addOption('charset', null, InputOption::VALUE_OPTIONAL, 'The body charset of the message', 'UTF8')
            ->assOption('body-input', null, InputOption::VALUE_OPTIONAL, 'The source where body come from [stdin|file]', 'stdin')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command creates and send simple email message.

<info>php %command.full_name% -m custom_mailer -ct text/xml</info>

You can get body of message from file:
<info>php %command.full_name% --body-input=file -b /path/to/file</info>

EOF
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mailerServiceName = sprintf('swiftmailer.mailer.%s', $input->getOption('mailer'));
        if (!$this->getContainer()->has($mailerServiceName)) {
            throw new \InvalidArgumentException(sprintf('The mailer "%s" does not exist', $this->getOption('mailer')));
        }

        $dialog = $this->getHelper('dialog');
        foreach ($this->options as $option) {
            if ($input->getOption($option) === null) {
                $input->setOption($option, $dialog->ask($output,
                    sprintf('<question>%s</question>: ', ucfirst($option))
                ));
            }
        }
        switch ($this->getOption('body-input')) {
            case 'file':
                $filename = $input->getOption('body');
                $content = file_get_contents($filename);
                if ($content === false) {
                    throw new \Exception('Could not get contents from ' . $filename);
                }
                $input->setOption('body', $content);
                break;
            case 'stdin':
                break;
            default:
                throw new \InvalidArgumentException('Body-input option should be "stdin" or "file"');
        }

        $message = $this->createMessage($input);
        $transport = $this->getContainer()->get($mailerServiceName)->getTransport();
        $transport->start();
        $output->writeln(sprintf('<info>Sent %s emails<info>', $transport->send($message)));
        $transport->stop();
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        return $this->getContainer()->has('mailer');
    }

    public function createMessage(InputInterface $input)
    {
        $message = \Swift_Message::newInstance(
            $input->getOption('subject'),
            $input->getOption('body'),
            $input->getOption('content-type'),
            $input->getOption('charset')
        );
        $message->setFrom($input->getOption('from'));
        $message->setTo($input->getOption('to'));

        return $message;
    }
}