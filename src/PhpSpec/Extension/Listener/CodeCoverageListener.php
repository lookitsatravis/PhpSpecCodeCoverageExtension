<?php

namespace PhpSpec\Extension\Listener;

use PhpSpec\Console\IO;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;

class CodeCoverageListener implements \Symfony\Component\EventDispatcher\EventSubscriberInterface
{
    private $coverage;
    private $reports;
    private $io;
    private $options;

    public function __construct(\PHP_CodeCoverage $coverage, $reports)
    {
        $this->coverage = $coverage;
        $this->reports   = $reports;
        $this->options  = array(
            'whitelist' => array('src', 'lib'),
            'blacklist' => array('vendor', 'spec'),
            'whitelist_files' => array(),
            'blacklist_files' => array(),
            'output_dir'    => 'coverage',
            'output_files' => array("clover" => "coverage.xml", "php" => "coverage.php", "text" => "coverage.txt"),
            'format'    => 'html',
        );
    }

    public function beforeSuite(SuiteEvent $event)
    {
        $filter = $this->coverage->filter();

        array_map(array($filter, 'addDirectoryToWhitelist'), $this->options['whitelist']);
        array_map(array($filter, 'addDirectoryToBlacklist'), $this->options['blacklist']);
        array_map(array($filter, 'addFileToWhitelist'), $this->options['whitelist_files']);
        array_map(array($filter, 'addFileToBlacklist'), $this->options['blacklist_files']);
    }

    public function beforeExample(ExampleEvent $event)
    {
        $example = $event->getExample();

        $name = strtr('%spec%::%example%', array(
            '%spec%' => $example->getSpecification()->getClassReflection()->getName(),
            '%example%' => $example->getFunctionReflection()->getName(),
        ));

        $this->coverage->start($name);
    }

    public function afterExample(ExampleEvent $event)
    {
        $this->coverage->stop();
    }

    public function afterSuite(SuiteEvent $event)
    {
        if(!file_exists($this->options['output_dir']))
        {
            mkdir($this->options['output_dir'], 0777, true);
        }

        foreach($this->reports as $format => $report)
        {
            if ($this->io) {
                $this->io->writeln('');
                $this->io->writeln(sprintf('Generating code coverage report in %s format ...', $format));
            }

            if ($format == 'text') {
                $output = $report->process($this->coverage, /* showColors */ true);
                $this->io->writeln($output);
            } else if ($format == 'clover' || $format == 'php') {
                $report->process($this->coverage, $this->options['output_dir'] . "/" . $this->options['output_files'][$format]);
            } else {
                $report->process($this->coverage, $this->options['output_dir']);
            }
        }
    }

    public function setIO(IO $io)
    {
        $this->io = $io;
    }

    public function setOptions(array $options)
    {
        $this->options = $options + $this->options;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            'beforeExample' => array('beforeExample', -10),
            'afterExample'  => array('afterExample', -10),
            'beforeSuite'   => array('beforeSuite', -10),
            'afterSuite'    => array('afterSuite', -10),
        );
    }
}
