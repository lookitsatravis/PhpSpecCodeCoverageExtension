<?php

namespace PhpSpec\Extension;

use PhpSpec\ServiceContainer;
use PhpSpec\Extension\Listener\CodeCoverageListener;

/**
 * Injects a Event Subscriber into the EventDispatcher. The Subscriber
 * will before each example add CodeCoverage Information.
 */
class CodeCoverageExtension implements \PhpSpec\Extension\ExtensionInterface
{
    protected $reports = array();

    /**
     * {@inheritDoc}
     */
    public function load(ServiceContainer $container)
    {
        $container->setShared('code_coverage.filter', function () {
            return new \PHP_CodeCoverage_Filter();
        });

        $container->setShared('code_coverage', function ($container) {
            return new \PHP_CodeCoverage(null, $container->get('code_coverage.filter'));
        });

        $container->setShared('code_coverage.reports', function ($container) {
            $options = $container->getParam('code_coverage');

            if (!isset($options['format'])) {
                $options['format'] = 'html';
            }

            if (!isset($options['show_uncovered_files'])) {
                $options['show_uncovered_files'] = true;
            }
            if (!isset($options['lower_upper_bound'])) {
                $options['lower_upper_bound'] = 35;
            }
            if (!isset($options['high_lower_bound'])) {
                $options['high_lower_bound'] = 70;
            }

            $reports = array();

            if(is_string($options['format']))
            {
                $report = $this->createReportForFormat($options['format'], $options);
                $reports[$options['format']] = $report;
            }
            else
            {
                foreach($options['format'] as $format)
                {
                    $report = $this->createReportForFormat($format, $options);
                    $reports[$format] = $report;
                }
            }

            return $reports;
        });

        $container->setShared('event_dispatcher.listeners.code_coverage', function ($container) {
            $listener = new CodeCoverageListener($container->get('code_coverage'), $container->get('code_coverage.reports'));
            $listener->setIO($container->get('console.io'));
            $listener->setOptions($container->getParam('code_coverage', array()));

            return $listener;
        });
    }

    protected function createReportForFormat($format, $options)
    {
        switch ($format) {
            case 'clover':
                return new \PHP_CodeCoverage_Report_Clover();
            case 'php':
                return new \PHP_CodeCoverage_Report_PHP();
            case 'text':
                return new \PHP_CodeCoverage_Report_Text($options['lower_upper_bound'], $options['high_lower_bound'], $options['show_uncovered_files'], /* $showOnlySummary */ false);
            case 'html':
            default:
                return new \PHP_CodeCoverage_Report_HTML();
        }
    }
}
