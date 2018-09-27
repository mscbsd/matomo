<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Actions\DataTable\Filter;

use Piwik\Common;
use Piwik\Config;
use Piwik\DataTable\BaseFilter;
use Piwik\DataTable\Row;
use Piwik\DataTable;

// TODO: the log importer urlencoded URLs & referrer URLs when creating page titles. need to detect these
// and conditionally urldecode
// TODO: should also try to put all this code in SafeDecodeLabel
class Actions extends BaseFilter
{
    /**
     * Constructor.
     *
     * @param DataTable $table The table to eventually filter.
     */
    public function __construct($table)
    {
        parent::__construct($table);
    }

    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        $isFlattening = Common::getRequestVar('flat', 0);

        $table->filter(function (DataTable $dataTable) use ($isFlattening) {
            $defaultActionName = Config::getInstance()->General['action_default_name'];

            // for BC, we read the old style delimiter first (see #1067)
            $actionDelimiter = @Config::getInstance()->General['action_category_delimiter'];
            if (empty($actionDelimiter)) {
                $actionDelimiter = Config::getInstance()->General['action_url_category_delimiter'];
            }

            foreach ($dataTable->getRows() as $row) {
                $url = $row->getMetadata('url');
                if ($url) {
                    // encoding the value since Segment will decode the condition AND the value. without encoding here, segments
                    // that for URLs w/ plus signs will decode to whitespace, and select no data.
                    $row->setMetadata('segmentValue', urlencode($url));
                }

                // remove the default action name 'index' in the end of flattened urls and prepend $actionDelimiter
                if ($isFlattening) {
                    $label = $row->getColumn('label');
                    $stringToSearch = $actionDelimiter.$defaultActionName;
                    if (substr($label, -strlen($stringToSearch)) == $stringToSearch) {
                        $label = substr($label, 0, -strlen($defaultActionName));
                        $label = rtrim($label, $actionDelimiter) . $actionDelimiter;
                        $row->setColumn('label', $label);
                    }
                    $dataTable->setLabelsHaveChanged();
                }
            }
        });

        foreach ($table->getRowsWithoutSummaryRow() as $row) {
            $subtable = $row->getSubtable();
            if ($subtable) {
                $this->filter($subtable);
            }
        }
    }
}